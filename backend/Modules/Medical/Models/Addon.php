<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Modules\Medical\Constants\MedicalConstants;

class Addon extends BaseModel
{
    protected $table = 'med_addons';

    protected $fillable = [
        'code',
        'name',
        'addon_type',
        'description',
        'terms_conditions',
        'pricing_type',
        'currency',
        'amount',
        'percentage',
        'percentage_basis',
        'is_active',
        'effective_from',
        'effective_to',
        'icon',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'addon_type' => MedicalConstants::ADDON_TYPE_OPTIONAL,
        'pricing_type' => MedicalConstants::ADDON_PRICING_FIXED,
        'currency' => MedicalConstants::DEFAULT_CURRENCY,
        'is_active' => true,
        'sort_order' => 0,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected function getCodePrefix(): string
    {
        return MedicalConstants::PREFIX_ADDON;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function planAddons(): HasMany
    {
        return $this->hasMany(PlanAddon::class, 'addon_id');
    }

    public function plans(): HasManyThrough
    {
        return $this->hasManyThrough(
            Plan::class,
            PlanAddon::class,
            'addon_id',
            'id',
            'id',
            'plan_id'
        );
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeByType($query, string $type)
    {
        return $query->where('addon_type', $type);
    }

    public function scopeOptional($query)
    {
        return $query->where('addon_type', MedicalConstants::ADDON_TYPE_OPTIONAL);
    }

    public function scopeMandatory($query)
    {
        return $query->where('addon_type', MedicalConstants::ADDON_TYPE_MANDATORY);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getAddonTypeLabelAttribute(): string
    {
        return MedicalConstants::ADDON_TYPES[$this->addon_type] ?? $this->addon_type;
    }

    public function getPricingTypeLabelAttribute(): string
    {
        return MedicalConstants::ADDON_PRICING_TYPES[$this->pricing_type] ?? $this->pricing_type;
    }

    public function getIsEffectiveAttribute(): bool
    {
        $today = now()->toDateString();

        if ($this->effective_from !== null && $this->effective_from > $today) {
            return false;
        }

        if ($this->effective_to !== null && $this->effective_to < $today) {
            return false;
        }

        return true;
    }

    public function getIsMandatoryAttribute(): bool
    {
        return $this->addon_type === MedicalConstants::ADDON_TYPE_MANDATORY;
    }

    public function getIsOptionalAttribute(): bool
    {
        return $this->addon_type === MedicalConstants::ADDON_TYPE_OPTIONAL;
    }

    public function getIsFixedAttribute(): bool
    {
        return $this->pricing_type === MedicalConstants::ADDON_PRICING_FIXED;
    }

    public function getIsPerMemberAttribute(): bool
    {
        return $this->pricing_type === MedicalConstants::ADDON_PRICING_PER_MEMBER;
    }

    public function getIsPercentageAttribute(): bool
    {
        return $this->pricing_type === MedicalConstants::ADDON_PRICING_PERCENTAGE;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function isAvailableForPlan(string $planId): bool
    {
        return $this->planAddons()
            ->where('plan_id', $planId)
            ->where('is_active', true)
            ->exists();
    }

    public function calculatePremium(
        int $memberCount = 1,
        float $basePremium = 0
    ): float {
        return match($this->pricing_type) {
            MedicalConstants::ADDON_PRICING_FIXED => (float) $this->amount,
            MedicalConstants::ADDON_PRICING_PER_MEMBER => (float) ($this->amount * $memberCount),
            MedicalConstants::ADDON_PRICING_PERCENTAGE => round($basePremium * ($this->percentage / 100), 2),
            default => 0,
        };
    }
}