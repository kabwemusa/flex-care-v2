<?php
// namespace Modules\Medical\Models;

// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\HasMany;
// use Illuminate\Database\Eloquent\Relations\BelongsTo;

// class RateCard extends BaseModel
// {
//     protected $table = 'med_rate_cards';
//     protected $fillable = ['plan_id', 'name', 'currency', 'is_active', 'valid_from', 'valid_until'];

//     protected $casts = [
//         'is_active' => 'boolean',
//         'valid_from' => 'date',
//         'valid_until' => 'date',
//     ];

//     public function plan(): BelongsTo
//     {
//         return $this->belongsTo(Plan::class);
//     }

//     public function entries(): HasMany
//     {
//         return $this->hasMany(RateCardEntry::class);
//     }
// }



namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Medical\Constants\MedicalConstants;

class RateCard extends BaseModel
{
    protected $table = 'med_rate_cards';

    protected $fillable = [
        'plan_id',
        'code',
        'name',
        'version',
        'currency',
        'premium_frequency',
        'effective_from',
        'effective_to',
        'is_active',
        'is_draft',
        'premium_basis',
        'member_type_factors',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
        'is_draft' => 'boolean',
        'member_type_factors' => 'array',
        'approved_at' => 'datetime',
    ];

    protected $attributes = [
        'version' => '1.0',
        'currency' => MedicalConstants::DEFAULT_CURRENCY,
        'premium_frequency' => MedicalConstants::PREMIUM_FREQUENCY_MONTHLY,
        'is_active' => false,
        'is_draft' => true,
        'premium_basis' => MedicalConstants::PREMIUM_BASIS_PER_MEMBER,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected function getCodePrefix(): string
    {
        return MedicalConstants::PREFIX_RATE_CARD;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RateCardEntry::class, 'rate_card_id');
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(RateCardTier::class, 'rate_card_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeDraft($query)
    {
        return $query->where('is_draft', true);
    }

    public function scopePublished($query)
    {
        return $query->where('is_draft', false);
    }

    public function scopeApproved($query)
    {
        return $query->whereNotNull('approved_at');
    }

    public function scopeByPremiumBasis($query, string $basis)
    {
        return $query->where('premium_basis', $basis);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getPremiumFrequencyLabelAttribute(): string
    {
        return MedicalConstants::PREMIUM_FREQUENCIES[$this->premium_frequency] ?? $this->premium_frequency;
    }

    public function getPremiumBasisLabelAttribute(): string
    {
        return MedicalConstants::PREMIUM_BASES[$this->premium_basis] ?? $this->premium_basis;
    }

    public function getIsEffectiveAttribute(): bool
    {
        $today = now()->toDateString();
        
        return $this->effective_from <= $today 
            && ($this->effective_to === null || $this->effective_to >= $today);
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->approved_at !== null;
    }

    public function getIsPerMemberAttribute(): bool
    {
        return $this->premium_basis === MedicalConstants::PREMIUM_BASIS_PER_MEMBER;
    }

    public function getIsTieredAttribute(): bool
    {
        return $this->premium_basis === MedicalConstants::PREMIUM_BASIS_TIERED;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function getMemberTypeFactor(string $memberType): float
    {
        $factors = $this->member_type_factors ?? [];
        
        return (float) ($factors[$memberType] ?? 1.00);
    }

    public function getBasePremium(int $age, ?string $gender = null, ?string $regionCode = null): ?float
    {
        $query = $this->entries()
            ->where('min_age', '<=', $age)
            ->where('max_age', '>=', $age);

        if ($gender !== null) {
            $query->where(function ($q) use ($gender) {
                $q->whereNull('gender')
                  ->orWhere('gender', $gender);
            });
        }

        if ($regionCode !== null) {
            $query->where(function ($q) use ($regionCode) {
                $q->whereNull('region_code')
                  ->orWhere('region_code', $regionCode);
            });
        }

        $entry = $query->first();

        return $entry?->base_premium;
    }

    public function calculateMemberPremium(
        int $age,
        string $memberType,
        ?string $gender = null,
        ?string $regionCode = null
    ): ?float {
        $basePremium = $this->getBasePremium($age, $gender, $regionCode);
        
        if ($basePremium === null) {
            return null;
        }

        $factor = $this->getMemberTypeFactor($memberType);
        
        return round($basePremium * $factor, 2);
    }

    public function getTierPremium(int $memberCount): ?RateCardTier
    {
        return $this->tiers()
            ->where('min_members', '<=', $memberCount)
            ->where('max_members', '>=', $memberCount)
            ->first();
    }

    public function activate(): bool
    {
        // Deactivate other rate cards for the same plan
        static::where('plan_id', $this->plan_id)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        $this->is_active = true;
        $this->is_draft = false;
        
        return $this->save();
    }

    public function approve(string $approvedBy): bool
    {
        $this->approved_by = $approvedBy;
        $this->approved_at = now();
        
        return $this->save();
    }
}