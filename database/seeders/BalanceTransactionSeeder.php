<?php
// database/seeders/BalanceTransactionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BalanceTransaction;

class BalanceTransactionSeeder extends Seeder
{
    public function run(): void
    {
        // Initial balance
        BalanceTransaction::create([
            'type' => 'in',
            'amount' => 10000000, // 10 juta initial capital
            'balance_before' => 0,
            'balance_after' => 10000000,
            'description' => 'Modal Awal',
            'payment_method' => 'cash',
            'notes' => 'Modal awal usaha',
            'transaction_date' => now(),
            'created_by' => 1,
        ]);

        // Sample transactions
        BalanceTransaction::create([
            'type' => 'in',
            'amount' => 500000,
            'balance_before' => 10000000,
            'balance_after' => 10500000,
            'description' => 'Pembayaran Order #ORD-202412-001',
            'reference_type' => 'App\Models\OrderPayment',
            'reference_id' => 1,
            'payment_method' => 'cash',
            'notes' => 'DP Order kemeja',
            'transaction_date' => now()->subDays(2),
            'created_by' => 1,
        ]);

        BalanceTransaction::create([
            'type' => 'out',
            'amount' => 300000,
            'balance_before' => 10500000,
            'balance_after' => 10200000,
            'description' => 'Pembelian bahan kain katun',
            'reference_type' => 'App\Models\MaterialPurchase',
            'reference_id' => 1,
            'payment_method' => 'transfer',
            'notes' => 'Supplier: Textile Mart',
            'transaction_date' => now()->subDay(),
            'created_by' => 1,
        ]);
    }
}