<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Medical\Constants\MedicalConstants;

class PlanBenefitLimit extends BaseModel
{
    protected $table = 'med_plan_benefit_limits';

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
        'plan_benefit_id',
        'member_type',
        'min_age',
        'max_age',
        'limit_amount',
        'limit_count',
        'limit_days',
        'display_value',
    ];

    protected $casts = [
        'min_age' => 'integer',
        'max_age' => 'integer',
        'limit_amount' => 'decimal:2',
        'limit_count' => 'integer',
        'limit_days' => 'integer',
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

    public function planBenefit(): BelongsTo
    {
        return $this->belongsTo(PlanBenefit::class, 'plan_benefit_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeByMemberType($query, string $memberType)
    {
        return $query->where('member_type', $memberType);
    }

    public function scopeByAge($query, int $age)
    {
        return $query->where('min_age', '<=', $age)
                     ->where('max_age', '>=', $age);
    }

    public function scopeForMember($query, string $memberType, int $age)
    {
        return $query->byMemberType($memberType)->byAge($age);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getMemberTypeLabelAttribute(): string
    {
        return MedicalConstants::MEMBER_TYPES[$this->member_type] ?? $this->member_type;
    }

    public function getAgeBandLabelAttribute(): string
    {
        if ($this->min_age === 0 && $this->max_age === 100) {
            return 'All Ages';
        }

        return "{$this->min_age} - {$this->max_age}";
    }

    public function getFormattedDisplayValueAttribute(): string
    {
        if ($this->display_value) {
            return $this->display_value;
        }

        if ($this->limit_amount !== null) {
            return 'K' . number_format($this->limit_amount, 2);
        }

        if ($this->limit_count !== null) {
            return $this->limit_count . ' visits';
        }

        if ($this->limit_days !== null) {
            return $this->limit_days . ' days';
        }

        return 'N/A';
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function appliesToAge(int $age): bool
    {
        return $age >= $this->min_age && $age <= $this->max_age;
    }
}