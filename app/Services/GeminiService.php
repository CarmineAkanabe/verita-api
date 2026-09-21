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
                'key' => config('services.gemini.key'),
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
                    // (IMAGE/PDF only) - that enum can't distinguish jpg vs png.
                    'mime_type' => Storage::disk('local')->mimeType($evidence->file_path),
                    'data' => base64_encode(Storage::disk('local')->get($evidence->file_path)),
                ],
            ];
        }

        $response = Http::timeout(60)
            ->retry(3, 1500)
            ->post($this->endpoint('generateContent'), [
                'contents' => [['parts' => $parts]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
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
You are an objective compliance assistant structuring an internal workplace misconduct case for human investigator review.
Analyze all provided statements, details, and attached documents/evidence.
Do not judge guilt, assign blame, or produce a severity or risk rating — report objective facts, chronological milestones, documentation gaps, and contradictions only.

Incident Details:
- Description: {$case->description}
- Purpose of transaction: {$case->purpose_of_transaction}
- Amount involved: {$case->amount_involved}
- Person involved: {$case->person_involved}
- Transaction date: {$case->transaction_date}

Instructions:
1. "summary": A clear, multi-sentence factual summary of the incident and what transpired according to the statements and evidence.
2. "timeline": Extract or infer a chronological list of events and milestones based on the statements, receipts, and chat dates. Each item MUST be an object:
   {"date": "YYYY-MM-DD", "time": "HH:MM", "event": "Short title", "description": "1-2 sentence description"}
3. "completeness": Array of specific missing documents, unverified identities, or proof gaps that the investigator should obtain.
4. "consistency": Array of specific factual discrepancies, mismatched names, currency differences, or date conflicts found between the report description and attached evidence.
5. "clarifications": Array of targeted, high-priority questions the investigator should ask during consultation.

Respond with strict JSON only, matching this shape:
{
  "summary": "string",
  "timeline": [
    {"date": "YYYY-MM-DD", "time": "string", "event": "string", "description": "string"}
  ],
  "completeness": ["string"],
  "consistency": ["string"],
  "clarifications": ["string"]
}
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
