<?php
// app/Models/Customer.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'gender',
        'measurement_notes',
        'preferences',
        'total_orders',
        'total_spent',
        'first_order_date',
        'last_order_date',
        'notes'
    ];

    protected $casts = [
        'first_order_date' => 'date',
        'last_order_date' => 'date',
        'total_spent' => 'decimal:2',
    ];

    // Relationships
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Helper methods
    public function getAverageOrderValueAttribute()
    {
        if ($this->total_orders > 0) {
            return $this->total_spent / $this->total_orders;
        }
        return 0;
    }

    public function isRegularCustomer(): bool
    {
        return $this->total_orders >= 3; // customer reguler jika order >= 3x
    }
}