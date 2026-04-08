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
            'occurrences' => ['required', 'integer', 'min:1', 'max:12'],
            'times_minutes' => ['required', 'array', 'min:1'],
            'times_minutes.*' => ['integer', 'min:0', 'max:1439'],
            'assigned_user_ids' => ['required', 'array', 'min:1'],
            'assigned_user_ids.*' => ['integer', 'exists:users,id'],
            'capture_photo' => ['boolean'],
            'notify_once_done' => ['boolean'],
            'overdue_alarm_enabled' => ['boolean'],
            'icon_name' => ['nullable', 'string', 'max:100'],
            'active' => ['boolean'],
        ]);

        $task = Task::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'repeat_type' => $data['repeat_type'],
            'repeat_days' => $data['repeat_days'] ?? null,
            'reminder_before_minutes' => $data['reminder_before_minutes'] ?? 0,
            'occurrences' => $data['occurrences'],
            'capture_photo' => (bool)($data['capture_photo'] ?? false),
            'notify_once_done' => (bool)($data['notify_once_done'] ?? false),
            'overdue_alarm_enabled' => (bool)($data['overdue_alarm_enabled'] ?? false),
            'icon_name' => $data['icon_name'] ?? null,
            'active' => (bool)($data['active'] ?? true),
        ]);

        $minutes = array_values(array_unique($data['times_minutes']));
        sort($minutes);
        foreach ($minutes as $i => $m) {
            TaskTime::create(['task_id' => $task->id, 'occurrence_minutes' => $m, 'sort_order' => $i]);
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
            'times_minutes' => ['sometimes', 'array', 'min:1'],
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
            $minutes = array_values(array_unique($data['times_minutes']));
            sort($minutes);
            foreach ($minutes as $i => $m) {
                TaskTime::create(['task_id' => $task->id, 'occurrence_minutes' => $m, 'sort_order' => $i]);
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

