<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('manageTags', $workspace);
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:40',
                Rule::unique('tags', 'name')->where('workspace_id', $workspace->id),
            ],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $workspace->tags()->create([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#6366f1',
        ]);

        return back()->with('success', 'Tag created.');
    }

    public function destroy(Workspace $workspace, Tag $tag): RedirectResponse
    {
        abort_unless($tag->workspace_id === $workspace->id, 404);
        Gate::authorize('manageTags', $workspace);

        $tag->delete();

        return back()->with('success', 'Tag deleted.');
    }
}
