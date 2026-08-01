<?php

namespace App\Services;

use App\Exceptions\MissingAiKeyException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiCopyService
{
    public function generateDescription(string $menuName, ?int $priceIdr = null): string
    {
        $apiKey = config('services.deepseek.api_key');

        if (blank($apiKey)) {
            throw MissingAiKeyException::create();
        }

        $userPrompt = "Buat deskripsi promosi untuk menu \"{$menuName}\"";

        if ($priceIdr !== null) {
            $userPrompt .= ' dengan harga Rp '.number_format($priceIdr, 0, ',', '.');
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->post(rtrim(config('services.deepseek.base_url'), '/').'/chat/completions', [
                'model' => config('services.deepseek.model'),
                'thinking' => ['type' => 'disabled'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Kamu adalah penulis pemasaran untuk sebuah kedai kopi di Indonesia. '
                            .'Tulis deskripsi promosi menu yang SINGKAT dalam bahasa Indonesia: maksimal satu kalimat pendek (10-15 kata). '
                            .'Jawab HANYA dengan teks deskripsi: tanpa tanda kutip, tanpa kata pengantar, tanpa penjelasan tambahan.',
                    ],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => 1024,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'DeepSeek API request failed with status '.$response->status()
                .': '.mb_substr($response->body(), 0, 300)
            );
        }

        $content = trim((string) ($response->json('choices.0.message.content') ?? ''));

        if ($content === '') {
            $finishReason = $response->json('choices.0.finish_reason');

            throw new RuntimeException(
                'DeepSeek API response contained no description'
                .(is_string($finishReason) ? " (finish_reason: {$finishReason})" : '')
                .': '.mb_substr($response->body(), 0, 300)
            );
        }

        return $content;
    }
}
