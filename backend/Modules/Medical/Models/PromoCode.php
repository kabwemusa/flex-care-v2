<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Medical\Constants\MedicalConstants;

class PromoCode extends BaseModel
{
    protected $table = 'med_promo_codes';

    protected $fillable = [
        'code',
        'name',
        'discount_rule_id',
        'valid_from',
        'valid_to',
        'max_uses',
        'current_uses',
        'max_uses_per_policy',
        'eligible_schemes',
        'eligible_plans',
        'eligible_groups',
        'is_active',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'max_uses' => 'integer',
        'current_uses' => 'integer',
        'max_uses_per_policy' => 'integer',
        'eligible_schemes' => 'array',
        'eligible_plans' => 'array',
        'eligible_groups' => 'array',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'current_uses' => 0,
        'max_uses_per_policy' => 1,
        'is_active' => true,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected function getCodePrefix(): string
    {
        return MedicalConstants::PREFIX_PROMO;
    }

    /**
     * Override code generation for promo codes - use the code field directly
     */
    protected function shouldGenerateCode(): bool
    {
        return false; // Promo codes are manually entered
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function discountRule(): BelongsTo
    {
        return $this->belongsTo(DiscountRule::class, 'discount_rule_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }

    public function scopeValid($query)
    {
        $today = now()->toDateString();

        return $query->where('is_active', true)
                     ->where('valid_from', '<=', $today)
                     ->where('valid_to', '>=', $today);
    }

    public function scopeNotExhausted($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('max_uses')
              ->orWhereColumn('current_uses', '<', 'max_uses');
        });
    }

    public function scopeUsable($query)
    {
        return $query->valid()->notExhausted();
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getIsValidAttribute(): bool
    {
        $today = now()->toDateString();

        return $this->valid_from <= $today && $this->valid_to >= $today;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->valid_to < now()->toDateString();
    }

    public function getIsNotYetValidAttribute(): bool
    {
        return $this->valid_from > now()->toDateString();
    }

    public function getHasMaxUsesAttribute(): bool
    {
        return $this->max_uses !== null;
    }

    public function getIsExhaustedAttribute(): bool
    {
        if (!$this->has_max_uses) {
            return false;
        }

        return $this->current_uses >= $this->max_uses;
    }

    public function getRemainingUsesAttribute(): ?int
    {
        if (!$this->has_max_uses) {
            return null;
        }

        return max(0, $this->max_uses - $this->current_uses);
    }

    public function getIsUsableAttribute(): bool
    {
        return $this->is_active 
            && $this->is_valid 
            && !$this->is_exhausted 
            && $this->discountRule?->canBeUsed();
    }

    public function getDaysUntilExpiryAttribute(): int
    {
        return max(0, now()->diffInDays($this->valid_to, false));
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function isEligibleForScheme(?string $schemeId): bool
    {
        if (empty($this->eligible_schemes)) {
            return true; // No restriction
        }

        return in_array($schemeId, $this->eligible_schemes);
    }

    public function isEligibleForPlan(?string $planId): bool
    {
        if (empty($this->eligible_plans)) {
            return true; // No restriction
        }

        return in_array($planId, $this->eligible_plans);
    }

    public function isEligibleForGroup(?string $groupId): bool
    {
        if (empty($this->eligible_groups)) {
            return true; // No restriction
        }

        return in_array($groupId, $this->eligible_groups);
    }

    public function isEligibleFor(?string $schemeId, ?string $planId, ?string $groupId = null): bool
    {
        return $this->isEligibleForScheme($schemeId)
            && $this->isEligibleForPlan($planId)
            && $this->isEligibleForGroup($groupId);
    }

    public function incrementUsage(): bool
    {
        $this->current_uses++;
        
        return $this->save();
    }

    public function validate(?string $schemeId = null, ?string $planId = null, ?string $groupId = null): array
    {
        $errors = [];

        if (!$this->is_active) {
            $errors[] = 'Promo code is inactive.';
        }

        if ($this->is_not_yet_valid) {
            $errors[] = 'Promo code is not yet valid.';
        }

        if ($this->is_expired) {
            $errors[] = 'Promo code has expired.';
        }

        if ($this->is_exhausted) {
            $errors[] = 'Promo code usage limit has been reached.';
        }

        if (!$this->isEligibleForScheme($schemeId)) {
            $errors[] = 'Promo code is not valid for this scheme.';
        }

        if (!$this->isEligibleForPlan($planId)) {
            $errors[] = 'Promo code is not valid for this plan.';
        }

        if (!$this->isEligibleForGroup($groupId)) {
            $errors[] = 'Promo code is not valid for this group.';
        }

        if ($this->discountRule && !$this->discountRule->canBeUsed()) {
            $errors[] = 'Associated discount rule is not available.';
        }

        return $errors;
    }

    public static function findByCode(string $code): ?self
    {
        return static::byCode($code)->first();
    }

    public static function findUsableByCode(string $code): ?self
    {
        return static::byCode($code)->usable()->first();
    }
}