<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HelpCenterController extends Controller
{
    public function index(Request $request, Workspace $workspace): Response
    {
        $q = $request->query('q');

        $categories = $workspace->kbCategories()
            ->orderBy('position')
            ->with(['articles' => fn ($query) => $query
                ->where('status', 'published')
                ->when($q, fn ($query, $search) => $query->where(fn ($w) => $w
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")))
                ->orderByDesc('views')
                ->select(['id', 'kb_category_id', 'title', 'slug', 'excerpt', 'views', 'updated_at'])])
            ->get()
            ->filter(fn ($category) => $category->articles->isNotEmpty())
            ->values();

        return Inertia::render('help/index', [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'categories' => $categories,
            'filters' => ['q' => $q],
        ]);
    }

    public function show(Workspace $workspace, string $slug): Response
    {
        $article = $workspace->kbArticles()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with('category:id,name,slug')
            ->firstOrFail();

        $article->increment('views');

        $related = $workspace->kbArticles()
            ->where('kb_category_id', $article->kb_category_id)
            ->where('id', '!=', $article->id)
            ->where('status', 'published')
            ->orderByDesc('views')
            ->limit(4)
            ->get(['id', 'title', 'slug', 'excerpt']);

        return Inertia::render('help/article', [
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'article' => $article,
            'related' => $related,
        ]);
    }
}
