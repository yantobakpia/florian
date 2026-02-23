<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use App\Models\ClothingType;
use App\Models\Customer;
use App\Models\Material;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Users
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@tailorpro.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'phone' => '081234567890',
            'is_active' => true,
            'join_date' => now(),
        ]);

        // Clothing Types (Standard)
        $standardTypes = [
            ['name' => 'Kemeja', 'base_price' => 150000, 'material_needed' => 2.5],
            ['name' => 'Celana', 'base_price' => 120000, 'material_needed' => 2.0],
            ['name' => 'Jas', 'base_price' => 450000, 'material_needed' => 3.5],
            ['name' => 'Dress', 'base_price' => 250000, 'material_needed' => 3.0],
            ['name' => 'Kaos', 'base_price' => 80000, 'material_needed' => 1.5],
            ['name' => 'Jaket', 'base_price' => 300000, 'material_needed' => 2.8],
            ['name' => 'Rok', 'base_price' => 100000, 'material_needed' => 1.8],
            ['name' => 'Blouse', 'base_price' => 130000, 'material_needed' => 2.2],
            ['name' => 'Seragam', 'base_price' => 200000, 'material_needed' => 2.5],
        ];

        foreach ($standardTypes as $type) {
            ClothingType::create($type);
        }

        // Clothing Types (Custom - bisa diinput user)
        $customTypes = [
            ['name' => 'Gamis', 'is_custom' => true],
            ['name' => 'Setelan', 'is_custom' => true],
            ['name' => 'Baju Muslim', 'is_custom' => true],
        ];

        foreach ($customTypes as $type) {
            ClothingType::create($type);
        }

        // Materials
        Material::create([
            'name' => 'Katun Prima',
            'stock' => 50,
            'price_per_meter' => 35000,
            'supplier' => 'PT Textile Jaya',
            'min_stock' => 5,
        ]);

        Material::create([
            'name' => 'Linen Import',
            'stock' => 20,
            'price_per_meter' => 75000,
            'supplier' => 'CV Linen Indonesia',
            'min_stock' => 3,
        ]);

        // Customers
        Customer::create([
            'name' => 'Budi Santoso',
            'phone' => '081234567801',
            'email' => 'budi@email.com',
            'address' => 'Jl. Merdeka No. 123, Jakarta',
            'gender' => 'male',
            'total_orders' => 5,
            'total_spent' => 2500000,
            'first_order_date' => now()->subMonths(6),
            'last_order_date' => now()->subDays(7),
        ]);

        $this->command->info('✅ Database seeded successfully!');
        $this->command->info('👕 Clothing Types: ' . ClothingType::count() . ' types created');
        $this->command->info('🧵 Materials: ' . Material::count() . ' materials created');
        $this->command->info('👥 Customers: ' . Customer::count() . ' customers created');
    }
}