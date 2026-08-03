<?php

namespace App\Services;

use App\Exceptions\MissingAiKeyException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AiCopyService
{
    public function generateDescription(string $menuName, ?int $priceIdr = null): string
    {
        $userPrompt = "Buat deskripsi promosi untuk menu \"{$menuName}\"";

        if ($priceIdr !== null) {
            $userPrompt .= ' dengan harga Rp '.number_format($priceIdr, 0, ',', '.');
        }

        return $this->requestCopy($userPrompt);
    }

    public function generatePromoSubtitle(string $promoTitle): string
    {
        return $this->requestCopy("Buat subjudul promosi untuk \"{$promoTitle}\" di kedai kopi");
    }

    private function requestCopy(string $userPrompt): string
    {
        $apiKey = config('services.deepseek.api_key');

        if (blank($apiKey)) {
            throw MissingAiKeyException::create();
        }

        try {
            // Timeout per attempt; retry(2, ...) is 2 TOTAL attempts (1
            // retry) for connection failures and server errors (5xx) with
            // 1s backoff. Client errors (4xx) fail fast, and once the
            // retries are exhausted the request throws.
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(30)
                ->retry(2, 1000, fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response?->serverError()))
                ->post(rtrim(config('services.deepseek.base_url'), '/').'/chat/completions', [
                    'model' => config('services.deepseek.model'),
                    'thinking' => ['type' => 'disabled'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Kamu adalah penulis pemasaran untuk sebuah kedai kopi di Indonesia. '
                                .'Tulis teks promosi yang SINGKAT dalam bahasa Indonesia: maksimal satu kalimat pendek (10-15 kata). '
                                .'Jawab HANYA dengan teks: tanpa tanda kutip, tanpa kata pengantar, tanpa penjelasan tambahan.',
                        ],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'max_tokens' => 1024,
                ]);
        } catch (RequestException $e) {
            $status = $e->response?->status() ?? 'unknown';

            throw new RuntimeException(
                'DeepSeek API request failed with status '.$status
                .': '.mb_substr((string) ($e->response?->body() ?? $e->getMessage()), 0, 300),
                previous: $e
            );
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'DeepSeek API request failed: '.$e->getMessage(),
                previous: $e
            );
        }

        $content = $this->extractContent($response);

        if ($content === '') {
            $finishReason = $response->json('choices.0.finish_reason');

            throw new RuntimeException(
                'DeepSeek API response contained no content'
                .(is_string($finishReason) ? " (finish_reason: {$finishReason})" : '')
                .': '.mb_substr($response->body(), 0, 300)
            );
        }

        return $content;
    }

    /**
     * Extract the assistant message text. DeepSeek can return the content
     * as a plain string or, in thinking mode, as an ARRAY of part objects
     * (e.g. [['type' => 'text', 'text' => '...']]); join the text fields
     * of every part so an array is never cast to a string.
     */
    protected function extractContent(Response $response): string
    {
        $raw = $response->json('choices.0.message.content');

        if (is_array($raw)) {
            $text = '';

            foreach ($raw as $part) {
                if (is_string($part)) {
                    $text .= $part;
                } elseif (is_array($part) && isset($part['text'])) {
                    $text .= (string) $part['text'];
                }
            }

            return trim($text);
        }

        return trim((string) ($raw ?? ''));
    }
}
