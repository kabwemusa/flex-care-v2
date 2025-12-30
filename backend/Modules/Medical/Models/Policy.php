<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Medical\Constants\MedicalConstants;

class Policy extends BaseModel
{
    protected $table = 'med_policies';

    protected $fillable = [
        'policy_number',
        'scheme_id',
        'plan_id',
        'rate_card_id',
        'policy_type',
        'group_id',
        'principal_member_id',
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
        'cancelled_at',
        'cancellation_reason',
        'cancellation_notes',
        'cancelled_by',
        'previous_policy_id',
        'renewed_to_policy_id',
        'renewal_count',
        'sales_agent_id',
        'broker_id',
        'commission_rate',
        'commission_amount',
        'promo_code_id',
        'applied_discounts',
        'applied_loadings',
        'source',
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
        'cancelled_at' => 'date',
        'renewal_count' => 'integer',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'applied_discounts' => 'array',
        'applied_loadings' => 'array',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'policy_term_months' => MedicalConstants::DEFAULT_POLICY_TERM_MONTHS,
        'is_auto_renew' => true,
        'currency' => MedicalConstants::DEFAULT_CURRENCY,
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
        'status' => MedicalConstants::POLICY_STATUS_DRAFT,
        'underwriting_status' => MedicalConstants::UW_STATUS_PENDING,
        'renewal_count' => 0,
    ];

    // =========================================================================
    // BOOT - Auto-generate policy number
    // =========================================================================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $policy) {
            if (empty($policy->policy_number)) {
                $policy->policy_number = $policy->generatePolicyNumber();
            }
        });
    }

    protected function generatePolicyNumber(): string
    {
        $prefix = MedicalConstants::PREFIX_POLICY;
        $year = date('Y');
        $sequence = static::whereYear('created_at', $year)->count() + 1;
        
        return sprintf('%s-%s-%06d', $prefix, $year, $sequence);
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

    public function principalMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'principal_member_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'policy_id');
    }

    public function activeMembers(): HasMany
    {
        return $this->hasMany(Member::class, 'policy_id')
                    ->where('status', MedicalConstants::MEMBER_STATUS_ACTIVE);
    }

    public function principals(): HasMany
    {
        return $this->hasMany(Member::class, 'policy_id')
                    ->where('member_type', MedicalConstants::MEMBER_TYPE_PRINCIPAL);
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(Member::class, 'policy_id')
                    ->where('member_type', '!=', MedicalConstants::MEMBER_TYPE_PRINCIPAL);
    }

    public function policyAddons(): HasMany
    {
        return $this->hasMany(PolicyAddon::class, 'policy_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PolicyDocument::class, 'policy_id');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }

    public function previousPolicy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_policy_id');
    }

    public function renewedPolicy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_to_policy_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeDraft($query)
    {
        return $query->where('status', MedicalConstants::POLICY_STATUS_DRAFT);
    }

    public function scopeActive($query)
    {
        return $query->where('status', MedicalConstants::POLICY_STATUS_ACTIVE);
    }

    public function scopePendingPayment($query)
    {
        return $query->where('status', MedicalConstants::POLICY_STATUS_PENDING_PAYMENT);
    }

    public function scopeCorporate($query)
    {
        return $query->where('policy_type', MedicalConstants::POLICY_TYPE_CORPORATE);
    }

    public function scopeIndividual($query)
    {
        return $query->whereIn('policy_type', [
            MedicalConstants::POLICY_TYPE_INDIVIDUAL,
            MedicalConstants::POLICY_TYPE_FAMILY,
        ]);
    }

    public function scopeExpiringWithin($query, int $days)
    {
        return $query->where('status', MedicalConstants::POLICY_STATUS_ACTIVE)
                     ->whereBetween('expiry_date', [now(), now()->addDays($days)]);
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now())
                     ->where('status', '!=', MedicalConstants::POLICY_STATUS_RENEWED);
    }

    public function scopeForRenewal($query)
    {
        return $query->where('status', MedicalConstants::POLICY_STATUS_ACTIVE)
                     ->where('is_auto_renew', true)
                     ->where('expiry_date', '<=', now()->addDays(30));
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('policy_number', 'LIKE', "%{$term}%")
              ->orWhereHas('members', fn($m) => 
                  $m->where('member_number', 'LIKE', "%{$term}%")
                    ->orWhere('first_name', 'LIKE', "%{$term}%")
                    ->orWhere('last_name', 'LIKE', "%{$term}%")
                    ->orWhere('national_id', 'LIKE', "%{$term}%")
              )
              ->orWhereHas('group', fn($g) => 
                  $g->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('code', 'LIKE', "%{$term}%")
              );
        });
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getStatusLabelAttribute(): string
    {
        return MedicalConstants::POLICY_STATUSES[$this->status] ?? $this->status;
    }

    public function getUnderwritingStatusLabelAttribute(): string
    {
        return MedicalConstants::UW_STATUSES[$this->underwriting_status] ?? $this->underwriting_status;
    }

    public function getPolicyTypeLabelAttribute(): string
    {
        return MedicalConstants::POLICY_TYPES[$this->policy_type] ?? $this->policy_type;
    }

    public function getBillingFrequencyLabelAttribute(): string
    {
        return MedicalConstants::BILLING_FREQUENCIES[$this->billing_frequency] ?? $this->billing_frequency;
    }

    public function getIsDraftAttribute(): bool
    {
        return $this->status === MedicalConstants::POLICY_STATUS_DRAFT;
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === MedicalConstants::POLICY_STATUS_ACTIVE;
    }

    public function getIsCorporateAttribute(): bool
    {
        return $this->policy_type === MedicalConstants::POLICY_TYPE_CORPORATE;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date->isPast();
    }

    public function getIsExpiringAttribute(): bool
    {
        return $this->expiry_date->between(now(), now()->addDays(30));
    }

    public function getDaysToExpiryAttribute(): int
    {
        return max(0, now()->diffInDays($this->expiry_date, false));
    }

    public function getPolicyHolderNameAttribute(): string
    {
        if ($this->is_corporate && $this->group) {
            return $this->group->display_name;
        }

        return $this->principalMember?->full_name ?? 'N/A';
    }

    public function getMonthlyPremiumAttribute(): float
    {
        return match($this->billing_frequency) {
            MedicalConstants::BILLING_MONTHLY => $this->gross_premium,
            MedicalConstants::BILLING_QUARTERLY => round($this->gross_premium / 3, 2),
            MedicalConstants::BILLING_SEMI_ANNUAL => round($this->gross_premium / 6, 2),
            MedicalConstants::BILLING_ANNUAL => round($this->gross_premium / 12, 2),
            default => $this->gross_premium,
        };
    }

    public function getAnnualPremiumAttribute(): float
    {
        return match($this->billing_frequency) {
            MedicalConstants::BILLING_MONTHLY => $this->gross_premium * 12,
            MedicalConstants::BILLING_QUARTERLY => $this->gross_premium * 4,
            MedicalConstants::BILLING_SEMI_ANNUAL => $this->gross_premium * 2,
            MedicalConstants::BILLING_ANNUAL => $this->gross_premium,
            default => $this->gross_premium,
        };
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function calculatePremium(): void
    {
        $this->total_premium = $this->base_premium + $this->addon_premium + $this->loading_amount - $this->discount_amount;
        // Tax calculation would go here based on business rules
        $this->gross_premium = $this->total_premium + $this->tax_amount;
    }

    public function updateMemberCounts(): void
    {
        $this->member_count = $this->members()->count();
        $this->principal_count = $this->principals()->count();
        $this->dependent_count = $this->dependents()->count();
        $this->save();
    }

    public function activate(): bool
    {
        if (!$this->canActivate()) {
            return false;
        }

        $this->status = MedicalConstants::POLICY_STATUS_ACTIVE;
        
        return $this->save();
    }

    public function canActivate(): bool
    {
        return in_array($this->status, [
            MedicalConstants::POLICY_STATUS_DRAFT,
            MedicalConstants::POLICY_STATUS_PENDING_PAYMENT,
        ]) && $this->underwriting_status === MedicalConstants::UW_STATUS_APPROVED;
    }

    public function suspend(string $reason): bool
    {
        $this->status = MedicalConstants::POLICY_STATUS_SUSPENDED;
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Suspended: {$reason}";
        }
        
        return $this->save();
    }

    public function cancel(string $reason, ?string $cancelledBy = null): bool
    {
        $this->status = MedicalConstants::POLICY_STATUS_CANCELLED;
        $this->cancelled_at = now();
        $this->cancellation_reason = $reason;
        $this->cancelled_by = $cancelledBy;
        
        return $this->save();
    }

    public function markExpired(): bool
    {
        $this->status = MedicalConstants::POLICY_STATUS_EXPIRED;
        
        return $this->save();
    }

    public function approve(?string $approvedBy = null, string $notes): bool
    {
        $this->underwriting_status = MedicalConstants::UW_STATUS_APPROVED;
        $this->underwritten_by = $approvedBy;
        $this->underwritten_at = now();
        
        if ($notes) {
            $this->underwriting_notes = $notes;
        }
        
        return $this->save();
    }

    public function decline(string $reason, ?string $declinedBy = null): bool
    {
        $this->underwriting_status = MedicalConstants::UW_STATUS_DECLINED;
        $this->underwriting_notes = $reason;
        $this->underwritten_by = $declinedBy;
        $this->underwritten_at = now();
        
        return $this->save();
    }

    public function refer(string $reason, ?string $referredBy = null): bool
    {
        $this->underwriting_status = MedicalConstants::UW_STATUS_REFERRED;
        $this->underwriting_notes = $reason;
        $this->underwritten_by = $referredBy;
        $this->underwritten_at = now();
        
        return $this->save();
    }

    public function canAddMember(): bool
    {
        return in_array($this->status, [
            MedicalConstants::POLICY_STATUS_DRAFT,
            MedicalConstants::POLICY_STATUS_ACTIVE,
        ]);
    }

    public function canBeRenewed(): bool
    {
        return $this->status === MedicalConstants::POLICY_STATUS_ACTIVE
            && $this->renewed_to_policy_id === null;
    }
}