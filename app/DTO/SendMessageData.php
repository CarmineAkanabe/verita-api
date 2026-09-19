<?php

namespace App\DTO;

readonly class SendMessageData
{
    private function __construct(public string $content) {}

    public static function fromRequest(array $validated): self
    {
        return new self(content: $validated['content']);
    }
}
