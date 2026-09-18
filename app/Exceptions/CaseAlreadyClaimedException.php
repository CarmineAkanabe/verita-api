<?php

namespace App\Exceptions;

// use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CaseAlreadyClaimedException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('This case has already been claimed by another Department Head.');
    }
}
