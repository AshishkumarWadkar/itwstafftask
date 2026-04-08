<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskLog;
use Illuminate\Http\Request;

class UserTaskController extends Controller
{
    /**
     * Returns occurrence-level tasks for a day, sorted pending then completed, by time.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'day_key' => ['nullable', 'integer'],
        ]);

        $dayKey = $data['day_key'] ?? now()->startOfDay()->getTimestampMs();

        $tasks = Task::query()
            ->where('active', true)
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->with(['times:id,task_id,occurrence_minutes,sort_order'])
            ->get();

        $rows = [];
        foreach ($tasks as $task) {
            $times = $task->times->sortBy('occurrence_minutes')->values();
            $minutesList = $times->count() ? $times->pluck('occurrence_minutes')->all() : [0];

            foreach ($minutesList as $m) {
                $log = TaskLog::query()
                    ->where('task_id', $task->id)
                    ->where('user_id', $user->id)
                    ->where('day_key', $dayKey)
                    ->where('occurrence_minutes', $m)
                    ->first();

                $rows[] = [
                    'task_id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'icon_name' => $task->icon_name,
                    'capture_photo' => $task->capture_photo,
                    'notify_once_done' => $task->notify_once_done,
                    'overdue_alarm_enabled' => $task->overdue_alarm_enabled,
                    'repeat_type' => $task->repeat_type,
                    'repeat_days' => $task->repeat_days,
                    'reminder_before_minutes' => $task->reminder_before_minutes,
                    'day_key' => $dayKey,
                    'occurrence_minutes' => $m,
                    'completed' => (bool) optional($log)->completed,
                    'proof_path' => optional($log)->proof_path,
                    'completed_at' => optional($log)->completed_at,
                ];
            }
        }

        usort($rows, function ($a, $b) {
            if ($a['completed'] !== $b['completed']) return $a['completed'] ? 1 : -1;
            return ($a['occurrence_minutes'] ?? 0) <=> ($b['occurrence_minutes'] ?? 0);
        });

        return response()->json(['day_key' => $dayKey, 'items' => $rows]);
    }
}

