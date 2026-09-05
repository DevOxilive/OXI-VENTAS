<?php

namespace App\Models;

use App\Search\ProductSearchDocumentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable, SoftDeletes;

    protected $fillable = [
        'name',
        'search_aliases',
        'description',
        'image',
        'cost',
        'sale_price',
        'margin_percentage',
        'unit',
        'inventory_unit',
        'pieces_per_box',
        'has_box_presentation',
        'inventory_quantity_mode',
        'cost_per_piece',
        'sale_price_per_piece',
        'cost_per_box',
        'sale_price_per_box',
        'category_id',
        'subcategory_id',
        'active',
    ];

    protected $casts = [
        'search_aliases' => 'array',
        'cost' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'cost_per_piece' => 'decimal:4',
        'sale_price_per_piece' => 'decimal:4',
        'cost_per_box' => 'decimal:4',
        'sale_price_per_box' => 'decimal:4',
        'has_box_presentation' => 'boolean',
        'active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function branchInventories()
    {
        return $this->hasMany(BranchInventory::class);
    }

    public function inventories()
    {
        return $this->hasMany(BranchInventory::class);
    }

    public function barcodes()
    {
        return $this->hasMany(Barcode::class);
    }

    public function branchProducts()
    {
        return $this->hasMany(BranchProduct::class);
    }

    public function searchableAs(): string
    {
        return (string) config('scout.prefix', '').'products';
    }

    public function toSearchableArray(): array
    {
        return app(ProductSearchDocumentBuilder::class)->build($this);
    }

    public function makeSearchableUsing(Collection $models): Collection
    {
        return $models->loadMissing([
            'category.productDepartment',
            'barcodes',
            'branchProducts',
        ]);
    }

    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with([
            'category.productDepartment',
            'barcodes',
            'branchProducts',
        ]);
    }
}
