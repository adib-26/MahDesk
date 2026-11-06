<?php

use App\Http\Controllers\Api\AccessTokenController;
use App\Http\Controllers\Api\InboundEmailController;
use App\Http\Controllers\Api\V1;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API v1  (/api/v1)
|--------------------------------------------------------------------------
| Authenticate with "Authorization: Bearer <token>" from POST /api/v1/auth/token.
| Organization data lives under /api/v1/organizations/{slug}/...; child records
| are looked up inside that organization only (scoped bindings), and tickets
| are addressed by their per-organization number (#42 -> /tickets/42).
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/token', [AccessTokenController::class, 'store'])
        ->middleware('throttle:api-auth')
        ->name('auth.token.store');

    Route::post('inbound/email/postmark', [InboundEmailController::class, 'postmark'])
        ->middleware('throttle:120,1')
        ->name('inbound-email.postmark');

    // Kept for clients built against the original token endpoints.
    Route::post('tokens', [AccessTokenController::class, 'store'])->middleware('throttle:api-auth')->name('legacy.tokens.store');

    Route::middleware(['auth:sanctum', 'verified', 'abilities:api:access', 'throttle:api'])->group(function () {
        Route::get('auth/me', [V1\AuthController::class, 'me'])->name('auth.me');
        Route::delete('auth/token', [AccessTokenController::class, 'destroy'])->name('auth.token.destroy');

        // Legacy aliases.
        Route::delete('tokens/current', [AccessTokenController::class, 'destroy'])->name('legacy.tokens.destroy');
        Route::get('user', function (Request $request) {
            $user = $request->user();

            return response()->json([
                'user' => $user->only(['id', 'name', 'email', 'email_verified_at']),
                'workspaces' => $user->workspaces()->orderBy('name')
                    ->get(['workspaces.id', 'workspaces.name', 'workspaces.slug'])
                    ->map(fn ($w) => ['id' => $w->id, 'name' => $w->name, 'slug' => $w->slug, 'role' => $w->pivot->role]),
            ]);
        })->name('legacy.user');

        Route::controller(V1\NotificationController::class)->prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('read-all', 'markAllRead')->name('read-all');
            Route::patch('{notification}/read', 'markRead')->whereUuid('notification')->name('read');
            Route::delete('{notification}', 'destroy')->whereUuid('notification')->name('destroy');
        });

        Route::get('organizations', [V1\OrganizationController::class, 'index'])->name('organizations.index');

        Route::prefix('organizations/{organization:slug}')
            ->middleware('api.organization')
            ->scopeBindings()
            ->group(function () {
                Route::get('/', [V1\OrganizationController::class, 'show'])->name('organizations.show');
                Route::patch('/', [V1\OrganizationController::class, 'update'])->name('organizations.update');

                // Customers and tickets are also open to customer accounts; policies limit them to their own records.
                Route::apiResource('customers', V1\CustomerController::class)
                    ->parameters(['customers' => 'contact']);

                Route::get('tickets/{ticket:number}', [V1\TicketController::class, 'show'])->name('tickets.show');
                Route::patch('tickets/{ticket:number}', [V1\TicketController::class, 'update'])->name('tickets.update');
                Route::delete('tickets/{ticket:number}', [V1\TicketController::class, 'destroy'])->name('tickets.destroy');
                Route::get('tickets', [V1\TicketController::class, 'index'])->name('tickets.index');
                Route::post('tickets', [V1\TicketController::class, 'store'])->middleware('throttle:ticket-writes')->name('tickets.store');
                Route::get('tickets/{ticket:number}/messages', [V1\TicketMessageController::class, 'index'])->name('tickets.messages.index');
                Route::post('tickets/{ticket:number}/messages', [V1\TicketMessageController::class, 'store'])->middleware('throttle:ticket-writes')->name('tickets.messages.store');
                Route::get('tickets/{ticket:number}/attachments/{attachment:uuid}', [V1\TicketMessageController::class, 'attachment'])
                    ->middleware('throttle:downloads')
                    ->name('tickets.attachments.show');

                Route::middleware('api.organization:staff')->group(function () {
                    Route::get('agents', [V1\AgentController::class, 'index'])->name('agents.index');
                    Route::get('agents/{member}', [V1\AgentController::class, 'show'])->name('agents.show');

                    Route::apiResource('teams', V1\TeamController::class);
                    Route::post('teams/{team}/members', [V1\TeamController::class, 'addMember'])->name('teams.members.store');
                    Route::delete('teams/{team}/members/{member}', [V1\TeamController::class, 'removeMember'])->name('teams.members.destroy');

                    Route::apiResource('sla', V1\SlaPolicyController::class)->parameters(['sla' => 'slaPolicy']);

                    Route::prefix('knowledge-base')->name('knowledge-base.')->group(function () {
                        Route::apiResource('categories', V1\KbCategoryController::class)->parameters(['categories' => 'kbCategory']);
                        Route::apiResource('articles', V1\KbArticleController::class)->parameters(['articles' => 'kbArticle']);
                    });

                    Route::get('reports/overview', [V1\ReportController::class, 'overview'])->name('reports.overview');
                    Route::get('reports/agents', [V1\ReportController::class, 'agents'])->name('reports.agents');
                });
            });
    });
});
