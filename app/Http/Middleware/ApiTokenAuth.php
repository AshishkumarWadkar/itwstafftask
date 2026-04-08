<?php

namespace App\Http\Middleware;

use App\Models\UserToken;
use Closure;
use Illuminate\Http\Request;

class ApiTokenAuth
{
    /**
     * Authenticate using Bearer token stored as sha256 hash.
     */
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization', '');
        $token = null;

        if (str_starts_with($header, 'Bearer ')) {
            $token = trim(substr($header, 7));
        }

        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $hash = hash('sha256', $token);
        $record = UserToken::query()
            ->with('user')
            ->where('token_hash', $hash)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$record || !$record->user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->setUserResolver(fn () => $record->user);

        return $next($request);
    }
}

