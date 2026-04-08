<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminDayReportController extends Controller
{
    private function weekdayKeyFromDayKeyMs(int $dayKeyMs): string
    {
        $dow = Carbon::createFromTimestampMs($dayKeyMs)->dayOfWeek; // 0=Sun..6=Sat
        return match ($dow) {
            0 => 'Sun',
            1 => 'Mon',
            2 => 'Tue',
            3 => 'Wed',
            4 => 'Thu',
            5 => 'Fri',
            default => 'Sat',
        };
    }

    private function isTaskDueOnDay(Task $task, int $dayKeyMs): bool
    {
        $wk = $this->weekdayKeyFromDayKeyMs($dayKeyMs);
        $repeatDays = is_array($task->repeat_days) ? $task->repeat_days : null;
        if ($repeatDays && count($repeatDays) > 0) {
            return in_array($wk, $repeatDays, true);
        }

        $type = (string) ($task->repeat_type ?? 'weekdays');
        if ($type === 'everyday') return true;
        if ($type === 'weekdays') return in_array($wk, ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'], true);
        if ($type === 'weekends') return in_array($wk, ['Sat', 'Sun'], true);
        if ($type === 'once') {
            $createdDayKey = Carbon::parse($task->created_at)->startOfDay()->getTimestampMs();
            return (int) $createdDayKey === (int) $dayKeyMs;
        }
        return true;
    }

    /**
     * Day-wise report for admins:
     * - For each active task occurrence, show assigned users and their completion.
     * - Includes overall totals (total/completed/pending).
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        if (!$actor || ($actor->role ?? null) !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'day_key' => ['nullable', 'integer'],
        ]);

        $dayKey = $data['day_key'] ?? now()->startOfDay()->getTimestampMs();
        $dayEnd = (int) $dayKey + (24 * 60 * 60 * 1000) - 1;

        $tasks = Task::query()
            ->where('active', true)
            ->where('created_at', '<=', Carbon::createFromTimestampMs($dayEnd))
            ->with([
                'times:id,task_id,occurrence_minutes,sort_order',
                'users:id,name,role',
            ])
            ->orderBy('id', 'desc')
            ->get();

        $taskIds = [];
        $userIds = [];

        foreach ($tasks as $task) {
            $taskIds[] = (int) $task->id;
            foreach ($task->users as $u) {
                if (($u->role ?? null) === 'user') $userIds[] = (int) $u->id;
            }
        }

        $taskIds = array_values(array_unique($taskIds));
        $userIds = array_values(array_unique($userIds));

        $users = User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'role']);

        $logs = TaskLog::query()
            ->where('day_key', $dayKey)
            ->when(count($taskIds) > 0, fn ($q) => $q->whereIn('task_id', $taskIds))
            ->when(count($userIds) > 0, fn ($q) => $q->whereIn('user_id', $userIds))
            ->get(['task_id', 'user_id', 'day_key', 'occurrence_minutes', 'completed', 'completed_at', 'proof_path']);

        $logByKey = [];
        foreach ($logs as $log) {
            $k = ((int) $log->task_id) . ':' . ((int) $log->user_id) . ':' . ((int) $log->occurrence_minutes);
            $logByKey[$k] = $log;
        }

        $resultTasks = [];
        $total = 0;
        $completed = 0;

        foreach ($tasks as $task) {
            $assigned = $task->users->filter(fn ($u) => ($u->role ?? null) === 'user')->values();
            if ($assigned->count() === 0) continue;
            if (!$this->isTaskDueOnDay($task, (int) $dayKey)) continue;

            $times = $task->times->sortBy('occurrence_minutes')->values();
            $minutesList = $times->count() ? $times->pluck('occurrence_minutes')->all() : [0];

            foreach ($minutesList as $m) {
                $usersArr = [];
                foreach ($assigned as $u) {
                    $key = ((int) $task->id) . ':' . ((int) $u->id) . ':' . ((int) $m);
                    /** @var TaskLog|null $log */
                    $log = $logByKey[$key] ?? null;

                    $isCompleted = (bool) optional($log)->completed;
                    $total++;
                    if ($isCompleted) $completed++;

                    $usersArr[] = [
                        'id' => (int) $u->id,
                        'name' => (string) $u->name,
                        'completed' => $isCompleted,
                        'completed_at' => optional($log)->completed_at,
                        'proof_path' => optional($log)->proof_path,
                    ];
                }

                $resultTasks[] = [
                    'task_id' => (int) $task->id,
                    'title' => (string) $task->title,
                    'description' => (string) ($task->description ?? ''),
                    'icon_name' => $task->icon_name,
                    'capture_photo' => (bool) $task->capture_photo,
                    'notify_once_done' => (bool) $task->notify_once_done,
                    'overdue_alarm_enabled' => (bool) $task->overdue_alarm_enabled,
                    'repeat_type' => (string) $task->repeat_type,
                    'repeat_days' => $task->repeat_days,
                    'reminder_before_minutes' => (int) ($task->reminder_before_minutes ?? 0),
                    'day_key' => (int) $dayKey,
                    'occurrence_minutes' => (int) $m,
                    'users' => $usersArr,
                ];
            }
        }

        // Sort: pending first (based on completion ratio), then by time.
        usort($resultTasks, function ($a, $b) {
            $aUsers = $a['users'] ?? [];
            $bUsers = $b['users'] ?? [];
            $aDone = count(array_filter($aUsers, fn ($u) => (bool) ($u['completed'] ?? false)));
            $bDone = count(array_filter($bUsers, fn ($u) => (bool) ($u['completed'] ?? false)));
            $aTotal = max(1, count($aUsers));
            $bTotal = max(1, count($bUsers));
            $aRatio = $aDone / $aTotal;
            $bRatio = $bDone / $bTotal;
            if ($aRatio !== $bRatio) return $aRatio <=> $bRatio;
            return ((int) ($a['occurrence_minutes'] ?? 0)) <=> ((int) ($b['occurrence_minutes'] ?? 0));
        });

        return response()->json([
            'day_key' => (int) $dayKey,
            'totals' => [
                'total' => (int) $total,
                'completed' => (int) $completed,
                'pending' => (int) max(0, $total - $completed),
            ],
            'users' => $users->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name])->values(),
            'tasks' => $resultTasks,
        ]);
    }
}

