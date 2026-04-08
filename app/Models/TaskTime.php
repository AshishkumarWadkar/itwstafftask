<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTime extends Model
{
    protected $fillable = [
        'task_id',
        'occurrence_minutes',
        'sort_order',
    ];

    public $timestamps = false;

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}

