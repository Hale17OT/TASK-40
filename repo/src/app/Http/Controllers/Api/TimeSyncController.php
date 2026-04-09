<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TimeSyncController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'server_time' => time(),
            'server_time_iso' => now()->toIso8601String(),
            'timezone' => config('harborbite.timezone'),
        ]);
    }
}
