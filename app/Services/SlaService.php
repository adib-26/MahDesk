<?php

namespace App\Services;

use App\Models\SlaPolicy;
use App\Models\Ticket;

class SlaService
{
    /**
     * Match the best SLA policy for the ticket and (re)compute due dates.
     * A policy targeting the ticket's exact priority wins over the default policy.
     */
    public function apply(Ticket $ticket): void
    {
        $policy = $this->match($ticket);

        if (! $policy) {
            return;
        }

        $base = $ticket->created_at ?? now();

        $ticket->forceFill([
            'sla_policy_id' => $policy->id,
            'first_response_due_at' => $base->copy()->addMinutes($policy->first_response_minutes),
            'resolution_due_at' => $base->copy()->addMinutes($policy->resolution_minutes),
        ])->save();
    }

    public function match(Ticket $ticket): ?SlaPolicy
    {
        $policies = SlaPolicy::query()
            ->where('workspace_id', $ticket->workspace_id)
            ->get();

        return $policies->firstWhere('priority', $ticket->priority->value)
            ?? $policies->firstWhere('is_default', true);
    }
}
