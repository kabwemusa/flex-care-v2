<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Medical\Constants\MedicalConstants;

class AddonBenefit extends BaseModel
{
    protected $table = 'med_addon_benefits';

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
        'addon_id',
        'benefit_id',
        'limit_amount',
        'limit_count',
        'limit_days',
        'limit_type',
        'limit_frequency',
        'limit_basis',
        'waiting_period_days',
        'display_value',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'limit_amount' => 'decimal:2',
        'limit_count' => 'integer',
        'limit_days' => 'integer',
        'waiting_period_days' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'sort_order' => 0,
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

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class, 'addon_id');
    }

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class, 'benefit_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getEffectiveLimitTypeAttribute(): string
    {
        return $this->limit_type ?? $this->benefit->limit_type ?? MedicalConstants::MONETARY;
    }

    public function getEffectiveLimitFrequencyAttribute(): string
    {
        return $this->limit_frequency ?? $this->benefit->limit_frequency ?? MedicalConstants::PER_ANNUM;
    }

    public function getEffectiveLimitBasisAttribute(): string
    {
        return $this->limit_basis ?? $this->benefit->limit_basis ?? MedicalConstants::PER_MEMBER;
    }

    public function getFormattedDisplayValueAttribute(): string
    {
        if ($this->display_value) {
            return $this->display_value;
        }

        return $this->formatLimit();
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function formatLimit(): string
    {
        $limitType = $this->effective_limit_type;
        
        return match($limitType) {
            MedicalConstants::MONETARY => $this->limit_amount 
                ? 'K' . number_format($this->limit_amount, 2) 
                : 'N/A',
            MedicalConstants::COUNT => $this->limit_count 
                ? $this->limit_count . ' visits' 
                : 'N/A',
            MedicalConstants::DAYS => $this->limit_days 
                ? $this->limit_days . ' days' 
                : 'N/A',
            MedicalConstants::UNLIMITED => 'Unlimited',
            default => 'N/A',
        };
    }
}