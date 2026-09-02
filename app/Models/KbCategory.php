<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KbCategory extends Model
{
    use HasFactory;

    protected $fillable = ['workspace_id', 'name', 'slug', 'description', 'position'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(KbArticle::class);
    }
}
