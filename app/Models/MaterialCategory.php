<?php
// app/Models/MaterialCategory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
    ];

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    // Hitung total stok di kategori
    public function getTotalStockAttribute(): float
    {
        return $this->materials->sum('stock');
    }

    // Hitung total nilai stok
    public function getTotalStockValueAttribute(): float
    {
        return $this->materials->sum('stock_value');
    }
}