<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $table = 'branches';

    protected $fillable = [
        'name',
        'address',
        'active',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'branch_user');
    }

    public function branchProducts()
    {
        return $this->hasMany(BranchProduct::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'branch_products')
            ->withPivot([
                'price',
                'stock',
                'min_stock',
                'active',
            ])
            ->withTimestamps();
    }
}
