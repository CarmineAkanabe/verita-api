<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
// use Illuminate\Http\Request;

final class UserEngagementReportController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}

    public function __invoke(): JsonResponse
    {
        return response()->json(
            ['data' => $this->analytics->generateUserEngagementReport()],
            200,
            [],
            JSON_PRESERVE_ZERO_FRACTION,
        );
    }
}
