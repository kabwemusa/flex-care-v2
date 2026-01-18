<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Medical\Constants\MedicalConstants;

/**
 * Tracks benefit utilization for a member within a policy period.
 * 
 * When a claim is approved against a benefit, this record is updated
 * to reflect the new usage. The remaining balance is computed automatically.
 */
class MemberBenefitUtilization extends Model
{
    use HasUuids;

    protected $table = 'med_member_benefit_utilization';

    protected $fillable = [
        'member_id',
        'policy_id',
        'benefit_id',
        'plan_benefit_id',
        'period_start',
        'period_end',
        'limit_type',
        'limit_frequency',
        'limit_basis',
        'limit_amount',
        'limit_count',
        'limit_days',
        'used_amount',
        'used_count',
        'used_days',
        'claims_count',
        'remaining_amount',
        'remaining_count',
        'remaining_days',
        'is_exhausted',
        'exhausted_at',
        'last_claim_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'limit_amount' => 'decimal:2',
        'limit_count' => 'integer',
        'limit_days' => 'integer',
        'used_amount' => 'decimal:2',
        'used_count' => 'integer',
        'used_days' => 'integer',
        'claims_count' => 'integer',
        'remaining_amount' => 'decimal:2',
        'remaining_count' => 'integer',
        'remaining_days' => 'integer',
        'is_exhausted' => 'boolean',
        'exhausted_at' => 'datetime',
        'last_claim_at' => 'datetime',
    ];

    protected $attributes = [
        'limit_type' => MedicalConstants::MONETARY,
        'limit_frequency' => MedicalConstants::PER_ANNUM,
        'limit_basis' => MedicalConstants::PER_MEMBER,
        'used_amount' => 0,
        'used_count' => 0,
        'used_days' => 0,
        'claims_count' => 0,
        'is_exhausted' => false,
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class);
    }

    public function planBenefit(): BelongsTo
    {
        return $this->belongsTo(PlanBenefit::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(BenefitUtilizationHistory::class, 'utilization_id')
            ->orderBy('created_at', 'desc');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeForMember($query, string $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeForPolicy($query, string $policyId)
    {
        return $query->where('policy_id', $policyId);
    }

    public function scopeForBenefit($query, string $benefitId)
    {
        return $query->where('benefit_id', $benefitId);
    }

    public function scopeCurrentPeriod($query)
    {
        $today = now()->toDateString();
        return $query->where('period_start', '<=', $today)
                     ->where('period_end', '>=', $today);
    }

    public function scopeActive($query)
    {
        return $query->where('is_exhausted', false);
    }

    public function scopeExhausted($query)
    {
        return $query->where('is_exhausted', true);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getLimitTypeLabelAttribute(): string
    {
        return MedicalConstants::LIMIT_TYPES[$this->limit_type] ?? $this->limit_type;
    }

    public function getUtilizationPercentageAttribute(): float
    {
        return match ($this->limit_type) {
            MedicalConstants::MONETARY => $this->limit_amount > 0 
                ? round(($this->used_amount / $this->limit_amount) * 100, 1) 
                : 0,
            MedicalConstants::COUNT => $this->limit_count > 0 
                ? round(($this->used_count / $this->limit_count) * 100, 1) 
                : 0,
            MedicalConstants::DAYS => $this->limit_days > 0 
                ? round(($this->used_days / $this->limit_days) * 100, 1) 
                : 0,
            default => 0,
        };
    }

    public function getIsWithinPeriodAttribute(): bool
    {
        $today = now()->toDateString();
        return $this->period_start <= $today && $this->period_end >= $today;
    }

    public function getFormattedLimitAttribute(): string
    {
        return match ($this->limit_type) {
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

    public function getFormattedUsedAttribute(): string
    {
        return match ($this->limit_type) {
            MedicalConstants::MONETARY => 'K' . number_format($this->used_amount, 2),
            MedicalConstants::COUNT => $this->used_count . ' visits',
            MedicalConstants::DAYS => $this->used_days . ' days',
            MedicalConstants::UNLIMITED => 'K' . number_format($this->used_amount, 2),
            default => 'N/A',
        };
    }

    public function getFormattedRemainingAttribute(): string
    {
        return match ($this->limit_type) {
            MedicalConstants::MONETARY => $this->remaining_amount !== null 
                ? 'K' . number_format($this->remaining_amount, 2) 
                : 'N/A',
            MedicalConstants::COUNT => $this->remaining_count !== null 
                ? $this->remaining_count . ' visits' 
                : 'N/A',
            MedicalConstants::DAYS => $this->remaining_days !== null 
                ? $this->remaining_days . ' days' 
                : 'N/A',
            MedicalConstants::UNLIMITED => 'Unlimited',
            default => 'N/A',
        };
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Check if the benefit has sufficient balance for a given amount.
     */
    public function hasAvailableBalance(float $amount = 0, int $count = 0, int $days = 0): bool
    {
        if ($this->limit_type === MedicalConstants::UNLIMITED) {
            return true;
        }

        if ($this->is_exhausted) {
            return false;
        }

        return match ($this->limit_type) {
            MedicalConstants::MONETARY => $this->remaining_amount >= $amount,
            MedicalConstants::COUNT => $this->remaining_count >= $count,
            MedicalConstants::DAYS => $this->remaining_days >= $days,
            default => true,
        };
    }

    /**
     * Get the available balance based on limit type.
     */
    public function getAvailableBalance(): float|int|null
    {
        return match ($this->limit_type) {
            MedicalConstants::MONETARY => $this->remaining_amount,
            MedicalConstants::COUNT => $this->remaining_count,
            MedicalConstants::DAYS => $this->remaining_days,
            MedicalConstants::UNLIMITED => null,
            default => null,
        };
    }

    /**
     * Record usage against this benefit.
     */
    public function recordUsage(
        float $amount = 0,
        int $count = 0,
        int $days = 0,
        ?string $claimId = null,
        ?string $claimLineId = null,
        ?string $userId = null,
        ?string $notes = null
    ): BenefitUtilizationHistory {
        // Update usage
        $this->used_amount += $amount;
        $this->used_count += $count;
        $this->used_days += $days;
        $this->claims_count++;
        $this->last_claim_at = now();

        // Recalculate remaining
        $this->recalculateRemaining();

        // Check if exhausted
        $this->checkExhaustion();

        $this->save();

        // Record history
        return $this->history()->create([
            'claim_id' => $claimId,
            'claim_line_id' => $claimLineId,
            'transaction_type' => 'claim_approved',
            'amount_change' => $amount,
            'count_change' => $count,
            'days_change' => $days,
            'balance_amount' => $this->remaining_amount,
            'balance_count' => $this->remaining_count,
            'balance_days' => $this->remaining_days,
            'notes' => $notes,
            'created_by' => $userId,
        ]);
    }

    /**
     * Reverse usage (e.g., when a claim is reversed).
     */
    public function reverseUsage(
        float $amount = 0,
        int $count = 0,
        int $days = 0,
        ?string $claimId = null,
        ?string $claimLineId = null,
        ?string $userId = null,
        ?string $notes = null
    ): BenefitUtilizationHistory {
        // Reverse usage (ensure not negative)
        $this->used_amount = max(0, $this->used_amount - $amount);
        $this->used_count = max(0, $this->used_count - $count);
        $this->used_days = max(0, $this->used_days - $days);
        $this->claims_count = max(0, $this->claims_count - 1);

        // Recalculate remaining
        $this->recalculateRemaining();

        // May no longer be exhausted
        if ($this->is_exhausted) {
            $this->checkExhaustion();
        }

        $this->save();

        // Record history
        return $this->history()->create([
            'claim_id' => $claimId,
            'claim_line_id' => $claimLineId,
            'transaction_type' => 'claim_reversed',
            'amount_change' => -$amount,
            'count_change' => -$count,
            'days_change' => -$days,
            'balance_amount' => $this->remaining_amount,
            'balance_count' => $this->remaining_count,
            'balance_days' => $this->remaining_days,
            'notes' => $notes ?? 'Claim reversed',
            'created_by' => $userId,
        ]);
    }

    /**
     * Recalculate remaining balance.
     */
    public function recalculateRemaining(): void
    {
        $this->remaining_amount = $this->limit_amount !== null 
            ? max(0, $this->limit_amount - $this->used_amount) 
            : null;
        
        $this->remaining_count = $this->limit_count !== null 
            ? max(0, $this->limit_count - $this->used_count) 
            : null;
        
        $this->remaining_days = $this->limit_days !== null 
            ? max(0, $this->limit_days - $this->used_days) 
            : null;
    }

    /**
     * Check if benefit is exhausted.
     */
    public function checkExhaustion(): void
    {
        $wasExhausted = $this->is_exhausted;
        
        $this->is_exhausted = match ($this->limit_type) {
            MedicalConstants::MONETARY => $this->remaining_amount !== null && $this->remaining_amount <= 0,
            MedicalConstants::COUNT => $this->remaining_count !== null && $this->remaining_count <= 0,
            MedicalConstants::DAYS => $this->remaining_days !== null && $this->remaining_days <= 0,
            MedicalConstants::UNLIMITED => false,
            default => false,
        };

        if ($this->is_exhausted && !$wasExhausted) {
            $this->exhausted_at = now();
        } elseif (!$this->is_exhausted) {
            $this->exhausted_at = null;
        }
    }

    /**
     * Initialize utilization from plan benefit configuration.
     */
    public static function initializeForMember(
        Member $member,
        Policy $policy,
        PlanBenefit $planBenefit
    ): self {
        $benefit = $planBenefit->benefit;

        return self::create([
            'member_id' => $member->id,
            'policy_id' => $policy->id,
            'benefit_id' => $benefit->id,
            'plan_benefit_id' => $planBenefit->id,
            'period_start' => $policy->inception_date,
            'period_end' => $policy->expiry_date,
            'limit_type' => $planBenefit->effective_limit_type,
            'limit_frequency' => $planBenefit->effective_limit_frequency,
            'limit_basis' => $planBenefit->effective_limit_basis,
            'limit_amount' => $planBenefit->getEffectiveLimitAmount($member->member_type, $member->age),
            'limit_count' => $planBenefit->limit_count,
            'limit_days' => $planBenefit->limit_days,
            'remaining_amount' => $planBenefit->getEffectiveLimitAmount($member->member_type, $member->age),
            'remaining_count' => $planBenefit->limit_count,
            'remaining_days' => $planBenefit->limit_days,
        ]);
    }
}
