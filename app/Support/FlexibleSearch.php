<?php

namespace App\Support;

use Closure;

class FlexibleSearch
{
    public static function apply($query, ?string $search, Closure $callback): void
    {
        $phrase = trim((string) $search);

        if ($phrase === '') {
            return;
        }

        $terms = self::terms($phrase);

        $query->where(function ($nestedQuery) use ($callback, $phrase, $terms) {
            $callback($nestedQuery, $phrase, $terms);
        });
    }

    public static function terms(string $phrase): array
    {
        return collect(preg_split('/\s+/u', mb_strtolower(trim($phrase))) ?: [])
            ->map(fn ($term) => trim($term))
            ->filter(fn ($term) => $term !== '' && (mb_strlen($term) >= 2 || preg_match('/^\d+$/', $term)))
            ->unique()
            ->values()
            ->all();
    }

    public static function orWhereColumns($query, array $columns, string $phrase, array $terms = []): void
    {
        foreach ($columns as $column) {
            $query->orWhere(function ($columnQuery) use ($column, $phrase, $terms) {
                self::applyColumnMatchers($columnQuery, $column, $phrase, $terms);
            });
        }
    }

    public static function orWhereHasColumns($query, string $relation, array $columns, string $phrase, array $terms = []): void
    {
        $query->orWhereHas($relation, function ($relationQuery) use ($columns, $phrase, $terms) {
            $relationQuery->where(function ($columnQuery) use ($columns, $phrase, $terms) {
                foreach ($columns as $column) {
                    $columnQuery->orWhere(function ($nestedColumnQuery) use ($column, $phrase, $terms) {
                        self::applyColumnMatchers($nestedColumnQuery, $column, $phrase, $terms);
                    });
                }
            });
        });
    }

    public static function orWhereExists($query, Closure $callback): void
    {
        $query->orWhereExists($callback);
    }

    private static function applyColumnMatchers($query, string $column, string $phrase, array $terms): void
    {
        $query->where($column, $phrase)
            ->orWhere($column, 'like', "%{$phrase}%");

        foreach ($terms as $term) {
            if (mb_strtolower($term) === mb_strtolower($phrase)) {
                continue;
            }

            $query->orWhere($column, $term)
                ->orWhere($column, 'like', "%{$term}%");
        }
    }
}
