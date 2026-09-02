<?php

namespace App\Http\Controllers;

use App\Models\KbCategory;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KbCategoryController extends Controller
{
    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $workspace->kbCategories()->create([
            ...$validated,
            'slug' => $this->uniqueSlug($workspace, $validated['name']),
            'position' => (int) $workspace->kbCategories()->max('position') + 1,
        ]);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, Workspace $workspace, KbCategory $category): RedirectResponse
    {
        abort_unless($category->workspace_id === $workspace->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $category->update($validated);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Workspace $workspace, KbCategory $category): RedirectResponse
    {
        abort_unless($category->workspace_id === $workspace->id, 404);

        $category->delete();

        return back()->with('success', 'Category and its articles deleted.');
    }

    private function uniqueSlug(Workspace $workspace, string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;

        while ($workspace->kbCategories()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
