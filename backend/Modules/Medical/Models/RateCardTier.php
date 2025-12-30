<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateCardTier extends BaseModel
{
    protected $table = 'med_rate_card_tiers';

    /**
     * Disable soft deletes for this model.
     */
    use \Illuminate\Database\Eloquent\SoftDeletes {
        \Illuminate\Database\Eloquent\SoftDeletes::bootSoftDeletes as parentBootSoftDeletes;
    }

    public static function bootSoftDeletes()
    {
        // Disable soft deletes for this model
    }

    protected $fillable = [
        'rate_card_id',
        'tier_name',
        'tier_description',
        'min_members',
        'max_members',
        'tier_premium',
        'extra_member_premium',
        'sort_order',
    ];

    protected $casts = [
        'min_members' => 'integer',
        'max_members' => 'integer',
        'tier_premium' => 'decimal:2',
        'extra_member_premium' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'sort_order' => 0,
    ];

    // =========================================================================
    // CODE GENERATION (Not needed)
    // =========================================================================

    protected function shouldGenerateCode(): bool
    {
        return false;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(RateCard::class, 'rate_card_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeByMemberCount($query, int $count)
    {
        return $query->where('min_members', '<=', $count)
                     ->where('max_members', '>=', $count);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('min_members');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getMemberRangeLabelAttribute(): string
    {
        if ($this->min_members === $this->max_members) {
            return (string) $this->min_members;
        }

        if ($this->max_members >= 99) {
            return "{$this->min_members}+";
        }

        return "{$this->min_members} - {$this->max_members}";
    }

    public function getFormattedPremiumAttribute(): string
    {
        $currency = $this->rateCard->currency ?? 'ZMW';
        
        return $currency . ' ' . number_format($this->tier_premium, 2);
    }

    public function getHasExtraMemberPremiumAttribute(): bool
    {
        return $this->extra_member_premium !== null && $this->extra_member_premium > 0;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function appliesToMemberCount(int $count): bool
    {
        return $count >= $this->min_members && $count <= $this->max_members;
    }

    public function calculatePremium(int $memberCount): float
    {
        // Base tier premium
        $premium = $this->tier_premium;

        // Add extra member premium if applicable
        if ($memberCount > $this->max_members && $this->has_extra_member_premium) {
            $extraMembers = $memberCount - $this->max_members;
            $premium += ($extraMembers * $this->extra_member_premium);
        }

        return round($premium, 2);
    }
}