<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexKbCategoriesRequest;
use App\Http\Requests\Api\V1\SaveKbCategoryRequest;
use App\Http\Resources\V1\KbCategoryResource;
use App\Models\KbCategory;
use App\Models\Workspace;
use App\Support\UniqueSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class KbCategoryController extends Controller
{
    public function index(IndexKbCategoriesRequest $request, Workspace $organization): AnonymousResourceCollection
    {
        $query = $organization->kbCategories()
            ->withCount('articles')
            ->whereContains('name', $request->filter('q'));

        return KbCategoryResource::collection(
            $request->applySort($query)->paginate($request->perPage())->withQueryString(),
        );
    }

    public function store(SaveKbCategoryRequest $request, Workspace $organization): JsonResponse
    {
        $validated = $request->validated();

        $category = $organization->kbCategories()->create([
            ...$validated,
            'slug' => UniqueSlug::within($organization->kbCategories(), $validated['name'], 'category'),
            'position' => $validated['position'] ?? (int) $organization->kbCategories()->max('position') + 1,
        ]);

        return KbCategoryResource::make($category->loadCount('articles'))->response()->setStatusCode(201);
    }

    public function show(Workspace $organization, KbCategory $kbCategory): KbCategoryResource
    {
        return KbCategoryResource::make($kbCategory->loadCount('articles'));
    }

    /** The slug is kept on rename so existing help-center links keep working. */
    public function update(SaveKbCategoryRequest $request, Workspace $organization, KbCategory $kbCategory): KbCategoryResource
    {
        $kbCategory->update($request->validated());

        return KbCategoryResource::make($kbCategory->loadCount('articles'));
    }

    /** Deleting a category also deletes its articles. */
    public function destroy(Workspace $organization, KbCategory $kbCategory): JsonResponse
    {
        Gate::authorize('manageKnowledgeBase', $organization);

        $kbCategory->delete();

        return response()->json(null, 204);
    }
}
