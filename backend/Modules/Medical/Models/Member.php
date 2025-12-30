<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Modules\Medical\Constants\MedicalConstants;

class Member extends BaseModel
{
    protected $table = 'med_members';

    protected $fillable = [
        'member_number',
        'policy_id',
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
        'requires_special_underwriting',
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
        'status_changed_at' => 'date',
        'terminated_at' => 'date',
        'has_pre_existing_conditions' => 'boolean',
        'is_chronic_patient' => 'boolean',
        'requires_special_underwriting' => 'boolean',
        'declared_conditions' => 'array',
        'has_portal_access' => 'boolean',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'premium' => 0,
        'loading_amount' => 0,
        'card_status' => MedicalConstants::CARD_STATUS_PENDING,
        'status' => MedicalConstants::MEMBER_STATUS_PENDING,
        'has_pre_existing_conditions' => false,
        'is_chronic_patient' => false,
        'requires_special_underwriting' => false,
        'has_portal_access' => false,
    ];

    // =========================================================================
    // BOOT - Auto-generate member number
    // =========================================================================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $member) {
            if (empty($member->member_number)) {
                $member->member_number = $member->generateMemberNumber();
            }
        });

        static::created(function (self $member) {
            $member->policy?->updateMemberCounts();
        });

        static::deleted(function (self $member) {
            $member->policy?->updateMemberCounts();
        });
    }

    protected function generateMemberNumber(): string
    {
        $prefix = MedicalConstants::PREFIX_MEMBER;
        $year = date('Y');
        $sequence = static::whereYear('created_at', $year)->count() + 1;
        
        return sprintf('%s-%s-%06d', $prefix, $year, $sequence);
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'principal_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(self::class, 'principal_id');
    }

    public function loadings(): HasMany
    {
        return $this->hasMany(MemberLoading::class, 'member_id');
    }

    public function activeLoadings(): HasMany
    {
        return $this->hasMany(MemberLoading::class, 'member_id')
                    ->where('status', 'active');
    }

    public function exclusions(): HasMany
    {
        return $this->hasMany(MemberExclusion::class, 'member_id');
    }

    public function activeExclusions(): HasMany
    {
        return $this->hasMany(MemberExclusion::class, 'member_id')
                    ->where('status', 'active');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MemberDocument::class, 'member_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopePrincipals($query)
    {
        return $query->where('member_type', MedicalConstants::MEMBER_TYPE_PRINCIPAL);
    }

    public function scopeDependents($query)
    {
        return $query->where('member_type', '!=', MedicalConstants::MEMBER_TYPE_PRINCIPAL);
    }

    public function scopeSpouses($query)
    {
        return $query->where('member_type', MedicalConstants::MEMBER_TYPE_SPOUSE);
    }

    public function scopeChildren($query)
    {
        return $query->where('member_type', MedicalConstants::MEMBER_TYPE_CHILD);
    }

    public function scopeActive($query)
    {
        return $query->where('status', MedicalConstants::MEMBER_STATUS_ACTIVE);
    }

    public function scopePending($query)
    {
        return $query->where('status', MedicalConstants::MEMBER_STATUS_PENDING);
    }

    public function scopeWithCover($query, $date = null)
    {
        $date = $date ?? now()->toDateString();

        return $query->where('cover_start_date', '<=', $date)
                     ->where(function ($q) use ($date) {
                         $q->whereNull('cover_end_date')
                           ->orWhere('cover_end_date', '>=', $date);
                     });
    }

    public function scopeInWaitingPeriod($query)
    {
        return $query->whereNotNull('waiting_period_end_date')
                     ->where('waiting_period_end_date', '>', now());
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('member_number', 'LIKE', "%{$term}%")
              ->orWhere('first_name', 'LIKE', "%{$term}%")
              ->orWhere('last_name', 'LIKE', "%{$term}%")
              ->orWhere('national_id', 'LIKE', "%{$term}%")
              ->orWhere('email', 'LIKE', "%{$term}%")
              ->orWhere('phone', 'LIKE', "%{$term}%")
              ->orWhere('mobile', 'LIKE', "%{$term}%")
              ->orWhere('card_number', 'LIKE', "%{$term}%");
        });
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
        return "{$this->first_name} {$this->last_name}";
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(
            substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1)
        );
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }

    public function getAgeBandAttribute(): string
    {
        $age = $this->age;

        if ($age < 18) return '0-17';
        if ($age <= 30) return '18-30';
        if ($age <= 40) return '31-40';
        if ($age <= 50) return '41-50';
        if ($age <= 60) return '51-60';
        if ($age <= 70) return '61-70';
        return '71+';
    }

    public function getStatusLabelAttribute(): string
    {
        return MedicalConstants::MEMBER_STATUSES[$this->status] ?? $this->status;
    }

    public function getMemberTypeLabelAttribute(): string
    {
        return MedicalConstants::MEMBER_TYPES[$this->member_type] ?? $this->member_type;
    }

    public function getCardStatusLabelAttribute(): string
    {
        return MedicalConstants::CARD_STATUSES[$this->card_status] ?? $this->card_status;
    }

    public function getGenderLabelAttribute(): string
    {
        return $this->gender === 'M' ? 'Male' : 'Female';
    }

    public function getIsPrincipalAttribute(): bool
    {
        return $this->member_type === MedicalConstants::MEMBER_TYPE_PRINCIPAL;
    }

    public function getIsDependentAttribute(): bool
    {
        return $this->member_type !== MedicalConstants::MEMBER_TYPE_PRINCIPAL;
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === MedicalConstants::MEMBER_STATUS_ACTIVE;
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === MedicalConstants::MEMBER_STATUS_PENDING;
    }

    public function getIsInWaitingPeriodAttribute(): bool
    {
        return $this->waiting_period_end_date && $this->waiting_period_end_date->isFuture();
    }

    public function getWaitingDaysRemainingAttribute(): int
    {
        if (!$this->waiting_period_end_date) {
            return 0;
        }

        return max(0, now()->diffInDays($this->waiting_period_end_date, false));
    }

    public function getHasCoverAttribute(): bool
    {
        $today = now()->toDateString();

        if ($this->cover_start_date > $today) {
            return false;
        }

        if ($this->cover_end_date && $this->cover_end_date < $today) {
            return false;
        }

        return $this->is_active;
    }

    public function getTotalPremiumAttribute(): float
    {
        return $this->premium + $this->loading_amount;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function activate(): bool
    {
        $this->status = MedicalConstants::MEMBER_STATUS_ACTIVE;
        $this->status_changed_at = now();
        
        return $this->save();
    }

    public function suspend(string $reason): bool
    {
        $this->status = MedicalConstants::MEMBER_STATUS_SUSPENDED;
        $this->status_changed_at = now();
        $this->status_reason = $reason;
        
        return $this->save();
    }

    public function terminate(string $reason, ?string $notes = null): bool
    {
        $this->status = MedicalConstants::MEMBER_STATUS_TERMINATED;
        $this->status_changed_at = now();
        $this->terminated_at = now();
        $this->termination_reason = $reason;
        $this->termination_notes = $notes;
        $this->cover_end_date = now();
        $this->card_status = MedicalConstants::CARD_STATUS_BLOCKED;
        
        return $this->save();
    }

    public function markDeceased(?string $notes = null): bool
    {
        $this->status = MedicalConstants::MEMBER_STATUS_DECEASED;
        $this->status_changed_at = now();
        $this->terminated_at = now();
        $this->termination_reason = 'deceased';
        $this->termination_notes = $notes;
        $this->cover_end_date = now();
        $this->card_status = MedicalConstants::CARD_STATUS_BLOCKED;
        
        return $this->save();
    }

    public function issueCard(): bool
    {
        $this->card_number = $this->generateCardNumber();
        $this->card_issued_date = now();
        $this->card_expiry_date = $this->policy->expiry_date;
        $this->card_status = MedicalConstants::CARD_STATUS_ISSUED;
        
        return $this->save();
    }

    public function activateCard(): bool
    {
        if ($this->card_status !== MedicalConstants::CARD_STATUS_ISSUED) {
            return false;
        }

        $this->card_status = MedicalConstants::CARD_STATUS_ACTIVE;
        
        return $this->save();
    }

    public function blockCard(string $reason): bool
    {
        $this->card_status = MedicalConstants::CARD_STATUS_BLOCKED;
        
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Card blocked: {$reason}";
        }
        
        return $this->save();
    }

    protected function generateCardNumber(): string
    {
        $prefix = substr($this->policy->policy_number, -6);
        $sequence = $this->policy->members()->whereNotNull('card_number')->count() + 1;
        
        return sprintf('%s-%04d', $prefix, $sequence);
    }

    public function setWaitingPeriod(int $days): void
    {
        $this->waiting_period_end_date = $this->cover_start_date->copy()->addDays($days);
    }

    public function calculateWaitingPeriodEndDate(): ?Carbon
    {
        $plan = $this->policy->plan;
        
        if (!$plan) {
            return null;
        }

        $waitingDays = $plan->getWaitingPeriodDays(MedicalConstants::WAITING_TYPE_GENERAL);
        
        return $this->cover_start_date->copy()->addDays($waitingDays);
    }

    public function canMakeClaim(): bool
    {
        return $this->is_active 
            && $this->has_cover 
            && !$this->is_in_waiting_period;
    }

    public function addDependent(array $data): self
    {
        if (!$this->is_principal) {
            throw new \Exception('Only principals can add dependents');
        }

        $data['policy_id'] = $this->policy_id;
        $data['principal_id'] = $this->id;
        $data['cover_start_date'] = $data['cover_start_date'] ?? now();

        return static::create($data);
    }

    public function isEligibleForBenefit(string $benefitId): bool
    {
        return !$this->activeExclusions()
                     ->where('benefit_id', $benefitId)
                     ->exists();
    }
}