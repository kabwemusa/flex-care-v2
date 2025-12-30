<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddonRateEntry extends BaseModel
{
    protected $table = 'med_addon_rate_entries';

    /**
     * Disable soft deletes for this model.
     */
    use \Illuminate\Database\Eloquent\SoftDeletes {
        \Illuminate\Database\Eloquent\SoftDeletes::bootSoftDeletes as parentBootSoftDeletes;
    }

    public static function bootSoftDeletes()
    {
        // Disable soft deletes for this model
    }

    protected $fillable = [
        'addon_rate_id',
        'min_age',
        'max_age',
        'gender',
        'premium',
    ];

    protected $casts = [
        'min_age' => 'integer',
        'max_age' => 'integer',
        'premium' => 'decimal:2',
    ];

    protected $attributes = [
        'min_age' => 0,
        'max_age' => 100,
    ];

    // =========================================================================
    // CODE GENERATION (Not needed)
    // =========================================================================

    protected function shouldGenerateCode(): bool
    {
        return false;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function addonRate(): BelongsTo
    {
        return $this->belongsTo(AddonRate::class, 'addon_rate_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeByAge($query, int $age)
    {
        return $query->where('min_age', '<=', $age)
                     ->where('max_age', '>=', $age);
    }

    public function scopeByGender($query, ?string $gender)
    {
        if ($gender === null) {
            return $query->whereNull('gender');
        }

        return $query->where(function ($q) use ($gender) {
            $q->whereNull('gender')
              ->orWhere('gender', $gender);
        });
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getAgeBandLabelAttribute(): string
    {
        if ($this->min_age === 0 && $this->max_age === 100) {
            return 'All Ages';
        }

        return "{$this->min_age} - {$this->max_age}";
    }

    public function getGenderLabelAttribute(): string
    {
        return match($this->gender) {
            'M' => 'Male',
            'F' => 'Female',
            default => 'Unisex',
        };
    }

    public function getFormattedPremiumAttribute(): string
    {
        $currency = $this->addonRate->currency ?? 'ZMW';
        
        return $currency . ' ' . number_format($this->premium, 2);
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function appliesToAge(int $age): bool
    {
        return $age >= $this->min_age && $age <= $this->max_age;
    }

    public function appliesToGender(?string $gender): bool
    {
        if ($this->gender === null) {
            return true;
        }

        return $this->gender === $gender;
    }
}