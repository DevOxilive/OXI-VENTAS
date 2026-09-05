<?php

namespace App\Search;

use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class ProductSearchDictionary
{
    private ?array $dictionary = null;

    public function aliasesFor(int $productId, string $category, array $barcodes, array $sourceTerms): array
    {
        $dictionary = $this->dictionary();
        $aliases = [];
        $haystack = ' '.implode(' ', $sourceTerms).' ';

        foreach ($dictionary['global'] as $canonical => $values) {
            $canonical = $this->normalize($canonical);

            if ($canonical !== '' && str_contains($haystack, " {$canonical} ")) {
                array_push($aliases, ...$this->stringValues($values));
            }
        }

        $normalizedCategory = $this->normalize($category);
        foreach ($dictionary['categories'] as $categoryName => $values) {
            if ($this->normalize($categoryName) === $normalizedCategory) {
                array_push($aliases, ...$this->stringValues($values));
            }
        }

        $productKeys = array_map('strval', [$productId, ...$barcodes]);
        foreach ($productKeys as $key) {
            if (array_key_exists($key, $dictionary['products'])) {
                array_push($aliases, ...$this->stringValues($dictionary['products'][$key]));
            }
        }

        return collect($aliases)
            ->map(fn (string $alias) => $this->normalize($alias))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function dictionary(): array
    {
        if ($this->dictionary !== null) {
            return $this->dictionary;
        }

        $path = (string) config('product_search.aliases_path');

        if (! is_file($path)) {
            throw new RuntimeException("No existe el catalogo de alias de productos: {$path}");
        }

        try {
            $contents = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('El catalogo de alias de productos contiene JSON invalido.', previous: $exception);
        }

        foreach (['global', 'categories', 'products'] as $section) {
            if (! isset($contents[$section]) || ! is_array($contents[$section])) {
                throw new RuntimeException("El catalogo de alias no contiene la seccion {$section}.");
            }
        }

        return $this->dictionary = $contents;
    }

    private function stringValues(mixed $values): array
    {
        if (is_string($values)) {
            return [$values];
        }

        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->filter(fn ($value) => is_string($value))
            ->values()
            ->all();
    }

    private function normalize(string $value): string
    {
        $value = Str::ascii(mb_strtolower($value));
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
