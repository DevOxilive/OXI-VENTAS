<?php

namespace App\Search;

use Illuminate\Support\Collection;

/**
 * Resultado común de una búsqueda de productos.
 *
 * Conserva las sugerencias flexibles, pero identifica los productos cuyo
 * código coincide exactamente con el texto recibido. Las pantallas de
 * escaneo pueden usar esa distinción sin perder las alternativas manuales.
 */
final readonly class ProductSearchResult
{
    /**
     * @param Collection<int, int> $ids
     * @param Collection<int, int> $exactBarcodeIds
     */
    public function __construct(
        public Collection $ids,
        public Collection $exactBarcodeIds,
        public bool $isBarcodeQuery,
    ) {}

    public function hasExactBarcode(): bool
    {
        return $this->exactBarcodeIds->isNotEmpty();
    }
}
