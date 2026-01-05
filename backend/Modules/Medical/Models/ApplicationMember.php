<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Medical\Constants\MedicalConstants;
use Carbon\Carbon;

class ApplicationMember extends BaseModel
{
    protected $table = 'med_application_members';

    protected $fillable = [
        'application_id',
        'member_type',
        'principal_member_id',
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
        'email',
        'phone',
        'mobile',
        'address',
        'city',
        'employee_number',
        'job_title',
        'department',
        'employment_date',
        'salary',
        'salary_band',
        'age_at_inception',
        'base_premium',
        'loading_amount',
        'total_premium',
        'has_pre_existing_conditions',
        'declared_conditions',
        'medical_history_notes',
        'underwriting_status',
        'applied_loadings',
        'applied_exclusions',
        'underwriting_notes',
        'underwritten_by',
        'underwritten_at',
        'converted_member_id',
        'is_active',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'employment_date' => 'date',
        'salary' => 'decimal:2',
        'age_at_inception' => 'integer',
        'base_premium' => 'decimal:2',
        'loading_amount' => 'decimal:2',
        'total_premium' => 'decimal:2',
        'has_pre_existing_conditions' => 'boolean',
        'declared_conditions' => 'array',
        'applied_loadings' => 'array',
        'applied_exclusions' => 'array',
        'underwritten_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'underwriting_status' => MedicalConstants::UW_STATUS_PENDING,
        'has_pre_existing_conditions' => false,
        'is_active' => true,
        'base_premium' => 0,
        'loading_amount' => 0,
        'total_premium' => 0,
    ];

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted()
    {
        parent::boot();

        static::creating(function ($model) {
            // Calculate age at inception
            if ($model->date_of_birth && $model->application) {
                $inceptionDate = $model->application->proposed_start_date ?? now();
                $model->age_at_inception = $model->date_of_birth->diffInYears($inceptionDate);
            }
        });

        static::saved(function ($model) {
            // Update application counts and premiums
            if ($model->application) {
                $model->application->updateMemberCounts();
                $model->application->recalculatePremiums();
            }
        });

        static::deleted(function ($model) {
            if ($model->application) {
                $model->application->updateMemberCounts();
                $model->application->recalculatePremiums();
            }
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'principal_member_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(self::class, 'principal_member_id');
    }

    public function activeDependents(): HasMany
    {
        return $this->dependents()->where('is_active', true);
    }

    public function convertedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'converted_member_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class, 'application_member_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

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

    public function scopePendingUnderwriting($query)
    {
        return $query->where('underwriting_status', MedicalConstants::UW_STATUS_PENDING);
    }

    public function scopeUnderwritten($query)
    {
        return $query->whereIn('underwriting_status', [
            MedicalConstants::UW_STATUS_APPROVED,
            MedicalConstants::UW_STATUS_DECLINED,
            MedicalConstants::UW_STATUS_TERMS,
        ]);
    }

    public function scopeWithLoadings($query)
    {
        return $query->whereNotNull('applied_loadings')
            ->whereRaw("JSON_LENGTH(applied_loadings) > 0");
    }

    public function scopeWithExclusions($query)
    {
        return $query->whereNotNull('applied_exclusions')
            ->whereRaw("JSON_LENGTH(applied_exclusions) > 0");
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

    public function getUnderwritingStatusLabelAttribute(): string
    {
        return MedicalConstants::UW_STATUSES[$this->underwriting_status] ?? $this->underwriting_status;
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

    public function getIsSpouseAttribute(): bool
    {
        return $this->member_type === MedicalConstants::MEMBER_TYPE_SPOUSE;
    }

    public function getIsChildAttribute(): bool
    {
        return $this->member_type === MedicalConstants::MEMBER_TYPE_CHILD;
    }

    public function getIsUnderwritingPendingAttribute(): bool
    {
        return $this->underwriting_status === MedicalConstants::UW_STATUS_PENDING;
    }

    public function getIsUnderwritingApprovedAttribute(): bool
    {
        return $this->underwriting_status === MedicalConstants::UW_STATUS_APPROVED;
    }

    public function getIsUnderwritingDeclinedAttribute(): bool
    {
        return $this->underwriting_status === MedicalConstants::UW_STATUS_DECLINED;
    }

    public function getHasTermsAttribute(): bool
    {
        return $this->underwriting_status === MedicalConstants::UW_STATUS_TERMS;
    }

    public function getHasLoadingsAttribute(): bool
    {
        return !empty($this->applied_loadings);
    }

    public function getHasExclusionsAttribute(): bool
    {
        return !empty($this->applied_exclusions);
    }

    public function getLoadingsCountAttribute(): int
    {
        return is_array($this->applied_loadings) ? count($this->applied_loadings) : 0;
    }

    public function getExclusionsCountAttribute(): int
    {
        return is_array($this->applied_exclusions) ? count($this->applied_exclusions) : 0;
    }

    public function getIsConvertedAttribute(): bool
    {
        return $this->converted_member_id !== null;
    }

    public function getDependentCountAttribute(): int
    {
        return $this->activeDependents()->count();
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Calculate premium for this member.
     */
    public function calculatePremium(): void
    {
        $application = $this->application;
        if (!$application || !$application->rate_card_id) {
            return;
        }

        $rateCard = $application->rateCard;
        if (!$rateCard) {
            return;
        }

        // Find applicable rate entry
        $age = $this->age_at_inception ?? $this->age;
        $entry = $rateCard->entries()
            ->where('age_from', '<=', $age)
            ->where('age_to', '>=', $age)
            ->where(function ($q) {
                $q->whereNull('member_type')
                  ->orWhere('member_type', $this->member_type);
            })
            ->first();

        if ($entry) {
            $this->base_premium = (float) $entry->premium;
        }

        // Calculate loading from applied_loadings
        $this->loading_amount = $this->calculateLoadingAmount();

        // Total
        $this->total_premium = $this->base_premium + $this->loading_amount;

        $this->save();
    }

    /**
     * Calculate total loading amount from applied loadings.
     */
    protected function calculateLoadingAmount(): float
    {
        if (empty($this->applied_loadings)) {
            return 0;
        }

        $totalLoading = 0;
        foreach ($this->applied_loadings as $loading) {
            if (($loading['loading_type'] ?? '') === 'percentage') {
                $totalLoading += round($this->base_premium * (($loading['value'] ?? 0) / 100), 2);
            } else {
                $totalLoading += (float) ($loading['value'] ?? 0);
            }
        }

        return $totalLoading;
    }

    /**
     * Approve member for underwriting.
     */
    public function approveUnderwriting(string $underwriterId, ?string $notes = null): bool
    {
        $this->underwriting_status = MedicalConstants::UW_STATUS_APPROVED;
        $this->underwritten_by = $underwriterId;
        $this->underwritten_at = now();
        $this->underwriting_notes = $notes;
        
        return $this->save();
    }

    /**
     * Decline member.
     */
    public function declineUnderwriting(string $underwriterId, string $reason): bool
    {
        $this->underwriting_status = MedicalConstants::UW_STATUS_DECLINED;
        $this->underwritten_by = $underwriterId;
        $this->underwritten_at = now();
        $this->underwriting_notes = $reason;
        $this->is_active = false; // Remove from application
        
        return $this->save();
    }

    /**
     * Apply underwriting terms (loadings/exclusions).
     */
    public function applyTerms(
        string $underwriterId,
        array $loadings = [],
        array $exclusions = [],
        ?string $notes = null
    ): bool {
        $this->underwriting_status = MedicalConstants::UW_STATUS_TERMS;
        $this->underwritten_by = $underwriterId;
        $this->underwritten_at = now();
        $this->applied_loadings = $loadings;
        $this->applied_exclusions = $exclusions;
        $this->underwriting_notes = $notes;

        // Recalculate premium with loadings
        $this->loading_amount = $this->calculateLoadingAmount();
        $this->total_premium = $this->base_premium + $this->loading_amount;
        
        return $this->save();
    }

    /**
     * Add a loading.
     */
    public function addLoading(array $loading): void
    {
        $loadings = $this->applied_loadings ?? [];
        $loading['id'] = uniqid('load_');
        $loading['applied_at'] = now()->toISOString();
        $loadings[] = $loading;
        
        $this->applied_loadings = $loadings;
        $this->loading_amount = $this->calculateLoadingAmount();
        $this->total_premium = $this->base_premium + $this->loading_amount;
        
        $this->save();
    }

    /**
     * Remove a loading.
     */
    public function removeLoading(string $loadingId): void
    {
        $loadings = collect($this->applied_loadings ?? [])
            ->filter(fn($l) => ($l['id'] ?? '') !== $loadingId)
            ->values()
            ->toArray();
        
        $this->applied_loadings = $loadings;
        $this->loading_amount = $this->calculateLoadingAmount();
        $this->total_premium = $this->base_premium + $this->loading_amount;
        
        $this->save();
    }

    /**
     * Add an exclusion.
     */
    public function addExclusion(array $exclusion): void
    {
        $exclusions = $this->applied_exclusions ?? [];
        $exclusion['id'] = uniqid('excl_');
        $exclusion['applied_at'] = now()->toISOString();
        $exclusions[] = $exclusion;
        
        $this->applied_exclusions = $exclusions;
        $this->save();
    }

    /**
     * Remove an exclusion.
     */
    public function removeExclusion(string $exclusionId): void
    {
        $exclusions = collect($this->applied_exclusions ?? [])
            ->filter(fn($e) => ($e['id'] ?? '') !== $exclusionId)
            ->values()
            ->toArray();
        
        $this->applied_exclusions = $exclusions;
        $this->save();
    }

    /**
     * Remove member from application (soft).
     */
    public function removeFromApplication(): bool
    {
        // If principal, also remove dependents
        if ($this->is_principal) {
            $this->activeDependents()->update(['is_active' => false]);
        }

        $this->is_active = false;
        return $this->save();
    }

    /**
     * Validate member can be added as dependent.
     */
    public static function validateDependent(string $memberType, ApplicationMember $principal, ?int $age = null): array
    {
        $errors = [];

        // Check principal exists
        if (!$principal->is_principal) {
            $errors[] = 'Principal member is not valid';
            return $errors;
        }

        // Get plan limits
        $application = $principal->application;
        $plan = $application?->plan;

        if ($plan) {
            // Check spouse limit
            if ($memberType === MedicalConstants::MEMBER_TYPE_SPOUSE) {
                $currentSpouses = $principal->activeDependents()
                    ->where('member_type', MedicalConstants::MEMBER_TYPE_SPOUSE)
                    ->count();
                
                $maxSpouses = $plan->max_spouses ?? 1;
                if ($currentSpouses >= $maxSpouses) {
                    $errors[] = "Maximum number of spouses ({$maxSpouses}) reached";
                }
            }

            // Check child limit
            if ($memberType === MedicalConstants::MEMBER_TYPE_CHILD) {
                $currentChildren = $principal->activeDependents()
                    ->where('member_type', MedicalConstants::MEMBER_TYPE_CHILD)
                    ->count();
                
                $maxChildren = $plan->max_children ?? 4;
                if ($currentChildren >= $maxChildren) {
                    $errors[] = "Maximum number of children ({$maxChildren}) reached";
                }

                // Check age limit for children
                if ($age !== null) {
                    $maxChildAge = $plan->max_child_age ?? 21;
                    if ($age > $maxChildAge) {
                        $errors[] = "Child age ({$age}) exceeds maximum allowed ({$maxChildAge})";
                    }
                }
            }

            // Check parent limit
            if ($memberType === MedicalConstants::MEMBER_TYPE_PARENT) {
                $currentParents = $principal->activeDependents()
                    ->where('member_type', MedicalConstants::MEMBER_TYPE_PARENT)
                    ->count();
                
                $maxParents = $plan->max_parents ?? 2;
                if ($currentParents >= $maxParents) {
                    $errors[] = "Maximum number of parents ({$maxParents}) reached";
                }
            }
        }

        return $errors;
    }
}