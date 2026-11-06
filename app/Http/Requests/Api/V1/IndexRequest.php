<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request for list endpoints.
 *
 * Query parameters shared by every list endpoint:
 *   page=2               page number
 *   per_page=50          page size, 1-100 (default 25)
 *   sort=-created_at,name  comma-separated fields; a leading "-" sorts descending
 *
 * Subclasses declare their filters in filterRules() and the fields that may
 * be sorted on in sortable().
 */
abstract class IndexRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 25;

    public const MAX_PER_PAGE = 100;

    /**
     * Map of public sort name => column, or a closure(Builder, string $direction).
     *
     * @return array<string, string|\Closure>
     */
    abstract protected function sortable(): array;

    /** Default sort applied when the client sends none, e.g. "-created_at". */
    abstract protected function defaultSort(): string;

    /** @return array<string, mixed> */
    protected function filterRules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $fields = implode('|', array_map('preg_quote', array_keys($this->sortable())));

        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'sort' => ['sometimes', 'string', 'max:200', "regex:/^-?({$fields})(,-?({$fields}))*$/"],
            ...$this->filterRules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sort.regex' => 'Sort by one or more of: '.implode(', ', array_keys($this->sortable())).'. Prefix a field with "-" for descending order.',
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', self::DEFAULT_PER_PAGE);
    }

    /** Read a validated filter value, or null when it was not sent. */
    public function filter(string $key): mixed
    {
        $value = $this->validated($key);

        return $value === '' ? null : $value;
    }

    public function applySort(Builder $query): Builder
    {
        $sortable = $this->sortable();
        $sort = (string) $this->validated('sort', $this->defaultSort());

        foreach (array_unique(explode(',', $sort)) as $field) {
            $direction = str_starts_with($field, '-') ? 'desc' : 'asc';
            // The "sort" rule already restricts fields to sortable(); this is a second guard
            // so a request value can never reach orderBy() as a column name.
            $column = $sortable[ltrim($field, '-')] ?? abort(422, 'Invalid sort field.');

            $column instanceof \Closure
                ? $column($query, $direction)
                : $query->orderBy($column, $direction);
        }

        // A unique tiebreaker keeps pagination stable when sort values repeat.
        return $query->orderBy($query->getModel()->getQualifiedKeyName(), 'desc');
    }
}
