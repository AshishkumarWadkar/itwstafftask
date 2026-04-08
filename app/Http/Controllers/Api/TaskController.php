<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskTime;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index()
    {
        return Task::query()
            ->with(['times:id,task_id,occurrence_minutes,sort_order', 'users:id,name,phone'])
            ->orderBy('id', 'desc')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'repeat_type' => ['required', 'in:weekdays,weekends,everyday,once'],
            'repeat_days' => ['nullable', 'array'],
            'repeat_days.*' => ['in:Mon,Tue,Wed,Thu,Fri,Sat,Sun'],
            'reminder_before_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            // When a task "has ETA", the app sends time slots in times_minutes.
            // When it does NOT have ETA, the app sends an empty/omitted times_minutes,
            // and the task is treated as "any time".
            'occurrences' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'times_minutes' => ['nullable', 'array'],
            'times_minutes.*' => ['integer', 'min:0', 'max:1439'],
            'assigned_user_ids' => ['required', 'array', 'min:1'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
            'capture_photo' => ['boolean'],
            'notify_once_done' => ['boolean'],
            'overdue_alarm_enabled' => ['boolean'],
            'icon_name' => ['nullable', 'string', 'max:100'],
            'active' => ['boolean'],
        ]);

        $minutes = array_values(array_unique(array_map('intval', $data['times_minutes'] ?? [])));
        $minutes = array_values(array_filter($minutes, fn ($m) => is_int($m) && $m >= 0 && $m <= 1439));
        sort($minutes);

        // occurrences: prefer explicit occurrences, else derive from time slots, else 1 (no-ETA).
        $occurrences = isset($data['occurrences'])
            ? (int) $data['occurrences']
            : (count($minutes) > 0 ? count($minutes) : 1);
        $occurrences = max(1, min(12, $occurrences));

        $task = Task::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'repeat_type' => $data['repeat_type'],
            'repeat_days' => $data['repeat_days'] ?? null,
            'reminder_before_minutes' => $data['reminder_before_minutes'] ?? 0,
            'occurrences' => $occurrences,
            'capture_photo' => (bool)($data['capture_photo'] ?? false),
            'notify_once_done' => (bool)($data['notify_once_done'] ?? false),
            'overdue_alarm_enabled' => (bool)($data['overdue_alarm_enabled'] ?? false),
            'icon_name' => $data['icon_name'] ?? null,
            'active' => (bool)($data['active'] ?? true),
        ]);

        // Store time slots only when present. If empty => "Anytime" task.
        foreach ($minutes as $i => $m) {
            TaskTime::create(['task_id' => $task->id, 'occurrence_minutes' => (int) $m, 'sort_order' => (int) $i]);
        }

        $task->users()->sync($data['assigned_user_ids']);

        return Task::query()->with(['times', 'users'])->findOrFail($task->id);
    }

    public function show(Task $task)
    {
        return $task->load(['times', 'users']);
    }

    public function update(Request $request, Task $task)
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'repeat_type' => ['sometimes', 'in:weekdays,weekends,everyday,once'],
            'repeat_days' => ['nullable', 'array'],
            'repeat_days.*' => ['in:Mon,Tue,Wed,Thu,Fri,Sat,Sun'],
            'reminder_before_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'occurrences' => ['sometimes', 'integer', 'min:1', 'max:12'],
            // Allow empty times_minutes to represent "no ETA" (Anytime).
            'times_minutes' => ['sometimes', 'nullable', 'array'],
            'times_minutes.*' => ['integer', 'min:0', 'max:1439'],
            'assigned_user_ids' => ['sometimes', 'array', 'min:1'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
            'capture_photo' => ['sometimes', 'boolean'],
            'notify_once_done' => ['sometimes', 'boolean'],
            'overdue_alarm_enabled' => ['sometimes', 'boolean'],
            'icon_name' => ['nullable', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $task->fill($data);
        $task->save();

        if (isset($data['times_minutes'])) {
            $task->times()->delete();
            $minutes = array_values(array_unique(array_map('intval', $data['times_minutes'] ?? [])));
            $minutes = array_values(array_filter($minutes, fn ($m) => is_int($m) && $m >= 0 && $m <= 1439));
            sort($minutes);
            foreach ($minutes as $i => $m) {
                TaskTime::create(['task_id' => $task->id, 'occurrence_minutes' => (int) $m, 'sort_order' => (int) $i]);
            }

            // Keep occurrences aligned with ETA presence unless admin explicitly set it.
            if (!isset($data['occurrences'])) {
                $task->occurrences = count($minutes) > 0 ? count($minutes) : 1;
                $task->save();
            }
        }

        if (isset($data['assigned_user_ids'])) {
            $task->users()->sync($data['assigned_user_ids']);
        }

        return $task->load(['times', 'users']);
    }

    public function destroy(Task $task)
    {
        $task->delete();
        return response()->json(['ok' => true]);
    }
}

