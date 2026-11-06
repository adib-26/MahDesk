<?php

namespace App\Http\Controllers;

use App\Models\InboundEmail;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InboundEmailController extends Controller
{
    public function index(Request $request, Workspace $workspace): Response
    {
        Gate::authorize('viewAny', [Ticket::class, $workspace]);

        $emails = InboundEmail::query()
            ->where('workspace_id', $workspace->id)
            ->with('ticket:id,number')
            ->latest()
            ->paginate(25)
            ->through(function (InboundEmail $email): array {
                $payload = $email->payload ?? [];

                return [
                    'id' => $email->id,
                    'from' => $email->from_email,
                    'subject' => $email->subject ?: '(No subject)',
                    'body' => (string) ($payload['text'] ?? ''),
                    'html' => (string) ($payload['html'] ?? ''),
                    'status' => $email->status,
                    'status_reason' => $email->status_reason,
                    'provider' => $email->provider,
                    'created_at' => $email->created_at?->toISOString(),
                    'ticket' => $email->ticket ? ['id' => $email->ticket->id, 'number' => $email->ticket->number] : null,
                ];
            });

        return Inertia::render('desk/emails/index', [
            'emails' => $emails,
            'inboundAddress' => $workspace->inboundEmailAddress(),
        ]);
    }
}
