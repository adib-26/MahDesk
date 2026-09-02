<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function __construct(private AuditLogger $audit)
    {
    }
    /**
     * Display the authenticated user's active database sessions.
     */
    public function index(Request $request): Response
    {
        $currentSessionId = $request->session()->getId();

        $sessions = $this->sessionsFor($request)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session) => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_active_at' => Carbon::createFromTimestamp((int) $session->last_activity)->toIso8601String(),
                'is_current' => $session->id === $currentSessionId,
            ]);

        return Inertia::render('settings/sessions', [
            'sessions' => $sessions,
        ]);
    }

    /**
     * Revoke one of the authenticated user's sessions.
     */
    public function destroy(Request $request, string $session): RedirectResponse
    {
        $deleted = $this->sessionsFor($request)
            ->where('id', $session)
            ->delete();

        abort_unless($deleted, 404);

        if ($session === $request->session()->getId()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('success', 'This session has been signed out.');
        }

        return back()->with('success', 'Session revoked.');
    }

    /**
     * Revoke every session except the current one after password confirmation.
     */
    public function destroyOther(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $deleted = $this->sessionsFor($request)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        if ($deleted) {
            $this->audit->record(
                'sessions.revoked_others',
                $request->user(),
                null,
                $request->user(),
                ['count' => $deleted],
            );
        }

        return back()->with(
            'success',
            $deleted ? 'All other sessions have been signed out.' : 'There are no other active sessions.',
        );
    }

    /**
     * Scope database-session operations to the authenticated user.
     */
    private function sessionsFor(Request $request)
    {
        return DB::table(config('session.table'))
            ->where('user_id', $request->user()->getAuthIdentifier());
    }
}
