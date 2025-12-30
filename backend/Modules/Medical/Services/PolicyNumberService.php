<?php
namespace Modules\Medical\Services;

use Modules\Medical\Models\Policy;
use Illuminate\Support\Str;

class PolicyNumberService
{
    /**
     * Generate a unique policy number.
     * Format: POL-{YEAR}-{SEQUENCE} (e.g., POL-2025-00042)
     */
    public function generate(): string
    {
        $year = date('Y');
        $prefix = "POL-{$year}";

        // Find the last policy created this year
        $lastPolicy = Policy::where('policy_number', 'like', "{$prefix}-%")
            ->orderBy('id', 'desc')
            ->first();

        if (! $lastPolicy) {
            return "{$prefix}-00001";
        }

        // Extract number, increment, and pad
        // Exp: POL-2025-00042 -> "00042" -> 42
        $lastNumber = (int) Str::afterLast($lastPolicy->policy_number, '-');
        $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$newNumber}";
    }
}