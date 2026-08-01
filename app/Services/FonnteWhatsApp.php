<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FonnteWhatsApp
{
    public function __construct(
        protected ?string $token = null,
        protected ?string $baseUrl = null,
    ) {
        $this->token ??= config('whatsapp.fonnte.token');
        $this->baseUrl ??= config('whatsapp.fonnte.url');
    }

    public function send(string $phone, string $message): bool
    {
        if ($this->token === null || $this->token === '') {
            Log::warning(__('whatsapp.log.no_token'), ['phone' => $phone]);

            return false;
        }

        try {
            $response = Http::acceptJson()
                ->asForm()
                ->withHeaders(['Authorization' => $this->token])
                ->post($this->baseUrl, [
                    'target' => $phone,
                    'message' => $message,
                ]);
        } catch (Throwable $e) {
            Log::warning(__('whatsapp.log.exception'), [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($response->failed() || (($response->json()['status'] ?? true) === false)) {
            Log::warning(__('whatsapp.log.failed'), [
                'phone' => $phone,
                'status' => $response->status(),
                'body' => (string) $response->body(),
            ]);

            return false;
        }

        return true;
    }
}
