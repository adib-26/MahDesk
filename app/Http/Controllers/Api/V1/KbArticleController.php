<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexKbArticlesRequest;
use App\Http\Requests\Api\V1\SaveKbArticleRequest;
use App\Http\Resources\V1\KbArticleResource;
use App\Models\KbArticle;
use App\Models\Workspace;
use App\Support\UniqueSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class KbArticleController extends Controller
{
    private const RELATIONS = ['category:id,name', 'author:id,name'];

    public function index(IndexKbArticlesRequest $request, Workspace $organization): AnonymousResourceCollection
    {
        $query = $organization->kbArticles()
            ->with(self::RELATIONS)
            ->when($request->filter('category_id'), fn ($q, $id) => $q->where('kb_category_id', $id))
            ->when($request->filter('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->filter('author_id'), fn ($q, $id) => $q->where('author_id', $id))
            ->whereContains(['title', 'excerpt'], $request->filter('q'));

        return KbArticleResource::collection(
            $request->applySort($query)->paginate($request->perPage())->withQueryString(),
        );
    }

    public function store(SaveKbArticleRequest $request, Workspace $organization): JsonResponse
    {
        $validated = $request->validated();
        $status = $validated['status'] ?? 'draft';

        $article = $organization->kbArticles()->create([
            'kb_category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'excerpt' => $validated['excerpt'] ?? null,
            'body' => $validated['body'],
            'status' => $status,
            'author_id' => $request->user()->id,
            'slug' => UniqueSlug::within($organization->kbArticles(), $validated['title'], 'article'),
            'published_at' => $status === 'published' ? now() : null,
        ]);

        return KbArticleResource::make($article->load(self::RELATIONS))->response()->setStatusCode(201);
    }

    public function show(Workspace $organization, KbArticle $kbArticle): KbArticleResource
    {
        return KbArticleResource::make($kbArticle->load(self::RELATIONS));
    }

    public function update(SaveKbArticleRequest $request, Workspace $organization, KbArticle $kbArticle): KbArticleResource
    {
        $validated = $request->validated();

        if (array_key_exists('category_id', $validated)) {
            $validated['kb_category_id'] = $validated['category_id'];
            unset($validated['category_id']);
        }

        if (($validated['status'] ?? null) === 'published' && ! $kbArticle->published_at) {
            $validated['published_at'] = now();
        } elseif (($validated['status'] ?? null) === 'draft') {
            $validated['published_at'] = null;
        }

        $kbArticle->update($validated);

        return KbArticleResource::make($kbArticle->load(self::RELATIONS));
    }

    public function destroy(Workspace $organization, KbArticle $kbArticle): JsonResponse
    {
        Gate::authorize('manageKnowledgeBase', $organization);

        $kbArticle->delete();

        return response()->json(null, 204);
    }
}
