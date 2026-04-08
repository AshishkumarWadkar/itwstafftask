<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Clear existing data for a fresh demo setup.
        \App\Models\UserToken::query()->delete();
        \App\Models\TaskLog::query()->delete();
        \App\Models\TaskTime::query()->delete();
        \Illuminate\Support\Facades\DB::table('task_assignments')->delete();
        \App\Models\Task::query()->delete();
        User::query()->delete();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
            'phone' => '9999999999',
            'pin_hash' => Hash::make('1111'),
        ]);

        $ravi = User::create([
            'name' => 'Ravi',
            'email' => 'ravi@example.com',
            'password' => 'password',
            'role' => 'user',
            'phone' => '8888888888',
            'pin_hash' => Hash::make('1111'),
        ]);

        $aman = User::create([
            'name' => 'Aman',
            'email' => 'aman@example.com',
            'password' => 'password',
            'role' => 'user',
            'phone' => '7777777777',
            'pin_hash' => Hash::make('1111'),
        ]);

        $task = \App\Models\Task::create([
            'title' => 'Check Water on Floor',
            'description' => 'Make sure there is no water spill.',
            'repeat_type' => 'everyday',
            'repeat_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'reminder_before_minutes' => 10,
            'occurrences' => 3,
            'capture_photo' => false,
            'notify_once_done' => true,
            'overdue_alarm_enabled' => false,
            'icon_name' => 'water',
            'active' => true,
        ]);

        $task->users()->sync([$ravi->id, $aman->id]);
        \App\Models\TaskTime::create(['task_id' => $task->id, 'occurrence_minutes' => 10 * 60, 'sort_order' => 0]);
        \App\Models\TaskTime::create(['task_id' => $task->id, 'occurrence_minutes' => 14 * 60, 'sort_order' => 1]);
        \App\Models\TaskTime::create(['task_id' => $task->id, 'occurrence_minutes' => 18 * 60, 'sort_order' => 2]);
    }
}
