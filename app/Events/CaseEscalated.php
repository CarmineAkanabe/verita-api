<?php

namespace App\Events;

use App\Models\CaseRecord;
use Illuminate\Foundation\Events\Dispatchable;

class CaseEscalated
{
    use Dispatchable;

    public function __construct(public readonly CaseRecord $case) {}
}
