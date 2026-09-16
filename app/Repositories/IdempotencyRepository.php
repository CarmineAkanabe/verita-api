<?php

namespace App\Repositories;

class IdempotencyRepository
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    // owns Cache::get/put for idempotency keys — filled in per-phase as needed
}
