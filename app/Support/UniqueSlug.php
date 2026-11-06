<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class UniqueSlug
{
    /**
     * Build a slug from $text that is unused within $scope, adding -2, -3, ...
     * when needed. Matches the slugs the desk UI generates.
     */
    public static function within(HasMany $scope, string $text, string $fallback, int $limit = 80): string
    {
        $base = Str::slug(Str::limit($text, $limit, '')) ?: $fallback;
        $slug = $base;
        $suffix = 2;

        while ((clone $scope)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
