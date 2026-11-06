<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\AutomationEngine;
use Illuminate\Console\Command;

class RunAutomationEscalations extends Command
{
    protected $signature = 'desk:run-automations
        {--workspace= : Optional workspace slug to scope evaluation}';

    protected $description = 'Evaluate time-based workflow escalation rules across unresolved tickets';

    public function handle(AutomationEngine $engine): int
    {
        $slug = $this->option('workspace');
        $workspace = $slug ? Workspace::query()->where('slug', $slug)->first() : null;

        if ($slug && ! $workspace) {
            $this->error("Workspace [{$slug}] not found.");

            return self::FAILURE;
        }

        $stats = $engine->runEscalations($workspace);

        $this->info(sprintf(
            '[%s] evaluated %d ticket(s), triggered %d escalation(s)',
            now()->format('H:i:s'),
            $stats['evaluated'],
            $stats['triggered'],
        ));

        foreach ($stats['summaries'] as $summary) {
            $this->line("  • {$summary}");
        }

        return self::SUCCESS;
    }
}
