<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Medical\Constants\MedicalConstants;

class Policy extends BaseModel
{
    protected $table = 'med_policies';

    protected $fillable = [
        'policy_number',
        'application_id',
        'scheme_id',
        'plan_id',
        'rate_card_id',
        'policy_type',
        'group_id',
        'principal_member_id',
        'holder_name',
        'holder_email',
        'holder_phone',
        'inception_date',
        'expiry_date',
        'renewal_date',
        'policy_term_months',
        'is_auto_renew',
        'currency',
        'billing_frequency',
        'base_premium',
        'addon_premium',
        'loading_amount',
        'discount_amount',
        'total_premium',
        'tax_amount',
        'gross_premium',
        'member_count',
        'principal_count',
        'dependent_count',
        'status',
        'underwriting_status',
        'underwriting_notes',
        'underwritten_by',
        'underwritten_at',
        'suspended_at',
        'suspension_reason',
        'cancelled_at',
        'cancellation_reason',
        'cancellation_notes',
        'cancelled_by',
        'previous_policy_id',
        'renewed_to_policy_id',
        'renewal_count',
        'source',
        'sales_agent_id',
        'broker_id',
        'commission_rate',
        'commission_amount',
        'promo_code_id',
        'applied_discounts',
        'applied_loadings',
        'issued_at',
        'issued_by',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'inception_date' => 'date',
        'expiry_date' => 'date',
        'renewal_date' => 'date',
        'policy_term_months' => 'integer',
        'is_auto_renew' => 'boolean',
        'base_premium' => 'decimal:2',
        'addon_premium' => 'decimal:2',
        'loading_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_premium' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'gross_premium' => 'decimal:2',
        'member_count' => 'integer',
        'principal_count' => 'integer',
        'dependent_count' => 'integer',
        'underwritten_at' => 'datetime',
        'suspended_at' => 'date',
        'cancelled_at' => 'date',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'applied_discounts' => 'array',
        'applied_loadings' => 'array',
        'issued_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'status' => MedicalConstants::POLICY_STATUS_ACTIVE,
        'underwriting_status' => MedicalConstants::UW_STATUS_APPROVED,
        'policy_term_months' => 12,
        'is_auto_renew' => true,
        'billing_frequency' => MedicalConstants::BILLING_MONTHLY,
        'currency' => 'ZMW',
        'renewal_count' => 0,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected static function booted()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->policy_number)) {
                $model->policy_number = $model->generatePolicyNumber();
            }
            if (empty($model->issued_at)) {
                $model->issued_at = now();
            }
        });
    }

    protected function generatePolicyNumber(): string
    {
        $prefix = MedicalConstants::PREFIX_POLICY;
        $year = date('Y');
        
        $lastNumber = static::where('policy_number', 'like', "{$prefix}{$year}-%")
            ->orderByRaw('CAST(SUBSTRING(policy_number, -6) AS UNSIGNED) DESC')
            ->value('policy_number');

        if ($lastNumber) {
            $sequence = (int) substr($lastNumber, -6) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s%s-%06d', $prefix, $year, $sequence);
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class, 'scheme_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(RateCard::class, 'rate_card_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function principalMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'principal_member_id');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }

    public function previousPolicy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_policy_id');
    }

    public function renewedToPolicy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_to_policy_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'policy_id');
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', MedicalConstants::MEMBER_STATUS_ACTIVE);
    }

    public function principals(): HasMany
    {
        return $this->members()->where('member_type', MedicalConstants::MEMBER_TYPE_PRINCIPAL);
    }

    public function dependents(): HasMany
    {
        return $this->members()->where('member_type', '!=', MedicalConstants::MEMBER_TYPE_PRINCIPAL);
    }

    public function policyAddons(): HasMany
    {
        return $this->hasMany(PolicyAddon::class, 'policy_id');
    }

    public function activeAddons(): HasMany
    {
        return $this->policyAddons()->where('is_active', true);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PolicyDocument::class, 'policy_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('status', MedicalConstants::POLICY_STATUS_ACTIVE);
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', MedicalConstants::POLICY_STATUS_SUSPENDED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', MedicalConstants::POLICY_STATUS_CANCELLED);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', MedicalConstants::POLICY_STATUS_EXPIRED);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('status', MedicalConstants::POLICY_STATUS_ACTIVE)
            ->whereBetween('expiry_date', [now(), now()->addDays($days)]);
    }

    public function scopeForRenewal($query, int $daysBeforeExpiry = 60)
    {
        return $query->where('status', MedicalConstants::POLICY_STATUS_ACTIVE)
            ->where('is_auto_renew', true)
            ->whereBetween('expiry_date', [now(), now()->addDays($daysBeforeExpiry)])
            ->whereNull('renewed_to_policy_id');
    }

    public function scopeCorporate($query)
    {
        return $query->whereIn('policy_type', [
            MedicalConstants::POLICY_TYPE_CORPORATE,
            MedicalConstants::POLICY_TYPE_SME,
        ]);
    }

    public function scopeIndividual($query)
    {
        return $query->whereIn('policy_type', [
            MedicalConstants::POLICY_TYPE_INDIVIDUAL,
            MedicalConstants::POLICY_TYPE_FAMILY,
        ]);
    }

    public function scopeForGroup($query, string $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    public function scopeInForce($query)
    {
        return $query->where('status', MedicalConstants::POLICY_STATUS_ACTIVE)
            ->where('inception_date', '<=', now())
            ->where('expiry_date', '>=', now());
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getStatusLabelAttribute(): string
    {
        return MedicalConstants::POLICY_STATUSES[$this->status] ?? $this->status;
    }

    public function getPolicyTypeLabelAttribute(): string
    {
        return MedicalConstants::POLICY_TYPES[$this->policy_type] ?? $this->policy_type;
    }

    public function getBillingFrequencyLabelAttribute(): string
    {
        return MedicalConstants::BILLING_FREQUENCIES[$this->billing_frequency] ?? $this->billing_frequency;
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === MedicalConstants::POLICY_STATUS_ACTIVE;
    }

    public function getIsSuspendedAttribute(): bool
    {
        return $this->status === MedicalConstants::POLICY_STATUS_SUSPENDED;
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->status === MedicalConstants::POLICY_STATUS_CANCELLED;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->status === MedicalConstants::POLICY_STATUS_EXPIRED 
            || ($this->expiry_date && $this->expiry_date->isPast());
    }

    public function getIsRenewedAttribute(): bool
    {
        return $this->renewed_to_policy_id !== null;
    }

    public function getIsCorporateAttribute(): bool
    {
        return in_array($this->policy_type, [
            MedicalConstants::POLICY_TYPE_CORPORATE,
            MedicalConstants::POLICY_TYPE_SME,
        ]);
    }

    public function getIsIndividualAttribute(): bool
    {
        return in_array($this->policy_type, [
            MedicalConstants::POLICY_TYPE_INDIVIDUAL,
            MedicalConstants::POLICY_TYPE_FAMILY,
        ]);
    }

    public function getIsInForceAttribute(): bool
    {
        return $this->is_active
            && $this->inception_date <= now()
            && $this->expiry_date >= now();
    }

    public function getDaysToExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) return null;
        return max(0, now()->diffInDays($this->expiry_date, false));
    }

    public function getMonthlyPremiumAttribute(): float
    {
        return match($this->billing_frequency) {
            MedicalConstants::BILLING_MONTHLY => (float) $this->gross_premium,
            MedicalConstants::BILLING_QUARTERLY => round($this->gross_premium / 3, 2),
            MedicalConstants::BILLING_SEMI_ANNUAL => round($this->gross_premium / 6, 2),
            MedicalConstants::BILLING_ANNUAL => round($this->gross_premium / 12, 2),
            default => (float) $this->gross_premium,
        };
    }

    public function getAnnualPremiumAttribute(): float
    {
        return match($this->billing_frequency) {
            MedicalConstants::BILLING_MONTHLY => round($this->gross_premium * 12, 2),
            MedicalConstants::BILLING_QUARTERLY => round($this->gross_premium * 4, 2),
            MedicalConstants::BILLING_SEMI_ANNUAL => round($this->gross_premium * 2, 2),
            MedicalConstants::BILLING_ANNUAL => (float) $this->gross_premium,
            default => (float) $this->gross_premium,
        };
    }

    public function getPolicyHolderNameAttribute(): string
    {
        if ($this->holder_name) {
            return $this->holder_name;
        }

        if ($this->is_corporate && $this->group) {
            return $this->group->name;
        }

        if ($this->principalMember) {
            return $this->principalMember->full_name;
        }

        return 'Unknown';
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->policy_number . ' - ' . $this->policy_holder_name;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Update member counts.
     */
    public function updateMemberCounts(): void
    {
        $this->member_count = $this->members()->count();
        $this->principal_count = $this->principals()->count();
        $this->dependent_count = $this->dependents()->count();
        $this->save();
    }

    /**
     * Recalculate premiums from members.
     */
    public function recalculatePremiums(): void
    {
        $this->base_premium = $this->activeMembers()->sum('premium');
        $this->loading_amount = $this->activeMembers()->sum('loading_amount');
        $this->addon_premium = $this->activeAddons()->sum('premium');
        
        $this->total_premium = $this->base_premium + $this->addon_premium + $this->loading_amount - $this->discount_amount;
        
        $taxRate = config('medical.tax_rate', 0);
        $this->tax_amount = round($this->total_premium * $taxRate, 2);
        
        $this->gross_premium = $this->total_premium + $this->tax_amount;
        
        $this->save();
    }

    /**
     * Suspend the policy.
     */
    public function suspend(string $reason): bool
    {
        $this->status = MedicalConstants::POLICY_STATUS_SUSPENDED;
        $this->suspended_at = now();
        $this->suspension_reason = $reason;
        
        // Suspend all active members
        $this->activeMembers()->update([
            'status' => MedicalConstants::MEMBER_STATUS_SUSPENDED,
            'status_changed_at' => now(),
            'status_reason' => 'Policy suspended',
        ]);
        
        return $this->save();
    }

    /**
     * Reinstate the policy.
     */
    public function reinstate(): bool
    {
        if ($this->status !== MedicalConstants::POLICY_STATUS_SUSPENDED) {
            return false;
        }

        $this->status = MedicalConstants::POLICY_STATUS_ACTIVE;
        $this->suspended_at = null;
        $this->suspension_reason = null;
        
        // Reactivate suspended members
        $this->members()
            ->where('status', MedicalConstants::MEMBER_STATUS_SUSPENDED)
            ->update([
                'status' => MedicalConstants::MEMBER_STATUS_ACTIVE,
                'status_changed_at' => now(),
                'status_reason' => 'Policy reinstated',
            ]);
        
        return $this->save();
    }

    /**
     * Cancel the policy.
     */
    public function cancel(string $reason, ?string $cancelledBy, ?string $notes = null): bool
    {
        $this->status = MedicalConstants::POLICY_STATUS_CANCELLED;
        $this->cancelled_at = now();
        $this->cancellation_reason = $reason;
        $this->cancellation_notes = $notes;
        $this->cancelled_by = $cancelledBy;
        
        // Terminate all members
        $this->activeMembers()->update([
            'status' => MedicalConstants::MEMBER_STATUS_TERMINATED,
            'terminated_at' => now(),
            'termination_reason' => 'policy_cancelled',
            'termination_notes' => 'Policy cancelled: ' . $reason,
        ]);
        
        // Block all cards
        $this->members()->update([
            'card_status' => MedicalConstants::CARD_STATUS_BLOCKED,
        ]);
        
        return $this->save();
    }

    /**
     * Set the principal member.
     */
    public function setPrincipalMember(Member $member): void
    {
        $this->principal_member_id = $member->id;
        $this->holder_name = $member->full_name;
        $this->holder_email = $member->email;
        $this->holder_phone = $member->mobile ?? $member->phone;
        $this->save();
    }

    /**
     * Create policy from an approved application.
     */
    public static function createFromApplication(Application $application, string $issuedBy): self
    {
        if (!$application->can_be_converted) {
            throw new \Exception('Application cannot be converted to policy');
        }

        $expiryDate = $application->proposed_end_date 
            ?? $application->proposed_start_date->copy()->addMonths($application->policy_term_months)->subDay();

        $policy = new self([
            'application_id' => $application->id,
            'scheme_id' => $application->scheme_id,
            'plan_id' => $application->plan_id,
            'rate_card_id' => $application->rate_card_id,
            'policy_type' => $application->policy_type,
            'group_id' => $application->group_id,
            'holder_name' => $application->applicant_name,
            'holder_email' => $application->contact_email,
            'holder_phone' => $application->contact_phone,
            'inception_date' => $application->proposed_start_date,
            'expiry_date' => $expiryDate,
            'renewal_date' => $expiryDate,
            'policy_term_months' => $application->policy_term_months,
            'billing_frequency' => $application->billing_frequency,
            'currency' => $application->currency,
            'base_premium' => $application->base_premium,
            'addon_premium' => $application->addon_premium,
            'loading_amount' => $application->loading_amount,
            'discount_amount' => $application->discount_amount,
            'total_premium' => $application->total_premium,
            'tax_amount' => $application->tax_amount,
            'gross_premium' => $application->gross_premium,
            'member_count' => $application->member_count,
            'principal_count' => $application->principal_count,
            'dependent_count' => $application->dependent_count,
            'status' => MedicalConstants::POLICY_STATUS_ACTIVE,
            'underwriting_status' => $application->underwriting_status,
            'underwriting_notes' => $application->underwriting_notes,
            'underwritten_by' => $application->underwriter_id,
            'underwritten_at' => $application->underwriting_completed_at,
            'source' => $application->source,
            'sales_agent_id' => $application->sales_agent_id,
            'broker_id' => $application->broker_id,
            'commission_rate' => $application->commission_rate,
            'promo_code_id' => $application->promo_code_id,
            'applied_discounts' => $application->applied_discounts,
            'issued_by' => $issuedBy,
        ]);

        $policy->save();

        return $policy;
    }
}