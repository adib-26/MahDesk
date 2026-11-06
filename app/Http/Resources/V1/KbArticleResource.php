<?php

namespace App\Http\Resources\V1;

use App\Models\KbArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin KbArticle */
class KbArticleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            // List responses leave out the body to keep pages small.
            'body' => $this->when(! $request->routeIs('api.v1.knowledge-base.articles.index'), $this->body),
            'status' => $this->status,
            'views' => (int) $this->views,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'author' => UserSummaryResource::make($this->whenLoaded('author')),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
