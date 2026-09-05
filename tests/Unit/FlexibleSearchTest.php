<?php

namespace Tests\Unit;

use App\Support\FlexibleSearch;
use Closure;
use PHPUnit\Framework\TestCase;

class FlexibleSearchTest extends TestCase
{
    public function test_all_abbreviated_terms_create_independent_required_groups(): void
    {
        $query = new FlexibleSearchQueryRecorder;

        FlexibleSearch::applyAllTerms($query, 'gall prin ave', function ($nestedQuery, $phrase, $terms) {
            FlexibleSearch::orWhereColumns($nestedQuery, ['name'], $phrase, $terms);
        });

        $this->assertCount(3, $query->groups);
        $this->assertSame(
            [['%gall%'], ['%prin%'], ['%ave%']],
            array_map(fn (FlexibleSearchQueryRecorder $group) => $group->likePatterns(), $query->groups),
        );
    }

    public function test_terms_are_normalized_and_repeated_terms_are_removed(): void
    {
        $this->assertSame(
            ['avellana', 'galleta'],
            FlexibleSearch::terms('  AVELLANA   galleta avellana '),
        );
    }
}

class FlexibleSearchQueryRecorder
{
    public array $clauses = [];

    public array $groups = [];

    public function where($column, ...$arguments): self
    {
        return $this->record('where', $column, $arguments);
    }

    public function orWhere($column, ...$arguments): self
    {
        return $this->record('orWhere', $column, $arguments);
    }

    public function likePatterns(): array
    {
        $patterns = collect($this->clauses)
            ->filter(fn (array $clause) => ($clause['arguments'][0] ?? null) === 'like')
            ->map(fn (array $clause) => $clause['arguments'][1])
            ->values()
            ->all();

        foreach ($this->groups as $group) {
            array_push($patterns, ...$group->likePatterns());
        }

        return $patterns;
    }

    private function record(string $boolean, $column, array $arguments): self
    {
        if ($column instanceof Closure) {
            $nested = new self;
            $column($nested);
            $this->groups[] = $nested;

            return $this;
        }

        $this->clauses[] = [
            'boolean' => $boolean,
            'column' => $column,
            'arguments' => $arguments,
        ];

        return $this;
    }
}
