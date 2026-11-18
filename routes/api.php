<?php

use App\Services\Cricket\ReverbPresenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Lightweight heartbeat for match watchers (Rate limited, fast)
Route::match(['get', 'post'], '/matches/{matchId}/ping', function (string $matchId) {
    ReverbPresenceService::recordHeartbeat($matchId);
    return response()->json(['status' => 'ok', 'match_id' => $matchId]);
})->middleware('throttle:60,1');

Route::match(['get', 'post'], '/matches/{matchId}/heartbeat', function (string $matchId) {
    ReverbPresenceService::recordHeartbeat($matchId);
    return response()->json(['status' => 'ok', 'match_id' => $matchId]);
})->middleware('throttle:60,1');
