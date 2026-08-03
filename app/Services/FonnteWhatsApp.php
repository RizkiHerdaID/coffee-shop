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
                $response = Http::acceptJson()
                    ->asForm()
                    ->withHeaders(['Authorization' => $this->token])
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
}
