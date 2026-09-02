<?php

use App\Http\Controllers\Api\AccessTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('tokens', [AccessTokenController::class, 'store'])
        ->middleware('throttle:6,1');

    Route::middleware(['auth:sanctum', 'verified', 'abilities:api:access'])->group(function () {
        Route::get('user', function (Request $request) {
            $user = $request->user();

            return response()->json([
                'user' => $user->only(['id', 'name', 'email', 'email_verified_at']),
                'workspaces' => $user->workspaces()
                    ->orderBy('name')
                    ->get(['workspaces.id', 'workspaces.name', 'workspaces.slug'])
                    ->map(fn ($workspace) => [
                        'id' => $workspace->id,
                        'name' => $workspace->name,
                        'slug' => $workspace->slug,
                        'role' => $workspace->pivot->role,
                    ]),
            ]);
        });

        Route::delete('tokens/current', [AccessTokenController::class, 'destroy']);
    });
});
