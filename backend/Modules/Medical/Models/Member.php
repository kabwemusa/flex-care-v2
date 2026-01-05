<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Medical\Constants\MedicalConstants;

class Member extends BaseModel
{
    protected $table = 'med_members';

    protected $fillable = [
        'member_number',
        'policy_id',
        'application_member_id',
        'member_type',
        'principal_id',
        'relationship',
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        'marital_status',
        'national_id',
        'passport_number',
        'employee_number',
        'email',
        'phone',
        'mobile',
        'address',
        'city',
        'region_code',
        'job_title',
        'department',
        'employment_date',
        'salary',
        'salary_band',
        'cover_start_date',
        'cover_end_date',
        'waiting_period_end_date',
        'premium',
        'loading_amount',
        'card_number',
        'card_issued_date',
        'card_expiry_date',
        'card_status',
        'status',
        'status_changed_at',
        'status_reason',
        'terminated_at',
        'termination_reason',
        'termination_notes',
        'has_pre_existing_conditions',
        'is_chronic_patient',
        'declared_conditions',
        'has_portal_access',
        'user_id',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'employment_date' => 'date',
        'salary' => 'decimal:2',
        'cover_start_date' => 'date',
        'cover_end_date' => 'date',
        'waiting_period_end_date' => 'date',
        'premium' => 'decimal:2',
        'loading_amount' => 'decimal:2',
        'card_issued_date' => 'date',
        'card_expiry_date' => 'date',
        'status_changed_at' => 'datetime',
        'terminated_at' => 'date',
        'has_pre_existing_conditions' => 'boolean',
        'is_chronic_patient' => 'boolean',
        'declared_conditions' => 'array',
        'has_portal_access' => 'boolean',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'status' => MedicalConstants::MEMBER_STATUS_ACTIVE,
        'card_status' => MedicalConstants::CARD_STATUS_PENDING,
        'has_pre_existing_conditions' => false,
        'is_chronic_patient' => false,
        'has_portal_access' => false,
        'premium' => 0,
        'loading_amount' => 0,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected static function booted()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->member_number)) {
                $model->member_number = $model->generateMemberNumber();
            }
        });

        static::saved(function ($model) {
            if ($model->policy) {
                $model->policy->updateMemberCounts();
            }
        });

        static::deleted(function ($model) {
            if ($model->policy) {
                $model->policy->updateMemberCounts();
            }
        });
    }

    protected function generateMemberNumber(): string
    {
        $prefix = MedicalConstants::PREFIX_MEMBER;
        $year = date('Y');
        
        $lastNumber = static::where('member_number', 'like', "{$prefix}{$year}-%")
            ->orderByRaw('CAST(SUBSTRING(member_number, -6) AS UNSIGNED) DESC')
            ->value('member_number');

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

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }

    public function applicationMember(): BelongsTo
    {
        return $this->belongsTo(ApplicationMember::class, 'application_member_id');
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'principal_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(self::class, 'principal_id');
    }

    public function activeDependents(): HasMany
    {
        return $this->dependents()->where('status', MedicalConstants::MEMBER_STATUS_ACTIVE);
    }

    public function loadings(): HasMany
    {
        return $this->hasMany(MemberLoading::class, 'member_id');
    }

    public function activeLoadings(): HasMany
    {
        return $this->loadings()->where('status', 'active');
    }

    public function exclusions(): HasMany
    {
        return $this->hasMany(MemberExclusion::class, 'member_id');
    }

    public function activeExclusions(): HasMany
    {
        return $this->exclusions()->where('status', 'active');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MemberDocument::class, 'member_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('status', MedicalConstants::MEMBER_STATUS_ACTIVE);
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', MedicalConstants::MEMBER_STATUS_SUSPENDED);
    }

    public function scopeTerminated($query)
    {
        return $query->where('status', MedicalConstants::MEMBER_STATUS_TERMINATED);
    }

    public function scopePrincipals($query)
    {
        return $query->where('member_type', MedicalConstants::MEMBER_TYPE_PRINCIPAL);
    }

    public function scopeDependents($query)
    {
        return $query->where('member_type', '!=', MedicalConstants::MEMBER_TYPE_PRINCIPAL);
    }

    public function scopeWithActiveCover($query)
    {
        return $query->where('status', MedicalConstants::MEMBER_STATUS_ACTIVE)
            ->where('cover_start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('cover_end_date')
                  ->orWhere('cover_end_date', '>=', now());
            });
    }

    public function scopeInWaitingPeriod($query)
    {
        return $query->whereNotNull('waiting_period_end_date')
            ->where('waiting_period_end_date', '>', now());
    }

    public function scopeCardPending($query)
    {
        return $query->where('card_status', MedicalConstants::CARD_STATUS_PENDING);
    }

    public function scopeCardActive($query)
    {
        return $query->where('card_status', MedicalConstants::CARD_STATUS_ACTIVE);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->title,
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ]);
        return implode(' ', $parts);
    }

    public function getShortNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getInitialsAttribute(): string
    {
        $first = $this->first_name ? strtoupper(substr($this->first_name, 0, 1)) : '';
        $last = $this->last_name ? strtoupper(substr($this->last_name, 0, 1)) : '';
        return $first . $last;
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth ? $this->date_of_birth->age : 0;
    }

    public function getAgeBandAttribute(): string
    {
        $age = $this->age;
        
        if ($age < 1) return '0-1';
        if ($age <= 17) return '1-17';
        if ($age <= 25) return '18-25';
        if ($age <= 30) return '26-30';
        if ($age <= 35) return '31-35';
        if ($age <= 40) return '36-40';
        if ($age <= 45) return '41-45';
        if ($age <= 50) return '46-50';
        if ($age <= 55) return '51-55';
        if ($age <= 60) return '56-60';
        if ($age <= 65) return '61-65';
        return '65+';
    }

    public function getMemberTypeLabelAttribute(): string
    {
        return MedicalConstants::MEMBER_TYPES[$this->member_type] ?? $this->member_type;
    }

    public function getRelationshipLabelAttribute(): ?string
    {
        if (!$this->relationship) return null;
        return MedicalConstants::RELATIONSHIPS[$this->relationship] ?? $this->relationship;
    }

    public function getStatusLabelAttribute(): string
    {
        return MedicalConstants::MEMBER_STATUSES[$this->status] ?? $this->status;
    }

    public function getCardStatusLabelAttribute(): string
    {
        return MedicalConstants::CARD_STATUSES[$this->card_status] ?? $this->card_status;
    }

    public function getGenderLabelAttribute(): string
    {
        return match($this->gender) {
            'M' => 'Male',
            'F' => 'Female',
            default => $this->gender,
        };
    }

    public function getIsPrincipalAttribute(): bool
    {
        return $this->member_type === MedicalConstants::MEMBER_TYPE_PRINCIPAL;
    }

    public function getIsDependentAttribute(): bool
    {
        return !$this->is_principal;
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === MedicalConstants::MEMBER_STATUS_ACTIVE;
    }

    public function getIsSuspendedAttribute(): bool
    {
        return $this->status === MedicalConstants::MEMBER_STATUS_SUSPENDED;
    }

    public function getIsTerminatedAttribute(): bool
    {
        return $this->status === MedicalConstants::MEMBER_STATUS_TERMINATED;
    }

    public function getHasCoverAttribute(): bool
    {
        return $this->is_active
            && $this->cover_start_date <= now()
            && ($this->cover_end_date === null || $this->cover_end_date >= now());
    }

    public function getIsInWaitingPeriodAttribute(): bool
    {
        return $this->waiting_period_end_date !== null
            && $this->waiting_period_end_date > now();
    }

    public function getWaitingDaysRemainingAttribute(): int
    {
        if (!$this->is_in_waiting_period) return 0;
        return max(0, now()->diffInDays($this->waiting_period_end_date, false));
    }

    public function getTotalPremiumAttribute(): float
    {
        return (float) $this->premium + (float) $this->loading_amount;
    }

    public function getDependentCountAttribute(): int
    {
        return $this->activeDependents()->count();
    }

    public function getCanMakeClaimAttribute(): bool
    {
        return $this->has_cover
            && !$this->is_in_waiting_period
            && $this->card_status === MedicalConstants::CARD_STATUS_ACTIVE;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Suspend the member.
     */
    public function suspend(string $reason, bool $includeDependents = false): bool
    {
        $this->status = MedicalConstants::MEMBER_STATUS_SUSPENDED;
        $this->status_changed_at = now();
        $this->status_reason = $reason;
        
        if ($includeDependents && $this->is_principal) {
            $this->activeDependents()->update([
                'status' => MedicalConstants::MEMBER_STATUS_SUSPENDED,
                'status_changed_at' => now(),
                'status_reason' => 'Principal suspended',
            ]);
        }
        
        return $this->save();
    }

    /**
     * Terminate the member.
     */
    public function terminate(string $reason, ?string $notes = null): bool
    {
        $this->status = MedicalConstants::MEMBER_STATUS_TERMINATED;
        $this->terminated_at = now();
        $this->termination_reason = $reason;
        $this->termination_notes = $notes;
        $this->card_status = MedicalConstants::CARD_STATUS_BLOCKED;
        
        // Terminate dependents too if principal
        if ($this->is_principal) {
            $this->activeDependents()->update([
                'status' => MedicalConstants::MEMBER_STATUS_TERMINATED,
                'terminated_at' => now(),
                'termination_reason' => 'principal_terminated',
                'termination_notes' => 'Principal member terminated',
                'card_status' => MedicalConstants::CARD_STATUS_BLOCKED,
            ]);
        }
        
        return $this->save();
    }

    /**
     * Reactivate a suspended member.
     */
    public function reactivate(): bool
    {
        if ($this->status !== MedicalConstants::MEMBER_STATUS_SUSPENDED) {
            return false;
        }

        $this->status = MedicalConstants::MEMBER_STATUS_ACTIVE;
        $this->status_changed_at = now();
        $this->status_reason = 'Reactivated';
        
        return $this->save();
    }

    /**
     * Issue a member card.
     */
    public function issueCard(): bool
    {
        if ($this->card_number) {
            return false; // Already has card
        }

        $this->card_number = $this->generateCardNumber();
        $this->card_issued_date = now();
        $this->card_expiry_date = $this->policy?->expiry_date ?? now()->addYear();
        $this->card_status = MedicalConstants::CARD_STATUS_ISSUED;
        
        return $this->save();
    }

    /**
     * Activate the member card.
     */
    public function activateCard(): bool
    {
        if ($this->card_status !== MedicalConstants::CARD_STATUS_ISSUED) {
            return false;
        }

        $this->card_status = MedicalConstants::CARD_STATUS_ACTIVE;
        return $this->save();
    }

    /**
     * Block the member card.
     */
    public function blockCard(string $reason): bool
    {
        $this->card_status = MedicalConstants::CARD_STATUS_BLOCKED;
        $this->status_reason = $reason;
        return $this->save();
    }

    /**
     * Generate card number.
     */
    protected function generateCardNumber(): string
    {
        $prefix = 'MED';
        $year = date('y');
        $random = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $checkDigit = $this->calculateLuhnCheckDigit($prefix . $year . $random);
        
        return $prefix . $year . $random . $checkDigit;
    }

    /**
     * Calculate Luhn check digit.
     */
    protected function calculateLuhnCheckDigit(string $number): int
    {
        $sum = 0;
        $numDigits = strlen($number);
        $parity = $numDigits % 2;

        for ($i = 0; $i < $numDigits; $i++) {
            $digit = ord($number[$i]) - ord('0');
            if ($digit < 0 || $digit > 9) {
                $digit = ord(strtoupper($number[$i])) - ord('A') + 10;
            }
            if ($i % 2 === $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Recalculate loading amount from active loadings.
     */
    public function recalculateLoadings(): void
    {
        $this->loading_amount = $this->activeLoadings()->sum('loading_amount');
        $this->save();
    }

    /**
     * Check claim eligibility.
     */
    public function checkEligibility(): array
    {
        $issues = [];

        if (!$this->is_active) {
            $issues[] = 'Member is not active (status: ' . $this->status_label . ')';
        }

        if (!$this->has_cover) {
            $issues[] = 'Member does not have active cover';
        }

        if ($this->is_in_waiting_period) {
            $issues[] = 'Member is in waiting period (' . $this->waiting_days_remaining . ' days remaining)';
        }

        if ($this->card_status !== MedicalConstants::CARD_STATUS_ACTIVE) {
            $issues[] = 'Member card is not active (status: ' . $this->card_status_label . ')';
        }

        // Check policy status
        if ($this->policy && !$this->policy->is_active) {
            $issues[] = 'Policy is not active (status: ' . $this->policy->status_label . ')';
        }

        return [
            'eligible' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Create member from application member.
     */
    public static function createFromApplicationMember(
        ApplicationMember $appMember,
        Policy $policy,
        ?Member $principal = null
    ): self {
        $member = new self([
            'policy_id' => $policy->id,
            'application_member_id' => $appMember->id,
            'member_type' => $appMember->member_type,
            'principal_id' => $principal?->id,
            'relationship' => $appMember->relationship,
            'title' => $appMember->title,
            'first_name' => $appMember->first_name,
            'middle_name' => $appMember->middle_name,
            'last_name' => $appMember->last_name,
            'date_of_birth' => $appMember->date_of_birth,
            'gender' => $appMember->gender,
            'marital_status' => $appMember->marital_status,
            'national_id' => $appMember->national_id,
            'passport_number' => $appMember->passport_number,
            'employee_number' => $appMember->employee_number,
            'email' => $appMember->email,
            'phone' => $appMember->phone,
            'mobile' => $appMember->mobile,
            'address' => $appMember->address,
            'city' => $appMember->city,
            'job_title' => $appMember->job_title,
            'department' => $appMember->department,
            'employment_date' => $appMember->employment_date,
            'salary' => $appMember->salary,
            'salary_band' => $appMember->salary_band,
            'cover_start_date' => $policy->inception_date,
            'cover_end_date' => $policy->expiry_date,
            'premium' => $appMember->base_premium,
            'loading_amount' => $appMember->loading_amount,
            'has_pre_existing_conditions' => $appMember->has_pre_existing_conditions,
            'declared_conditions' => $appMember->declared_conditions,
            'status' => MedicalConstants::MEMBER_STATUS_ACTIVE,
            'card_status' => MedicalConstants::CARD_STATUS_PENDING,
        ]);

        // Calculate waiting period
        $plan = $policy->plan;
        if ($plan) {
            $waitingDays = $plan->general_waiting_period ?? MedicalConstants::DEFAULT_GENERAL_WAITING_DAYS;
            $member->waiting_period_end_date = $policy->inception_date->copy()->addDays($waitingDays);
        }

        $member->save();

        // Update application member with converted member link
        $appMember->converted_member_id = $member->id;
        $appMember->save();

        return $member;
    }
}