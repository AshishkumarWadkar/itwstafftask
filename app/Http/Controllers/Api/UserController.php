<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        return User::query()
            ->orderBy('id', 'desc')
            ->get(['id', 'name', 'role', 'phone', 'created_at']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['sometimes', 'in:admin,user'],
            'phone' => ['required', 'string', 'min:8', 'max:20', 'unique:users,phone'],
            'pin' => ['required', 'string', 'size:4'],
        ]);

        $phone = preg_replace('/[^\d+]/', '', $data['phone']);

        $user = User::create([
            'name' => $data['name'],
            'role' => $data['role'] ?? 'user',
            'phone' => $phone,
            'pin_hash' => Hash::make($data['pin']),
        ]);

        return response()->json($user->only(['id', 'name', 'role', 'phone', 'created_at']), 201);
    }

    public function show(User $user)
    {
        return $user->only(['id', 'name', 'role', 'phone', 'created_at']);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'in:admin,user'],
            'phone' => ['sometimes', 'string', 'min:8', 'max:20', 'unique:users,phone,'.$user->id],
            'pin' => ['sometimes', 'string', 'size:4'],
        ]);

        if (isset($data['phone'])) {
            $data['phone'] = preg_replace('/[^\d+]/', '', $data['phone']);
        }
        if (isset($data['pin'])) {
            $data['pin_hash'] = Hash::make($data['pin']);
            unset($data['pin']);
        }

        $user->fill($data)->save();

        return $user->only(['id', 'name', 'role', 'phone', 'created_at']);
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['ok' => true]);
    }
}

