<?php
// app/Models/AdditionalCharge.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdditionalCharge extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'description',
        'quantity',
        'unit_price',
        'total_price',
    ];

    protected static function booted(): void
    {
        static::saving(function ($charge) {
            $charge->total_price = ($charge->quantity ?? 1) * ($charge->unit_price ?? 0);
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}