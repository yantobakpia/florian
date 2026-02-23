<?php
// app/Models/ClothingType.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClothingType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'base_price',
        'material_needed',
        'is_active',
        'is_custom',
        'order_count'
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'material_needed' => 'decimal:2',
        'is_active' => 'boolean',
        'is_custom' => 'boolean',
    ];

    // Relationships
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeStandard($query)
    {
        return $query->where('is_custom', false);
    }

    public function scopeCustom($query)
    {
        return $query->where('is_custom', true);
    }

    public function scopePopular($query, $limit = 10)
    {
        return $query->orderBy('order_count', 'desc')->limit($limit);
    }

    // Methods
    public function incrementOrderCount()
    {
        $this->increment('order_count');
        $this->save();
    }

    public function getDisplayNameAttribute()
    {
        if ($this->is_custom) {
            return "{$this->name} (Custom)";
        }
        return $this->name;
    }
}