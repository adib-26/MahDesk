<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/server-side-setup#asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'workspaces' => fn () => $this->workspacesFor($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }

    /**
     * @return list<array{id: int, name: string, slug: string, role?: string|null}>
     */
    private function workspacesFor(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            return Workspace::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (Workspace $workspace) => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug,
                    'role' => 'super_admin',
                ])
                ->all();
        }

        return $user->staffWorkspaces()
            ->orderBy('name')
            ->get(['workspaces.id', 'workspaces.name', 'workspaces.slug'])
            ->map(fn ($workspace) => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'role' => $workspace->pivot->role,
            ])
            ->all();
    }
}
