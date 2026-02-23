<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MultipleOfThousand implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value % 1000 !== 0) {
            $fail('Jumlah harus dalam kelipatan Rp 1.000 (contoh: 40.000, 50.000, 100.000)');
        }
    }
}