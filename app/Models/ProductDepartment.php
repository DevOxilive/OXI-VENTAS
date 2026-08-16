<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductDepartment extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'description',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function products()
    {
        return $this->hasManyThrough(Product::class, Category::class);
    }
}
