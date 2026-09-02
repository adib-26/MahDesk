<?php

use App\Http\Controllers\AutomationRuleController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\KbArticleController;
use App\Http\Controllers\KbCategoryController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\SlaPolicyController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketMessageController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : Inertia::render('welcome');
})->name('home');

// Public help center (no auth required).
Route::get('help/{workspace:slug}', [HelpCenterController::class, 'index'])->name('help.index');
Route::get('help/{workspace:slug}/articles/{slug}', [HelpCenterController::class, 'show'])->name('help.article');

Route::middleware(['auth', 'verified'])->group(function () {
    // Entry point after login: forward to the user's first workspace or onboarding.
    Route::get('dashboard', function (Request $request) {
        $workspace = $request->user()->workspaces()->first();

        return $workspace
            ? redirect()->route('desk.dashboard', $workspace)
            : redirect()->route('workspaces.create');
    })->name('dashboard');

    Route::get('workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');

    Route::prefix('w/{workspace:slug}')->middleware('workspace')->name('desk.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Tickets
        Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::patch('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
        Route::delete('tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
        Route::post('tickets/{ticket}/messages', [TicketMessageController::class, 'store'])->name('tickets.messages.store');

        // Customers
        Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index');
        Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store');
        Route::get('contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
        Route::patch('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
        Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

        // Tags
        Route::post('tags', [TagController::class, 'store'])->name('tags.store');
        Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

        // Knowledge base
        Route::get('kb', [KbArticleController::class, 'index'])->name('kb.index');
        Route::get('kb/articles/create', [KbArticleController::class, 'create'])->name('kb.create');
        Route::post('kb/articles', [KbArticleController::class, 'store'])->name('kb.store');
        Route::get('kb/articles/{article}/edit', [KbArticleController::class, 'edit'])->name('kb.edit');
        Route::patch('kb/articles/{article}', [KbArticleController::class, 'update'])->name('kb.update');
        Route::delete('kb/articles/{article}', [KbArticleController::class, 'destroy'])->name('kb.destroy');
        Route::post('kb/categories', [KbCategoryController::class, 'store'])->name('kb.categories.store');
        Route::patch('kb/categories/{category}', [KbCategoryController::class, 'update'])->name('kb.categories.update');
        Route::delete('kb/categories/{category}', [KbCategoryController::class, 'destroy'])->name('kb.categories.destroy');

        // Workspace settings
        Route::get('settings', [WorkspaceController::class, 'edit'])->name('settings.general');
        Route::patch('settings', [WorkspaceController::class, 'update'])->name('settings.update');
        Route::delete('settings', [WorkspaceController::class, 'destroy'])->name('settings.destroy');

        Route::get('settings/members', [MemberController::class, 'index'])->name('members.index');
        Route::post('settings/members', [MemberController::class, 'store'])->name('members.store');
        Route::patch('settings/members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('settings/members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

        Route::get('settings/sla', [SlaPolicyController::class, 'index'])->name('sla.index');
        Route::post('settings/sla', [SlaPolicyController::class, 'store'])->name('sla.store');
        Route::patch('settings/sla/{slaPolicy}', [SlaPolicyController::class, 'update'])->name('sla.update');
        Route::delete('settings/sla/{slaPolicy}', [SlaPolicyController::class, 'destroy'])->name('sla.destroy');

        Route::get('settings/automations', [AutomationRuleController::class, 'index'])->name('automations.index');
        Route::post('settings/automations', [AutomationRuleController::class, 'store'])->name('automations.store');
        Route::patch('settings/automations/{rule}', [AutomationRuleController::class, 'update'])->name('automations.update');
        Route::delete('settings/automations/{rule}', [AutomationRuleController::class, 'destroy'])->name('automations.destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
