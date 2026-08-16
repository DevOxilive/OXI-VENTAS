<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_department_id',
        'name',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'product_department_id' => 'integer',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function productDepartment()
    {
        return $this->belongsTo(ProductDepartment::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
    }
}
