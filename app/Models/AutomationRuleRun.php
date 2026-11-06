<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRuleRun extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['automation_rule_id', 'ticket_id', 'event', 'actions'];

    protected function casts(): array
    {
        return [
            'actions' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'automation_rule_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
