<?php
namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateCardEntry extends BaseModel
{
    protected $table = 'med_rate_card_entries';

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
        'rate_card_id',
        'min_age',
        'max_age',
        'age_band_label',
        'gender',
        'region_code',
        'base_premium',
    ];

    protected $casts = [
        'min_age' => 'integer',
        'max_age' => 'integer',
        'base_premium' => 'decimal:2',
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

    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(RateCard::class, 'rate_card_id');
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

    public function scopeByRegion($query, ?string $regionCode)
    {
        if ($regionCode === null) {
            return $query->whereNull('region_code');
        }

        return $query->where(function ($q) use ($regionCode) {
            $q->whereNull('region_code')
              ->orWhere('region_code', $regionCode);
        });
    }

    public function scopeUnisex($query)
    {
        return $query->whereNull('gender');
    }

    public function scopeNational($query)
    {
        return $query->whereNull('region_code');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getAgeBandLabelAttribute($value): string
    {
        if ($value) {
            return $value;
        }

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
        $currency = $this->rateCard->currency ?? 'ZMW';
        
        return $currency . ' ' . number_format($this->base_premium, 2);
    }

    public function getIsUnisexAttribute(): bool
    {
        return $this->gender === null;
    }

    public function getIsNationalAttribute(): bool
    {
        return $this->region_code === null;
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
        // Unisex rate applies to all
        if ($this->gender === null) {
            return true;
        }

        return $this->gender === $gender;
    }

    public function appliesToRegion(?string $regionCode): bool
    {
        // National rate applies to all
        if ($this->region_code === null) {
            return true;
        }

        return $this->region_code === $regionCode;
    }
}