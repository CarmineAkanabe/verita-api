<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiService
{
    public function ping(string $prompt): array
    {
        $response = Http::timeout(60)
            ->connectTimeout(15)
            ->retry(3, 1000)
            ->withQueryParameters([
                'key' => config('services.gemini.api_key'),
            ])
            ->post(
                'https://generativelanguage.googleapis.com/v1beta/models/'
                    . config('services.gemini.model')
                    . ':generateContent',
                [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                ],
            );

        return $response->throw()->json();
    }
}
