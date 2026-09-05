<?php

return [
    'fallback_enabled' => env('PRODUCT_SEARCH_FALLBACK', true),
    'max_results' => (int) env('PRODUCT_SEARCH_MAX_RESULTS', 10000),
    'minimum_prefix_length' => 3,
    'minimum_barcode_suffix_length' => (int) env('PRODUCT_SEARCH_MIN_BARCODE_SUFFIX', 5),
    'minimum_lot_fragment_length' => (int) env('PRODUCT_SEARCH_MIN_LOT_FRAGMENT', 4),
    'aliases_path' => resource_path('search/product_aliases.json'),
];
