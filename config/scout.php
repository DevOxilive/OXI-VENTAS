<?php

use App\Models\Product;

return [
    'driver' => env('SCOUT_DRIVER', 'database'),

    'prefix' => env('SCOUT_PREFIX', ''),

    'queue' => env('SCOUT_QUEUE', false),

    'after_commit' => true,

    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],

    'soft_delete' => false,

    'identify' => false,

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index-settings' => [
            Product::class => [
                'searchableAttributes' => [
                    'barcodes',
                    'barcode_suffixes',
                    'branch_barcodes',
                    'name',
                    'search_terms',
                    'prefixes',
                    'aliases',
                    'category',
                    'department',
                ],
                'displayedAttributes' => ['id', 'name'],
                'filterableAttributes' => [
                    'active',
                    'branch_ids',
                    'active_branch_ids',
                    'has_active_branch',
                    'category_id',
                    'department_id',
                    'inventory_quantity_mode',
                ],
                'rankingRules' => [
                    'words',
                    'typo',
                    'proximity',
                    'attribute',
                    'sort',
                    'exactness',
                ],
                'typoTolerance' => [
                    'enabled' => true,
                    'minWordSizeForTypos' => [
                        'oneTypo' => 5,
                        'twoTypos' => 9,
                    ],
                    'disableOnAttributes' => ['barcodes', 'branch_barcodes'],
                    'disableOnNumbers' => true,
                ],
                'pagination' => [
                    'maxTotalHits' => 10000,
                ],
            ],
        ],
        'model-settings' => [],
    ],
];
