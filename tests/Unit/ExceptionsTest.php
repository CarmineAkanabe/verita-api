<?php

use App\Exceptions\CaseAlreadyClaimedException;
use App\Exceptions\GeminiResponseException;
// use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

test('CaseAlreadyClaimedException is a 409 with the expected message', function () {
    $exception = new CaseAlreadyClaimedException();

    expect($exception)->toBeInstanceOf(ConflictHttpException::class)
        ->and($exception->getStatusCode())->toBe(409)
        ->and($exception->getMessage())->toBe('This case has already been claimed by another Department Head.');
});

test('GeminiResponseException carries no HTTP mapping', function () {
    $exception = new GeminiResponseException('malformed response');

    expect($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception)->not->toBeInstanceOf(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class)
        ->and($exception->getMessage())->toBe('malformed response');
});
