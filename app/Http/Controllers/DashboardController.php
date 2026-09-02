<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Workspace;
use App\Services\AnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, Workspace $workspace, AnalyticsService $analytics): Response|RedirectResponse
    {
        if (! Gate::allows('viewAnalytics', $workspace)) {
            return redirect()->route('desk.tickets.index', $workspace);
        }

        $user = $request->user();

        $recentTickets = Ticket::query()
            ->visibleTo($user, $workspace)
            ->with(['contact:id,name,email', 'assignee:id,name'])
            ->latest()
            ->limit(6)
            ->get();

        return Inertia::render('desk/dashboard', [
            'analytics' => $analytics->forWorkspace($workspace, $user),
            'recentTickets' => $recentTickets,
        ]);
    }
}
