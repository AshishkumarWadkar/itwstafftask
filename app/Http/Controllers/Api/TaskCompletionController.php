<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskLog;
use Illuminate\Http\Request;

class TaskCompletionController extends Controller
{
    public function complete(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'day_key' => ['required', 'integer'],
            'occurrence_minutes' => ['required', 'integer', 'min:0', 'max:1439'],
        ]);

        $log = TaskLog::query()->updateOrCreate(
            [
                'task_id' => $data['task_id'],
                'user_id' => $user->id,
                'day_key' => $data['day_key'],
                'occurrence_minutes' => $data['occurrence_minutes'],
            ],
            [
                'completed' => true,
                'completed_at' => now(),
            ]
        );

        return response()->json(['ok' => true, 'log' => $log]);
    }

    public function uncomplete(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'day_key' => ['required', 'integer'],
            'occurrence_minutes' => ['required', 'integer', 'min:0', 'max:1439'],
        ]);

        $log = TaskLog::query()->updateOrCreate(
            [
                'task_id' => $data['task_id'],
                'user_id' => $user->id,
                'day_key' => $data['day_key'],
                'occurrence_minutes' => $data['occurrence_minutes'],
            ],
            [
                'completed' => false,
                'completed_at' => null,
                'proof_path' => null,
            ]
        );

        return response()->json(['ok' => true, 'log' => $log]);
    }

    public function uploadProof(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'day_key' => ['required', 'integer'],
            'occurrence_minutes' => ['required', 'integer', 'min:0', 'max:1439'],
            'proof' => ['required', 'file', 'max:5120'], // 5MB
        ]);

        $path = $request->file('proof')->store('proofs', 'public');

        $log = TaskLog::query()->updateOrCreate(
            [
                'task_id' => $data['task_id'],
                'user_id' => $user->id,
                'day_key' => $data['day_key'],
                'occurrence_minutes' => $data['occurrence_minutes'],
            ],
            [
                'completed' => true,
                'completed_at' => now(),
                'proof_path' => $path,
            ]
        );

        return response()->json(['ok' => true, 'proof_path' => $path, 'log' => $log]);
    }
}

