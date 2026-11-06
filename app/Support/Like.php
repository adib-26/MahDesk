<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

/**
 * Safe "contains" search for LIKE queries.
 *
 * Search terms are always sent as bound parameters, so they cannot change the
 * SQL. This class also escapes the LIKE wildcards (% and _) so a user typing
 * "50%" searches for that text instead of "50 followed by anything", and
 * "%" or "____" can't be used to match every row.
 *
 * "!" is used as the escape character because, unlike backslash, it means the
 * same thing in SQLite, MySQL and PostgreSQL string literals.
 */
final class Like
{
    public const ESCAPE_CHAR = '!';

    public static function escape(string $value): string
    {
        return str_replace(
            [self::ESCAPE_CHAR, '%', '_'],
            [self::ESCAPE_CHAR.self::ESCAPE_CHAR, self::ESCAPE_CHAR.'%', self::ESCAPE_CHAR.'_'],
            $value,
        );
    }

    public static function containsPattern(string $value): string
    {
        return '%'.self::escape($value).'%';
    }

    /**
     * Register whereContains() / orWhereContains() on the query builder.
     *
     *   $query->whereContains(['name', 'email'], $search)
     *
     * matches rows where any of the columns contains $search.
     */
    public static function registerMacros(): void
    {
        $macro = function (string $boolean) {
            return function (string|array $columns, mixed $value) use ($boolean) {
                /** @var Builder $this */
                // Ignore empty and non-string input (e.g. ?q[]=x) instead of erroring.
                if (! is_string($value) || $value === '') {
                    return $this;
                }

                $pattern = Like::containsPattern($value);

                return $this->where(function (Builder $query) use ($columns, $pattern) {
                    foreach ((array) $columns as $column) {
                        // Column names come from code, never from the request, and are quoted by the grammar.
                        $query->orWhereRaw(
                            $query->getGrammar()->wrap($column)." like ? escape '".Like::ESCAPE_CHAR."'",
                            [$pattern],
                        );
                    }
                }, null, null, $boolean);
            };
        };

        Builder::macro('whereContains', $macro('and'));
        Builder::macro('orWhereContains', $macro('or'));
    }
}
