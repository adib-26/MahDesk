<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportRequest;
use App\Models\Workspace;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;

/**
 * Support analytics for the last 30 days, scoped to what the caller can see
 * (managers get their teams only). Same numbers as the desk dashboard.
 */
class ReportController extends Controller
{
    public function __construct(private AnalyticsService $analytics) {}

    /** KPIs, the daily created/resolved series and breakdowns. */
    public function overview(ReportRequest $request, Workspace $organization): JsonResponse
    {
        $report = $this->analytics->forWorkspace($organization, $request->user());

        return response()->json([
            'data' => [
                'period' => $this->period(),
                'kpis' => $report['kpis'],
                'series' => $report['series'],
                'breakdowns' => [
                    'by_status' => $report['byStatus'],
                    'by_priority' => $report['byPriority'],
                    'by_priority_in_period' => $report['byPriorityInPeriod'],
                    'by_channel' => $report['byChannel'],
                    'by_category' => $report['byCategory'],
                ],
            ],
        ]);
    }

    /** Per-agent workload and performance. */
    public function agents(ReportRequest $request, Workspace $organization): JsonResponse
    {
        $report = $this->analytics->forWorkspace($organization, $request->user());

        return response()->json([
            'data' => $report['agents'],
            'meta' => ['period' => $this->period()],
        ]);
    }

    /** @return array{from: string, to: string, days: int} */
    private function period(): array
    {
        return [
            'from' => now()->subDays(29)->startOfDay()->toIso8601String(),
            'to' => now()->toIso8601String(),
            'days' => 30,
        ];
    }
}
