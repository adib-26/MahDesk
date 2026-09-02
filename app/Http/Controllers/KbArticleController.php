<?php

namespace App\Http\Controllers;

use App\Models\KbArticle;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KbArticleController extends Controller
{
    public function index(Request $request, Workspace $workspace): Response
    {
        Gate::authorize('viewKnowledgeBase', $workspace);
        $q = $request->query('q');

        $categories = $workspace->kbCategories()
            ->orderBy('position')
            ->orderBy('name')
            ->with(['articles' => fn ($query) => $query
                ->when($q, fn ($query, $search) => $query->where('title', 'like', "%{$search}%"))
                ->orderByDesc('updated_at')
                ->select(['id', 'kb_category_id', 'title', 'slug', 'excerpt', 'status', 'views', 'updated_at'])])
            ->get();

        return Inertia::render('desk/kb/index', [
            'categories' => $categories,
            'filters' => ['q' => $q],
        ]);
    }

    public function create(Workspace $workspace): Response
    {
        Gate::authorize('manageKnowledgeBase', $workspace);
        return Inertia::render('desk/kb/editor', [
            'article' => null,
            'categories' => $workspace->kbCategories()->orderBy('position')->get(['id', 'name']),
        ]);
    }

    public function edit(Workspace $workspace, KbArticle $article): Response
    {
        abort_unless($article->workspace_id === $workspace->id, 404);
        Gate::authorize('manageKnowledgeBase', $workspace);

        return Inertia::render('desk/kb/editor', [
            'article' => $article,
            'categories' => $workspace->kbCategories()->orderBy('position')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('manageKnowledgeBase', $workspace);
        $validated = $this->validated($request, $workspace);

        $article = $workspace->kbArticles()->create([
            ...$validated,
            'author_id' => $request->user()->id,
            'slug' => $this->uniqueSlug($workspace, $validated['title']),
            'published_at' => $validated['status'] === 'published' ? now() : null,
        ]);

        return redirect()
            ->route('desk.kb.edit', [$workspace, $article])
            ->with('success', 'Article created.');
    }

    public function update(Request $request, Workspace $workspace, KbArticle $article): RedirectResponse
    {
        abort_unless($article->workspace_id === $workspace->id, 404);
        Gate::authorize('manageKnowledgeBase', $workspace);

        $validated = $this->validated($request, $workspace);

        if ($validated['status'] === 'published' && ! $article->published_at) {
            $validated['published_at'] = now();
        } elseif ($validated['status'] === 'draft') {
            $validated['published_at'] = null;
        }

        $article->update($validated);

        return back()->with('success', 'Article saved.');
    }

    public function destroy(Workspace $workspace, KbArticle $article): RedirectResponse
    {
        abort_unless($article->workspace_id === $workspace->id, 404);
        Gate::authorize('manageKnowledgeBase', $workspace);

        $article->delete();

        return redirect()
            ->route('desk.kb.index', $workspace)
            ->with('success', 'Article deleted.');
    }

    private function validated(Request $request, Workspace $workspace): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'kb_category_id' => ['required', Rule::exists('kb_categories', 'id')->where('workspace_id', $workspace->id)],
            'excerpt' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);
    }

    private function uniqueSlug(Workspace $workspace, string $title): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'article';
        $slug = $base;
        $suffix = 2;

        while ($workspace->kbArticles()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
