<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChangeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->force_password_change) {
            // Allow the password change route itself and logout
            if ($request->is('password/change', 'logout', 'livewire/*')) {
                return $next($request);
            }

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Password change required. Please update your password before continuing.',
                    'error_code' => 'FORCE_PASSWORD_CHANGE',
                ], 403);
            }

            return redirect()->route('password.force-change');
        }

        return $next($request);
    }
}
