<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $isApi = $request->is('api/*');

        if (!$request->user()) {
            if ($isApi || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Authentication required.',
                    'error_code' => 'UNAUTHENTICATED',
                ], 401);
            }

            return redirect()->guest(route('login'));
        }

        $userRole = $request->user()->role;

        if (!in_array($userRole, $roles, true)) {
            if ($isApi || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Access denied.',
                    'error_code' => 'FORBIDDEN',
                ], 403);
            }

            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
