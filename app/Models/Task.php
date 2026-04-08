<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $fillable = [
        'title',
        'description',
        'repeat_type',
        'repeat_days',
        'reminder_before_minutes',
        'occurrences',
        'capture_photo',
        'notify_once_done',
        'overdue_alarm_enabled',
        'icon_name',
        'active',
    ];

    protected $casts = [
        'repeat_days' => 'array',
        'capture_photo' => 'boolean',
        'notify_once_done' => 'boolean',
        'overdue_alarm_enabled' => 'boolean',
        'active' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignments', 'task_id', 'user_id')
            ->withTimestamps();
    }

    public function times(): HasMany
    {
        return $this->hasMany(TaskTime::class);
    }
}

