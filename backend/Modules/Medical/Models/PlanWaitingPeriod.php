<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Medical\Constants\MedicalConstants;

class PlanWaitingPeriod extends BaseModel
{
    protected $table = 'med_plan_waiting_periods';

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
        'plan_id',
        'benefit_id',
        'waiting_type',
        'name',
        'waiting_days',
        'applies_to_member_types',
        'applies_to_new_members',
        'applies_to_upgrades',
        'can_be_waived',
        'waiver_conditions',
        'description',
        'is_active',
    ];

    protected $casts = [
        'waiting_days' => 'integer',
        'applies_to_member_types' => 'array',
        'applies_to_new_members' => 'boolean',
        'applies_to_upgrades' => 'boolean',
        'can_be_waived' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'waiting_type' => MedicalConstants::WAITING_TYPE_GENERAL,
        'applies_to_new_members' => true,
        'applies_to_upgrades' => false,
        'can_be_waived' => false,
        'is_active' => true,
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

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class, 'benefit_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeByType($query, string $type)
    {
        return $query->where('waiting_type', $type);
    }

    public function scopeGeneral($query)
    {
        return $query->where('waiting_type', MedicalConstants::WAITING_TYPE_GENERAL);
    }

    public function scopeMaternity($query)
    {
        return $query->where('waiting_type', MedicalConstants::WAITING_TYPE_MATERNITY);
    }

    public function scopePreExisting($query)
    {
        return $query->where('waiting_type', MedicalConstants::WAITING_TYPE_PRE_EXISTING);
    }

    public function scopeForNewMembers($query)
    {
        return $query->where('applies_to_new_members', true);
    }

    public function scopeForUpgrades($query)
    {
        return $query->where('applies_to_upgrades', true);
    }

    public function scopeBenefitSpecific($query)
    {
        return $query->whereNotNull('benefit_id');
    }

    public function scopePlanLevel($query)
    {
        return $query->whereNull('benefit_id');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getWaitingTypeLabelAttribute(): string
    {
        return MedicalConstants::WAITING_TYPES[$this->waiting_type] ?? $this->waiting_type;
    }

    public function getIsBenefitSpecificAttribute(): bool
    {
        return $this->benefit_id !== null;
    }

    public function getIsPlanLevelAttribute(): bool
    {
        return $this->benefit_id === null;
    }

    public function getWaitingMonthsAttribute(): int
    {
        return (int) ceil($this->waiting_days / 30);
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function appliesToMemberType(string $memberType): bool
    {
        if (empty($this->applies_to_member_types)) {
            return true; // Applies to all if not specified
        }

        return in_array($memberType, $this->applies_to_member_types);
    }

    public function calculateWaitingEndDate(\DateTimeInterface $coverStartDate): \DateTimeInterface
    {
        // Convert the provided DateTimeInterface to a DateTimeImmutable which implements modify()
        $date = \DateTimeImmutable::createFromInterface($coverStartDate);
        return $date->modify("+{$this->waiting_days} days");
    }

    public function hasWaitingElapsed(\DateTimeInterface $coverStartDate): bool
    {
        $waitingEndDate = $this->calculateWaitingEndDate($coverStartDate);
        
        return now()->greaterThanOrEqualTo($waitingEndDate);
    }
}