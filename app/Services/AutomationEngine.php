<?php

namespace App\Services;

use App\Models\AutomationRule;
use App\Models\Tag;
use App\Models\Ticket;
use Illuminate\Support\Str;

class AutomationEngine
{
    public function __construct(private SlaService $slaService)
    {
    }

    /**
     * Run all active rules for the given event against the ticket.
     * Conditions within a rule are AND-ed; rules run in position order.
     */
    public function run(Ticket $ticket, string $event): void
    {
        $rules = AutomationRule::query()
            ->where('workspace_id', $ticket->workspace_id)
            ->where('event', $event)
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        foreach ($rules as $rule) {
            if (! $this->matches($ticket, $rule->conditions ?? [])) {
                continue;
            }

            $applied = $this->applyActions($ticket, $rule->actions ?? []);

            if ($applied !== []) {
                $ticket->logEvent(
                    sprintf('Automation "%s" applied: %s', $rule->name, implode(', ', $applied)),
                    null,
                    ['rule_id' => $rule->id],
                );
            }
        }
    }

    private function matches(Ticket $ticket, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? 'equals';
            $value = (string) ($condition['value'] ?? '');

            $actual = match ($field) {
                'subject' => $ticket->subject,
                'priority' => $ticket->priority->value,
                'status' => $ticket->status->value,
                'channel' => $ticket->channel->value,
                'contact_email' => $ticket->contact?->email ?? '',
                default => null,
            };

            if ($actual === null) {
                return false;
            }

            $ok = match ($operator) {
                'equals' => Str::lower($actual) === Str::lower($value),
                'not_equals' => Str::lower($actual) !== Str::lower($value),
                'contains' => Str::contains(Str::lower($actual), Str::lower($value)),
                default => false,
            };

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string> human readable descriptions of the applied actions
     */
    private function applyActions(Ticket $ticket, array $actions): array
    {
        $applied = [];
        $priorityChanged = false;

        foreach ($actions as $action) {
            $type = $action['type'] ?? null;
            $value = $action['value'] ?? null;

            switch ($type) {
                case 'set_priority':
                    if ($ticket->priority->value !== $value) {
                        $ticket->priority = $value;
                        $priorityChanged = true;
                        $applied[] = "priority set to {$value}";
                    }
                    break;

                case 'set_status':
                    if ($ticket->status->value !== $value) {
                        $ticket->status = $value;
                        $applied[] = "status set to {$value}";
                    }
                    break;

                case 'assign_agent':
                    $member = $ticket->workspace->members()->where('user_id', $value)->first();
                    if ($member && $ticket->assignee_id !== $member->id) {
                        $ticket->assignee_id = $member->id;
                        $applied[] = "assigned to {$member->name}";
                    }
                    break;

                case 'add_tag':
                    $tag = Tag::firstOrCreate(
                        ['workspace_id' => $ticket->workspace_id, 'name' => $value],
                    );
                    $ticket->tags()->syncWithoutDetaching([$tag->id]);
                    $applied[] = "tagged \"{$value}\"";
                    break;

                case 'add_note':
                    $ticket->messages()->create([
                        'kind' => 'note',
                        'body' => (string) $value,
                    ]);
                    $applied[] = 'internal note added';
                    break;
            }
        }

        if ($ticket->isDirty()) {
            $ticket->save();
        }

        if ($priorityChanged) {
            $this->slaService->apply($ticket);
        }

        return $applied;
    }
}
