<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Medical\Constants\MedicalConstants;

class Scheme extends BaseModel
{
    protected $table = 'med_schemes';

    protected $fillable = [
        'code',
        'name',
        'slug',
        'market_segment',
        'description',
        'eligibility_rules',
        'underwriting_rules',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'eligibility_rules' => 'array',
        'underwriting_rules' => 'array',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    protected $attributes = [
        'is_active' => true,
    ];


        protected static function booted()
    {
        parent::boot();

        static::creating(function ($scheme) {
            $scheme->slug = Str::slug($scheme->name);
        });

        static::updating(function ($scheme) {
            $scheme->slug = Str::slug($scheme->name);
        });
    }

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected function getCodePrefix(): string
    {
        return MedicalConstants::PREFIX_SCHEME;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class, 'scheme_id');
    }

    public function discountRules(): HasMany
    {
        return $this->hasMany(DiscountRule::class, 'scheme_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeByMarketSegment($query, string $segment)
    {
        return $query->where('market_segment', $segment);
    }

    public function scopeCorporate($query)
    {
        return $query->where('market_segment', MedicalConstants::MARKET_SEGMENT_CORPORATE);
    }

    public function scopeIndividual($query)
    {
        return $query->where('market_segment', MedicalConstants::MARKET_SEGMENT_INDIVIDUAL);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getMarketSegmentLabelAttribute(): string
    {
        return MedicalConstants::MARKET_SEGMENTS[$this->market_segment] ?? $this->market_segment;
    }

    public function getIsEffectiveAttribute(): bool
    {
        $today = now()->toDateString();
        
        return $this->effective_from <= $today 
            && ($this->effective_to === null || $this->effective_to >= $today);
    }
}