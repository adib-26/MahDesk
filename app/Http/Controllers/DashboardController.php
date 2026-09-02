<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\AnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Workspace $workspace, AnalyticsService $analytics): Response
    {
        $recentTickets = $workspace->tickets()
            ->with(['contact:id,name,email', 'assignee:id,name'])
            ->latest()
            ->limit(6)
            ->get();

        return Inertia::render('desk/dashboard', [
            'analytics' => $analytics->forWorkspace($workspace),
            'recentTickets' => $recentTickets,
        ]);
    }
}
