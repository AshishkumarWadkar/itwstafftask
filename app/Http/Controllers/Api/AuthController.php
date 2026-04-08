<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'pin' => ['required', 'string', 'size:4'],
            'device_name' => ['sometimes', 'string', 'max:50'],
        ]);

        $phone = preg_replace('/[^\d+]/', '', $data['phone']);
        $user = User::query()->where('phone', $phone)->first();

        if (!$user || !$user->pin_hash || !Hash::check($data['pin'], $user->pin_hash)) {
            return response()->json(['message' => 'Invalid phone or PIN'], 422);
        }

        $plain = Str::random(40);
        $token = UserToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'name' => $data['device_name'] ?? 'mobile',
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return response()->json([
            'token' => $plain,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'phone' => $user->phone,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'phone' => $user->phone,
        ]);
    }

    public function logout(Request $request)
    {
        $header = $request->header('Authorization', '');
        $token = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : null;
        if ($token) {
            UserToken::query()->where('token_hash', hash('sha256', $token))->delete();
        }
        return response()->json(['ok' => true]);
    }
}

