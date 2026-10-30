<?php

namespace App\Search;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use PDO;

/** Prefiltro conservador: la relevancia y los errores permitidos se resuelven después. */
class InventoryProductSqlPrefilter
{
    public function __construct(
        private readonly ProductSearchDocumentBuilder $documents,
        private readonly ProductSearchDictionary $dictionary,
    ) {}

    public function supportsConnection(Connection $connection): bool
    {
        $driver = $connection->getDriverName();
        if ($driver === 'sqlite') {
            return true;
        }
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        // PDO ya conoce la versión tras conectar; no se agrega una consulta por búsqueda.
        return $this->supportsVersion($driver, (string) $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION));
    }

    public function supportsVersion(string $driver, string $version): bool
    {
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return $driver === 'sqlite';
        }

        $mariaDb = $driver === 'mariadb' || stripos($version, 'mariadb') !== false;
        $version = preg_replace('/^5\.5\.5-/', '', $version) ?? $version;
        if (! preg_match('/^(\d+\.\d+\.\d+)/', $version, $match)) {
            return false;
        }

        return version_compare($match[1], $mariaDb ? '10.6.0' : '8.0.4', '>=');
    }

    public function apply(Builder $query, array $terms): void
    {
        $dictionary = $this->dictionary->dictionary();

        foreach ($terms as $term) {
            $fragments = $this->fragments($term);
            $query->where(function (Builder $termQuery) use ($fragments, $dictionary): void {
                $this->whereSourceFragments($termQuery, $fragments);
                $termQuery->orWhereHas('barcodes', fn (Builder $codes) => $this->whereFragments($codes, 'code', $fragments));
                $termQuery->orWhereHas('branchProducts', fn (Builder $assignments) => $this->whereFragments($assignments, 'barcode', $fragments));

                // JSON_TABLE compara el texto decodificado, incluidos aliases con acentos.
                if (in_array($termQuery->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                    $conditions = implode(' OR ', array_fill(0, count($fragments), 'lab_alias.value COLLATE utf8mb4_unicode_ci LIKE ?'));
                    $termQuery->orWhereRaw(
                        "EXISTS (SELECT 1 FROM JSON_TABLE(COALESCE(products.search_aliases, JSON_ARRAY()), '$[*]' COLUMNS (value VARCHAR(255) PATH '$')) AS lab_alias WHERE {$conditions})",
                        array_map(fn (string $fragment) => $this->pattern($fragment), $fragments),
                    );
                } else {
                    // SQLite se usa en pruebas; json_each también entrega valores decodificados.
                    $conditions = implode(' OR ', array_fill(0, count($fragments), $this->sqliteFold('inventory_alias.value').' LIKE ?'));
                    $termQuery->orWhereRaw(
                        "EXISTS (SELECT 1 FROM json_each(COALESCE(products.search_aliases, '[]')) AS inventory_alias WHERE {$conditions})",
                        array_map(fn (string $fragment) => $this->pattern($fragment), $fragments),
                    );
                }

                foreach ($dictionary['global'] as $canonical => $aliases) {
                    if ($this->aliasesMayMatch($aliases, $fragments)) {
                        $termQuery->orWhere(function (Builder $source) use ($canonical): void {
                            foreach ($this->documents->tokens((string) $canonical) as $word) {
                                $source->where(fn (Builder $wordQuery) => $this->whereSourceFragments($wordQuery, [$word]));
                            }
                        });
                    }
                }

                foreach ($dictionary['categories'] as $category => $aliases) {
                    if ($this->aliasesMayMatch($aliases, $fragments)) {
                        $termQuery->orWhereHas('category', function (Builder $categoryQuery) use ($category): void {
                            foreach ($this->documents->tokens((string) $category) as $word) {
                                $categoryQuery->where('name', 'like', $this->pattern($word));
                            }
                        });
                    }
                }

                foreach ($dictionary['products'] as $identifier => $aliases) {
                    if ($this->aliasesMayMatch($aliases, $fragments)) {
                        $termQuery->orWhere(function (Builder $product) use ($identifier): void {
                            if (ctype_digit((string) $identifier)) {
                                $product->whereKey((int) $identifier);
                            } else {
                                $product->whereRaw('1 = 0');
                            }
                            $product->orWhereHas('barcodes', fn (Builder $codes) => $codes->where('code', (string) $identifier));
                            $product->orWhereHas('branchProducts', fn (Builder $assignments) => $assignments->where('barcode', (string) $identifier));
                        });
                    }
                }
            });
        }
    }

    public function fragments(string $term): array
    {
        $parts = ctype_digit($term) ? 1 : match (true) {
            strlen($term) >= 9 => 3,
            strlen($term) >= 5 => 2,
            default => 1,
        };
        // Con hasta N errores, uno de N+1 segmentos permanece intacto.
        // Buscar cualquiera conserva candidatos de prefijo y de corrección, sin decidir el ranking.
        $fragments = [];
        for ($part = 0; $part < $parts; $part++) {
            $start = (int) floor(strlen($term) * $part / $parts);
            $end = (int) floor(strlen($term) * ($part + 1) / $parts);
            $fragments[] = substr($term, $start, $end - $start);
        }

        return array_values(array_unique($fragments));
    }

    private function whereSourceFragments(Builder $query, array $fragments): void
    {
        $query->where(fn (Builder $names) => $this->whereFragments($names, 'products.name', $fragments))
            ->orWhereHas('category', function (Builder $category) use ($fragments): void {
                $category->where(fn (Builder $names) => $this->whereFragments($names, 'name', $fragments))
                    ->orWhereHas('productDepartment', fn (Builder $departments) => $this->whereFragments($departments, 'name', $fragments));
            });
    }

    private function whereFragments(Builder $query, string $column, array $fragments): void
    {
        $query->where(function (Builder $matches) use ($column, $fragments): void {
            foreach ($fragments as $fragment) {
                if ($matches->getConnection()->getDriverName() === 'sqlite') {
                    $matches->orWhereRaw($this->sqliteFold($column).' LIKE ?', [$this->pattern($fragment)]);
                } else {
                    $matches->orWhere($column, 'like', $this->pattern($fragment));
                }
            }
        });
    }

    private function aliasesMayMatch(mixed $aliases, array $fragments): bool
    {
        foreach (is_array($aliases) ? $aliases : [$aliases] as $alias) {
            if (! is_string($alias)) {
                continue;
            }
            $alias = $this->documents->normalize($alias);
            foreach ($fragments as $fragment) {
                if (str_contains($alias, $fragment)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function pattern(string $fragment): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $fragment).'%';
    }

    private function sqliteFold(string $column): string
    {
        $expression = 'LOWER('.$column.')';
        foreach (['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n'] as $accent => $plain) {
            $expression = "REPLACE({$expression}, '{$accent}', '{$plain}')";
        }

        return $expression;
    }
}
