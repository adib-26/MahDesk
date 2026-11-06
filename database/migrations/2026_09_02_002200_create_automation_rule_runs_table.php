<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rule_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->json('actions');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['automation_rule_id', 'ticket_id']);
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::table('automation_rules', function (Blueprint $table) {
            $table->index(['workspace_id', 'event', 'is_active']);
        });

        $this->backfillFromTicketEvents();
    }

    public function down(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'event', 'is_active']);
        });

        Schema::dropIfExists('automation_rule_runs');
    }

    /**
     * Earlier versions recorded rule executions only as ticket timeline events.
     * Copy them over so escalation rules don't fire a second time after deploy.
     */
    private function backfillFromTicketEvents(): void
    {
        $ruleIds = DB::table('automation_rules')->pluck('id')->flip();

        DB::table('ticket_messages')
            ->where('kind', 'event')
            ->whereNotNull('meta')
            ->orderBy('id')
            ->chunkById(500, function ($messages) use ($ruleIds) {
                $rows = [];

                foreach ($messages as $message) {
                    $meta = json_decode((string) $message->meta, true);
                    $ruleId = (int) ($meta['rule_id'] ?? 0);

                    if ($ruleId === 0 || ! $ruleIds->has($ruleId)) {
                        continue;
                    }

                    $rows[] = [
                        'automation_rule_id' => $ruleId,
                        'ticket_id' => $message->ticket_id,
                        'event' => (string) ($meta['event'] ?? 'ticket_escalation'),
                        'actions' => json_encode($meta['actions'] ?? []),
                        'created_at' => $message->created_at,
                    ];
                }

                if ($rows !== []) {
                    DB::table('automation_rule_runs')->insert($rows);
                }
            });
    }
};
