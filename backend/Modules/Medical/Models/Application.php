<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Medical\Constants\MedicalConstants;
use Carbon\Carbon;

class Application extends BaseModel
{
    protected $table = 'med_applications';

    protected $fillable = [
        'application_number',
        'application_type',
        'policy_type',
        'scheme_id',
        'plan_id',
        'rate_card_id',
        'group_id',
        'renewal_of_policy_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'proposed_start_date',
        'proposed_end_date',
        'policy_term_months',
        'billing_frequency',
        'currency',
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
        'promo_code_id',
        'applied_discounts',
        'status',
        'underwriting_status',
        'underwriting_notes',
        'underwriter_id',
        'underwriting_started_at',
        'underwriting_completed_at',
        'underwriting_decisions',
        'quoted_at',
        'submitted_at',
        'accepted_at',
        'acceptance_reference',
        'converted_policy_id',
        'converted_at',
        'converted_by',
        'quote_valid_until',
        'expired_at',
        'source',
        'sales_agent_id',
        'broker_id',
        'commission_rate',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'proposed_start_date' => 'date',
        'proposed_end_date' => 'date',
        'policy_term_months' => 'integer',
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
        'applied_discounts' => 'array',
        'underwriting_decisions' => 'array',
        'underwriting_started_at' => 'datetime',
        'underwriting_completed_at' => 'datetime',
        'quoted_at' => 'datetime',
        'submitted_at' => 'datetime',
        'accepted_at' => 'datetime',
        'converted_at' => 'datetime',
        'quote_valid_until' => 'date',
        'expired_at' => 'date',
        'commission_rate' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'application_type' => MedicalConstants::APPLICATION_TYPE_NEW,
        'status' => MedicalConstants::APPLICATION_STATUS_DRAFT,
        'policy_term_months' => 12,
        'billing_frequency' => MedicalConstants::BILLING_MONTHLY,
        'currency' => 'ZMW',
        'base_premium' => 0,
        'addon_premium' => 0,
        'loading_amount' => 0,
        'discount_amount' => 0,
        'total_premium' => 0,
        'tax_amount' => 0,
        'gross_premium' => 0,
        'member_count' => 0,
        'principal_count' => 0,
        'dependent_count' => 0,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected static function booted()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->application_number)) {
                $model->application_number = $model->generateApplicationNumber();
            }
            if (empty($model->quote_valid_until)) {
                $model->quote_valid_until = now()->addDays(30);
            }
        });
    }

    protected function generateApplicationNumber(): string
    {
        $prefix = MedicalConstants::PREFIX_APPLICATION;
        $year = date('Y');
        
        $lastNumber = static::where('application_number', 'like', "{$prefix}{$year}%")
            ->orderByRaw('CAST(SUBSTRING(application_number, -6) AS UNSIGNED) DESC')
            ->value('application_number');

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

    public function renewalOfPolicy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'renewal_of_policy_id');
    }

    public function convertedPolicy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'converted_policy_id');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ApplicationMember::class, 'application_id');
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('is_active', true);
    }

    public function principals(): HasMany
    {
        return $this->members()
            ->where('member_type', MedicalConstants::MEMBER_TYPE_PRINCIPAL)
            ->where('is_active', true);
    }

    public function dependents(): HasMany
    {
        return $this->members()
            ->where('member_type', '!=', MedicalConstants::MEMBER_TYPE_PRINCIPAL)
            ->where('is_active', true);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(ApplicationAddon::class, 'application_id');
    }

    public function activeAddons(): HasMany
    {
        return $this->addons()->where('is_active', true);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class, 'application_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeDraft($query)
    {
        return $query->where('status', MedicalConstants::APPLICATION_STATUS_DRAFT);
    }

    public function scopeQuoted($query)
    {
        return $query->where('status', MedicalConstants::APPLICATION_STATUS_QUOTED);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', MedicalConstants::APPLICATION_STATUS_SUBMITTED);
    }

    public function scopeUnderwriting($query)
    {
        return $query->where('status', MedicalConstants::APPLICATION_STATUS_UNDERWRITING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', MedicalConstants::APPLICATION_STATUS_APPROVED);
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', MedicalConstants::APPLICATION_STATUS_ACCEPTED);
    }

    public function scopeConverted($query)
    {
        return $query->where('status', MedicalConstants::APPLICATION_STATUS_CONVERTED);
    }

    public function scopePendingUnderwriting($query)
    {
        return $query->whereIn('status', [
            MedicalConstants::APPLICATION_STATUS_SUBMITTED,
            MedicalConstants::APPLICATION_STATUS_UNDERWRITING,
        ]);
    }

    public function scopePendingConversion($query)
    {
        return $query->where('status', MedicalConstants::APPLICATION_STATUS_ACCEPTED);
    }

    public function scopeExpired($query)
    {
        return $query->where('quote_valid_until', '<', now())
            ->whereNotIn('status', [
                MedicalConstants::APPLICATION_STATUS_CONVERTED,
                MedicalConstants::APPLICATION_STATUS_EXPIRED,
            ]);
    }

    public function scopeValidQuotes($query)
    {
        return $query->where('quote_valid_until', '>=', now())
            ->whereIn('status', [
                MedicalConstants::APPLICATION_STATUS_QUOTED,
                MedicalConstants::APPLICATION_STATUS_APPROVED,
                MedicalConstants::APPLICATION_STATUS_ACCEPTED,
            ]);
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

    public function scopeNewBusiness($query)
    {
        return $query->where('application_type', MedicalConstants::APPLICATION_TYPE_NEW);
    }

    public function scopeRenewals($query)
    {
        return $query->where('application_type', MedicalConstants::APPLICATION_TYPE_RENEWAL);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getStatusLabelAttribute(): string
    {
        return MedicalConstants::APPLICATION_STATUSES[$this->status] ?? $this->status;
    }

    public function getApplicationTypeLabelAttribute(): string
    {
        return MedicalConstants::APPLICATION_TYPES[$this->application_type] ?? $this->application_type;
    }

    public function getPolicyTypeLabelAttribute(): string
    {
        return MedicalConstants::POLICY_TYPES[$this->policy_type] ?? $this->policy_type;
    }

    public function getUnderwritingStatusLabelAttribute(): string
    {
        if (!$this->underwriting_status) return 'N/A';
        return MedicalConstants::UW_STATUSES[$this->underwriting_status] ?? $this->underwriting_status;
    }

    public function getBillingFrequencyLabelAttribute(): string
    {
        return MedicalConstants::BILLING_FREQUENCIES[$this->billing_frequency] ?? $this->billing_frequency;
    }

    public function getIsDraftAttribute(): bool
    {
        return $this->status === MedicalConstants::APPLICATION_STATUS_DRAFT;
    }

    public function getIsQuotedAttribute(): bool
    {
        return $this->status === MedicalConstants::APPLICATION_STATUS_QUOTED;
    }

    public function getIsSubmittedAttribute(): bool
    {
        return $this->status === MedicalConstants::APPLICATION_STATUS_SUBMITTED;
    }

    public function getIsUnderwritingAttribute(): bool
    {
        return $this->status === MedicalConstants::APPLICATION_STATUS_UNDERWRITING;
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->status === MedicalConstants::APPLICATION_STATUS_APPROVED;
    }

    public function getIsDeclinedAttribute(): bool
    {
        return $this->status === MedicalConstants::APPLICATION_STATUS_DECLINED;
    }

    public function getIsAcceptedAttribute(): bool
    {
        return $this->status === MedicalConstants::APPLICATION_STATUS_ACCEPTED;
    }

    public function getIsConvertedAttribute(): bool
    {
        return $this->status === MedicalConstants::APPLICATION_STATUS_CONVERTED;
    }

    public function getIsExpiredAttribute(): bool
    {
        if ($this->status === MedicalConstants::APPLICATION_STATUS_EXPIRED) {
            return true;
        }
        return $this->quote_valid_until && $this->quote_valid_until->isPast();
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

    public function getIsRenewalAttribute(): bool
    {
        return $this->application_type === MedicalConstants::APPLICATION_TYPE_RENEWAL;
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->quote_valid_until) return null;
        return max(0, now()->diffInDays($this->quote_valid_until, false));
    }

    public function getCanBeEditedAttribute(): bool
    {
        return in_array($this->status, [
            MedicalConstants::APPLICATION_STATUS_DRAFT,
            MedicalConstants::APPLICATION_STATUS_QUOTED,
        ]);
    }

    public function getCanBeSubmittedAttribute(): bool
    {
        return $this->status === MedicalConstants::APPLICATION_STATUS_QUOTED
            && $this->member_count > 0
            && !$this->is_expired;
    }

    public function getCanBeUnderwrittenAttribute(): bool
    {
        return in_array($this->status, [
            MedicalConstants::APPLICATION_STATUS_SUBMITTED,
            MedicalConstants::APPLICATION_STATUS_UNDERWRITING,
        ]);
    }

    public function getCanBeAcceptedAttribute(): bool
    {
        return $this->status === MedicalConstants::APPLICATION_STATUS_APPROVED
            && !$this->is_expired;
    }

    public function getCanBeConvertedAttribute(): bool
    {
        return $this->status === MedicalConstants::APPLICATION_STATUS_ACCEPTED
            && !$this->is_expired;
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

    public function getApplicantNameAttribute(): string
    {
        if ($this->is_corporate && $this->group) {
            return $this->group->name;
        }
        
        if ($this->contact_name) {
            return $this->contact_name;
        }

        $principal = $this->principals()->first();
        return $principal ? $principal->full_name : 'Unknown';
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Update member counts from actual members.
     */
    public function updateMemberCounts(): void
    {
        $this->member_count = $this->activeMembers()->count();
        $this->principal_count = $this->principals()->count();
        $this->dependent_count = $this->dependents()->count();
        $this->save();
    }

    /**
     * Calculate and update premiums.
     */
    public function recalculatePremiums(): void
    {
        // Base premium from members
        $this->base_premium = $this->activeMembers()->sum('base_premium');
        
        // Loading from members
        $this->loading_amount = $this->activeMembers()->sum('loading_amount');
        
        // Addon premium
        $this->addon_premium = $this->activeAddons()->sum('premium');
        
        // Calculate totals
        $this->total_premium = $this->base_premium + $this->addon_premium + $this->loading_amount - $this->discount_amount;
        
        // Tax (configurable)
        $taxRate = config('medical.tax_rate', 0);
        $this->tax_amount = round($this->total_premium * $taxRate, 2);
        
        $this->gross_premium = $this->total_premium + $this->tax_amount;
        
        $this->save();
    }

    /**
     * Mark as quoted.
     */
    public function markAsQuoted(): bool
    {
        if ($this->member_count === 0) {
            return false;
        }

        $this->status = MedicalConstants::APPLICATION_STATUS_QUOTED;
        $this->quoted_at = now();
        $this->quote_valid_until = now()->addDays(30);
        
        return $this->save();
    }

    /**
     * Submit for underwriting.
     */
    public function submit(): bool
    {
        if (!$this->can_be_submitted) {
            return false;
        }

        $this->status = MedicalConstants::APPLICATION_STATUS_SUBMITTED;
        $this->submitted_at = now();
        
        return $this->save();
    }

    /**
     * Start underwriting process.
     */
    public function startUnderwriting(string $underwriterId): bool
    {
        if (!$this->can_be_underwritten) {
            return false;
        }

        $this->status = MedicalConstants::APPLICATION_STATUS_UNDERWRITING;
        $this->underwriting_status = MedicalConstants::UW_STATUS_IN_PROGRESS;
        $this->underwriter_id = $underwriterId;
        $this->underwriting_started_at = now();
        
        return $this->save();
    }

    /**
     * Approve the application.
     */
    public function approve(string $underwriterId, ?string $notes = null): bool
    {
        $this->status = MedicalConstants::APPLICATION_STATUS_APPROVED;
        $this->underwriting_status = MedicalConstants::UW_STATUS_APPROVED;
        $this->underwriter_id = $underwriterId;
        $this->underwriting_completed_at = now();
        $this->underwriting_notes = $notes;
        
        // Extend quote validity after approval
        $this->quote_valid_until = now()->addDays(14);
        
        return $this->save();
    }

    /**
     * Decline the application.
     */
    public function decline(string $underwriterId, string $reason): bool
    {
        $this->status = MedicalConstants::APPLICATION_STATUS_DECLINED;
        $this->underwriting_status = MedicalConstants::UW_STATUS_DECLINED;
        $this->underwriter_id = $underwriterId;
        $this->underwriting_completed_at = now();
        $this->underwriting_notes = $reason;
        
        return $this->save();
    }

    /**
     * Refer for further review.
     */
    public function refer(string $underwriterId, string $reason): bool
    {
        $this->status = MedicalConstants::APPLICATION_STATUS_REFERRED;
        $this->underwriting_status = MedicalConstants::UW_STATUS_REFERRED;
        $this->underwriter_id = $underwriterId;
        $this->underwriting_notes = $reason;
        
        return $this->save();
    }

    /**
     * Customer accepts the quote.
     */
    public function accept(?string $acceptanceReference = null): bool
    {
        if (!$this->can_be_accepted) {
            return false;
        }

        $this->status = MedicalConstants::APPLICATION_STATUS_ACCEPTED;
        $this->accepted_at = now();
        $this->acceptance_reference = $acceptanceReference;
        
        return $this->save();
    }

    /**
     * Mark as converted (called after policy is created).
     */
    public function markAsConverted(string $policyId, string $convertedBy): bool
    {
        $this->status = MedicalConstants::APPLICATION_STATUS_CONVERTED;
        $this->converted_policy_id = $policyId;
        $this->converted_at = now();
        $this->converted_by = $convertedBy;
        
        return $this->save();
    }

    /**
     * Mark as expired.
     */
    public function markAsExpired(): bool
    {
        $this->status = MedicalConstants::APPLICATION_STATUS_EXPIRED;
        $this->expired_at = now();
        
        return $this->save();
    }

    /**
     * Cancel the application.
     */
    public function cancel(?string $reason = null): bool
    {
        $this->status = MedicalConstants::APPLICATION_STATUS_CANCELLED;
        $this->notes = $reason;
        
        return $this->save();
    }

    /**
     * Get the principal member (for individual/family).
     */
    public function getPrincipalMember(): ?ApplicationMember
    {
        return $this->principals()->first();
    }
}