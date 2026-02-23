<?php
// app/Models/Material.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'unit',
        'stock',
        'min_stock',
        'price_per_unit',
        'color',
        'supplier',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'stock' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'price_per_unit' => 'decimal:0',
        'is_active' => 'boolean',
    ];

    const UNITS = [
        'meter' => 'Meter',
        'yard' => 'Yard',
        'pcs' => 'Pieces',
        'roll' => 'Roll',
        'kg' => 'Kilogram',
        'set' => 'Set',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(MaterialPurchase::class);
    }

    // Cek stok rendah
    public function getIsLowStockAttribute(): bool
    {
        return $this->stock <= $this->min_stock;
    }

    // Format stok dengan unit
    public function getFormattedStockAttribute(): string
    {
        return $this->stock . ' ' . $this->unit;
    }

    // Format harga per unit
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->price_per_unit, 0, ',', '.') . ' / ' . $this->unit;
    }

    // Nilai total stok
    public function getStockValueAttribute(): float
    {
        return $this->stock * $this->price_per_unit;
    }

    public function getFormattedStockValueAttribute(): string
    {
        return 'Rp ' . number_format($this->stock_value, 0, ',', '.');
    }
}