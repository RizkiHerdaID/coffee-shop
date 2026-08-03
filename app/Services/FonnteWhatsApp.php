<?php

namespace App\Services;

use App\Support\Phone;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FonnteWhatsApp
{
    public const DEFAULT_MAX_ATTEMPTS = 3;

    public const DEFAULT_RETRY_DELAYS = [1, 3];

    /**
     * Substrings that mark a Fonnte failure reason as transient (worth a
     * retry). A status:false reason with none of these is treated as
     * permanent (e.g. invalid token/target, quota) and fails fast.
     *
     * @var string[]
     */
    public const TRANSIENT_REASON_MARKERS = ['temporar', 'timeout', 'busy', 'rate'];

    public function __construct(
        protected ?string $token = null,
        protected ?string $baseUrl = null,
        protected int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        protected array $retryDelays = self::DEFAULT_RETRY_DELAYS,
    ) {
        $this->token ??= config('whatsapp.fonnte.token');
        $this->baseUrl ??= config('whatsapp.fonnte.url');
    }

    public function send(string $phone, string $message): bool
    {
        $phone = Phone::normalize($phone);

        if ($phone === '') {
            Log::warning(__('whatsapp.log.no_phone'), ['phone' => $phone]);

            return false;
        }

        if ($this->token === null || $this->token === '') {
            Log::warning(__('whatsapp.log.no_token'), ['phone' => $phone]);

            return false;
        }

        $response = null;
        $exception = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            if ($attempt > 1) {
                sleep($this->retryDelay($attempt));
            }

            try {
                // Explicit timeouts keep a single attempt's worst case at
                // ~25s (15s response + 10s connect), well under the queue
                // worker's --timeout (120s in compose.yaml), so a slow
                // gateway can never get the worker killed mid-attempt and
                // the job re-run (duplicate customer-facing sends).
                $response = Http::acceptJson()
                    ->asForm()
                    ->withHeaders(['Authorization' => $this->token])
                    ->timeout(15)
                    ->connectTimeout(10)
                    ->post($this->baseUrl, [
                        'target' => $phone,
                        'message' => $message,
                    ]);
                $exception = null;
            } catch (Throwable $e) {
                $exception = $e;
                $response = null;
            }

            if ($response !== null && $this->isSuccess($response)) {
                return true;
            }

            // Permanent failures (e.g. token/target invalid, quota) can
            // never succeed by retrying, so skip the remaining attempts.
            if ($response !== null && $this->isPermanentFailure($response)) {
                break;
            }

            // Non-successful response (HTTP error, non-JSON body, or a
            // JSON body without a truthy status): keep retrying with
            // backoff until the final attempt, then fail below.
        }

        if ($response !== null) {
            Log::warning(__('whatsapp.log.failed'), [
                'phone' => $phone,
                'status' => $response->status(),
                'body' => (string) $response->body(),
            ]);
        } else {
            Log::warning(__('whatsapp.log.exception'), [
                'phone' => $phone,
                'error' => $exception?->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Seconds to wait before the given attempt (1-based). Falls back to
     * the last configured delay so extra attempts stay spaced out.
     */
    protected function retryDelay(int $attempt): int
    {
        $index = $attempt - 2;

        if (isset($this->retryDelays[$index])) {
            return (int) $this->retryDelays[$index];
        }

        $last = end($this->retryDelays);

        return is_int($last) ? $last : 3;
    }

    /**
     * Fonnte reports success as a JSON body with a truthy "status" field
     * (e.g. {"status": true}). A non-JSON body, a JSON error payload or
     * an HTTP error status all count as failure.
     */
    protected function isSuccess(Response $response): bool
    {
        if ($response->failed()) {
            return false;
        }

        $body = $response->json();

        return is_array($body) && (bool) ($body['status'] ?? false);
    }

    /**
     * A JSON body with status:false and a non-empty reason that shows no
     * sign of being transient (per TRANSIENT_REASON_MARKERS) will not
     * succeed on retry, so it is not worth further attempts. HTTP errors,
     * non-JSON bodies, connection exceptions and status:false bodies
     * without a reason key stay retryable.
     */
    protected function isPermanentFailure(Response $response): bool
    {
        $body = $response->json();

        if (! is_array($body) || (bool) ($body['status'] ?? true)) {
            return false;
        }

        $reason = strtolower((string) ($body['reason'] ?? ''));

        if ($reason === '') {
            return false;
        }

        foreach (self::TRANSIENT_REASON_MARKERS as $marker) {
            if (str_contains($reason, $marker)) {
                return false;
            }
        }

        return true;
    }
}
