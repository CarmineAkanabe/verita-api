<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\Department;

final class AnalyticsService
{
    public function generateUserEngagementReport(): array
    {
        return [
            'caseVolumeByDepartment' => $this->caseVolumeByDepartment(),
            'averageResolutionDays' => $this->averageResolutionDays(),
            'categoryBreakdownOverTime' => $this->categoryBreakdownOverTime(),
        ];
    }

    private function caseVolumeByDepartment(): array
    {
        return Department::withCount('cases')->get()
            ->map(fn(Department $d) => ['department' => $d->name, 'count' => $d->cases_count])
            ->all();
    }

    private function averageResolutionDays(): ?float
    {
        $avg = CaseRecord::whereNotNull('resolved_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 86400) as avg_days')
            ->value('avg_days');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    private function categoryBreakdownOverTime(): array
    {
        return CaseRecord::query()
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, category, COUNT(*) as count")
            ->groupBy('month', 'category')
            ->orderBy('month')
            ->get()
            ->map(fn($row) => [
                'month' => $row->month,
                'category' => $row->category,
                'count' => (int) $row->count,
            ])->all();
    }
}
