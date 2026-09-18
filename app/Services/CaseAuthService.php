<?php

namespace App\Services;

use App\Models\CaseRecord;
use Illuminate\Support\Facades\Hash;

class CaseAuthService
{
    /**
     * Create a new class instance.
     */
    public function __construct() {}
    public function verifyPin(string $caseId, string $pin): string
    {
        $case = CaseRecord::find($caseId);

        if (! $case || ! Hash::check($pin, $case->tracking_pin_hash)) {
            abort(401, 'Invalid Case ID or Tracking PIN.');
        }

        return auth('case-api')->login($case);
    }
}
