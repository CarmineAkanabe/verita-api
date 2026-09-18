<?php

namespace App\Services;

use App\Exceptions\GeminiResponseException;
use App\Models\CaseRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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

    public function analyzeCase(CaseRecord $case): array
    {
        $parts = [['text' => $this->buildCasePrompt($case)]];

        foreach ($case->evidence as $evidence) {
            $parts[] = [
                'inline_data' => [
                    // read the real mime off disk rather than trusting file_type
                    // (IMAGE/PDF only) — that enum can't distinguish jpg vs png.
                    'mime_type' => Storage::disk('local')->mimeType($evidence->file_path),
                    'data' => base64_encode(Storage::disk('local')->get($evidence->file_path)),
                ],
            ];
        }

        $response = Http::timeout(60)
            ->post($this->endpoint('generateContent'), [
                'contents' => [['parts' => $parts]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    // Phase 0 flagged 137 thinking tokens on a 2-word test answer —
                    // this is that flag getting acted on.
                    'thinkingConfig' => ['thinkingBudget' => 0],
                ],
            ])
            ->throw();

        $text = $response->json('candidates.0.content.parts.0.text');
        $decoded = json_decode($text, true);

        return $decoded ?? throw new GeminiResponseException('Gemini returned a non-JSON response.');
    }

    private function buildCasePrompt(CaseRecord $case): string
    {
        return <<<PROMPT
        You are structuring a workplace fraud report for internal review.
        Do not judge guilt, assign blame, or produce a severity, credibility,
        or risk score — report facts, gaps, and inconsistencies only.

        Incident description: {$case->description}
        Purpose of transaction: {$case->purpose_of_transaction}
        Amount involved: {$case->amount_involved}
        Person involved: {$case->person_involved}
        Transaction date: {$case->transaction_date}

        Respond with strict JSON only, matching this shape:
        {"summary": string, "timeline": array, "completeness": array, "consistency": array, "clarifications": array}
        PROMPT;
    }

    private function endpoint(string $action): string
    {
        $model = config('services.gemini.model', 'gemini-3.5-flash');
        $apiKey = config('services.gemini.key');

        // Standard Gemini REST API URL structure
        return "https://generativelanguage.googleapis.com/v1beta/models/{$model}:{$action}?key={$apiKey}";
    }
}
