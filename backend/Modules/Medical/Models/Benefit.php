<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Medical\Constants\MedicalConstants;

class Benefit extends BaseModel
{
    protected $table = 'med_benefits';

    protected $fillable = [
        'category_id',
        'parent_id',
        'code',
        'name',
        'short_name',
        'benefit_type',
        'limit_type',
        'limit_frequency',
        'limit_basis',
        'waiting_period_type',
        'requires_preauth',
        'requires_referral',
        'required_documents',
        'applicable_member_types',
        'description',
        'terms_conditions',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'requires_preauth' => 'boolean',
        'requires_referral' => 'boolean',
        'required_documents' => 'array',
        'applicable_member_types' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'benefit_type' => MedicalConstants::BENEFIT_TYPE_CORE,
        'limit_type' => MedicalConstants:: MONETARY,
        'limit_frequency' => MedicalConstants::PER_ANNUM,
        'limit_basis' => MedicalConstants::PER_MEMBER,
        'waiting_period_type' => MedicalConstants::WAITING_TYPE_GENERAL,
        'requires_preauth' => false,
        'requires_referral' => false,
        'sort_order' => 0,
        'is_active' => true,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected function getCodePrefix(): string
    {
        return MedicalConstants::PREFIX_BENEFIT;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function category(): BelongsTo
    {
        return $this->belongsTo(BenefitCategory::class, 'category_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Benefit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Benefit::class, 'parent_id');
    }

    public function activeChildren(): HasMany
    {
        return $this->hasMany(Benefit::class, 'parent_id')
            ->where('is_active', true);
    }

    public function planBenefits(): HasMany
    {
        return $this->hasMany(PlanBenefit::class, 'benefit_id');
    }

    public function addonBenefits(): HasMany
    {
        return $this->hasMany(AddonBenefit::class, 'benefit_id');
    }

    public function exclusions(): HasMany
    {
        return $this->hasMany(PlanExclusion::class, 'benefit_id');
    }

    public function waitingPeriods(): HasMany
    {
        return $this->hasMany(PlanWaitingPeriod::class, 'benefit_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeByType($query, string $type)
    {
        return $query->where('benefit_type', $type);
    }

    public function scopeCore($query)
    {
        return $query->where('benefit_type', MedicalConstants::BENEFIT_TYPE_CORE);
    }

    public function scopeOptional($query)
    {
        return $query->where('benefit_type', MedicalConstants::BENEFIT_TYPE_OPTIONAL);
    }

    public function scopeRootLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeRequiresPreauth($query)
    {
        return $query->where('requires_preauth', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeByCategory($query, string $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getBenefitTypeLabelAttribute(): string
    {
        return MedicalConstants::BENEFIT_TYPES[$this->benefit_type] ?? $this->benefit_type;
    }

    public function getLimitTypeLabelAttribute(): string
    {
        return MedicalConstants::LIMIT_TYPES[$this->limit_type] ?? $this->limit_type;
    }

    public function getLimitFrequencyLabelAttribute(): string
    {
        return MedicalConstants::LIMIT_FREQUENCIES[$this->limit_frequency] ?? $this->limit_frequency;
    }

    public function getLimitBasisLabelAttribute(): string
    {
        return MedicalConstants::LIMIT_BASES[$this->limit_basis] ?? $this->limit_basis;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->short_name ?? $this->name;
    }

    public function getIsRootAttribute(): bool
    {
        return $this->parent_id === null;
    }

    public function getHasChildrenAttribute(): bool
    {
        return $this->children()->exists();
    }

    public function getFullPathAttribute(): string
    {
        if ($this->parent) {
            return $this->parent->full_path . ' > ' . $this->name;
        }
        
        return $this->name;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function isApplicableToMemberType(string $memberType): bool
    {
        if (empty($this->applicable_member_types)) {
            return true; // Applies to all if not specified
        }

        return in_array($memberType, $this->applicable_member_types);
    }

    public function getAllDescendants(): \Illuminate\Database\Eloquent\Collection
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }
        
        return $descendants;
    }
}