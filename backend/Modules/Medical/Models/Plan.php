<?php


// class Plan extends BaseModel
// {
//     protected $table = 'med_plans';

//     protected $fillable = [
//         'scheme_id',
//         'name',
//         'code',
//         'type'
//     ];

//     protected static function boot()
//     {
//         parent::boot();
        
//         static::creating(function ($plan) {
//             // Force override any value sent by the frontend
//             $plan->code = self::generateUniqueCode($plan);
//         });
//     }

//     private static function generateUniqueCode($plan): string
//     {
//         // Important: Use the full namespace or ensure it's imported correctly
//         $scheme = \Modules\Medical\Models\Scheme::find($plan->scheme_id);
        
//         // Clean prefix: Take first 3 letters of scheme, fallback to 'PLN'
//         $prefix = $scheme ? Str::upper(Str::limit(preg_replace('/[^A-Z0-9]/i', '', $scheme->name), 3, '')) : 'PLN';

//         // Clean tier: Take first 3 letters of plan name
//         $tier = Str::upper(Str::limit(preg_replace('/[^A-Z0-9]/i', '', $plan->name), 3, ''));

//         $baseCode = "{$prefix}-{$tier}";

//         // Look for the highest sequence for this specific base code
//         $latestCode = self::where('code', 'LIKE', "{$baseCode}-%")
//             ->orderBy('code', 'desc')
//             ->first();

//         $sequence = 1;
//         if ($latestCode) {
//             $pieces = explode('-', $latestCode->code);
//             $lastPiece = end($pieces);
//             if (is_numeric($lastPiece)) {
//                 $sequence = (int)$lastPiece + 1;
//             }
//         }

//         return "{$baseCode}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
//     }
//     /**
//      * The parent Umbrella Scheme
//      */
//     public function scheme(): BelongsTo
//     {
//         return $this->belongsTo(Scheme::class, 'scheme_id');
//     }

//     /**
//      * Features assigned to this plan (Gold, Silver, etc.)
//      */
//     public function features(): BelongsToMany
//     {
//         return $this->belongsToMany(Feature::class, 'med_feature_plan')
//                     ->withPivot('limit_amount', 'limit_description');
//     }
//     public function addons(): BelongsToMany {
//         return $this->belongsToMany(Addon::class, 'med_plan_addon')->withTimestamps();
//     }
// }



namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Modules\Medical\Constants\MedicalConstants;

class Plan extends BaseModel
{
    protected $table = 'med_plans';

    protected $fillable = [
        'scheme_id',
        'code',
        'name',
        'tier_level',
        'plan_type',
        'member_config',
        'default_waiting_periods',
        'default_cost_sharing',
        'network_type',
        'out_of_network_penalty',
        'is_active',
        'is_visible',
        'effective_from',
        'effective_to',
        'sort_order',
        'description',
        'highlights',
    ];

    protected $casts = [
        'tier_level' => 'integer',
        'member_config' => 'array',
        'default_waiting_periods' => 'array',
        'default_cost_sharing' => 'array',
        'out_of_network_penalty' => 'decimal:2',
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'tier_level' => 1,
        'network_type' => MedicalConstants::NETWORK_TYPE_OPEN,
        'out_of_network_penalty' => 0,
        'is_active' => true,
        'is_visible' => true,
        // 'effective_from' => date('YYYY-m-d'),
        'sort_order' => 0,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected function getCodePrefix(): string
    {
        return MedicalConstants::PREFIX_PLAN;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class, 'scheme_id');
    }

    public function planBenefits(): HasMany
    {
        return $this->hasMany(PlanBenefit::class, 'plan_id');
    }

    public function benefits(): HasManyThrough
    {
        return $this->hasManyThrough(
            Benefit::class,
            PlanBenefit::class,
            'plan_id',
            'id',
            'id',
            'benefit_id'
        );
    }

    public function exclusions(): HasMany
    {
        return $this->hasMany(PlanExclusion::class, 'plan_id');
    }

    public function waitingPeriods(): HasMany
    {
        return $this->hasMany(PlanWaitingPeriod::class, 'plan_id');
    }

    public function rateCards(): HasMany
    {
        return $this->hasMany(RateCard::class, 'plan_id');
    }

    public function planAddons(): HasMany
    {
        return $this->hasMany(PlanAddon::class, 'plan_id');
    }

    public function addons(): HasManyThrough
    {
        return $this->hasManyThrough(
            Addon::class,
            PlanAddon::class,
            'plan_id',
            'id',
            'id',
            'addon_id'
        );
    }

    public function discountRules(): HasMany
    {
        return $this->hasMany(DiscountRule::class, 'plan_id');
    }

    public function addonRates(): HasMany
    {
        return $this->hasMany(AddonRate::class, 'plan_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeByPlanType($query, string $type)
    {
        return $query->where('plan_type', $type);
    }

    public function scopeGroup($query)
    {
        return $query->where('plan_type', MedicalConstants::PLAN_TYPE_GROUP);
    }

    public function scopeIndividual($query)
    {
        return $query->where('plan_type', MedicalConstants::PLAN_TYPE_INDIVIDUAL);
    }

    public function scopeFamily($query)
    {
        return $query->where('plan_type', MedicalConstants::PLAN_TYPE_FAMILY);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('tier_level')->orderBy('sort_order');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getPlanTypeLabelAttribute(): string
    {
        return MedicalConstants::PLAN_TYPES[$this->plan_type] ?? $this->plan_type;
    }

    public function getNetworkTypeLabelAttribute(): string
    {
        return MedicalConstants::NETWORK_TYPES[$this->network_type] ?? $this->network_type;
    }

    public function getActiveRateCardAttribute(): ?RateCard
    {
        return $this->rateCards()
            ->where('is_active', true)
            ->effective()
            ->first();
    }

    public function getMaxDependentsAttribute(): int
    {
        return $this->member_config['max_dependents'] ?? MedicalConstants::DEFAULT_MAX_DEPENDENTS;
    }

    public function getAllowedMemberTypesAttribute(): array
    {
        return $this->member_config['allowed_member_types'] ?? array_keys(MedicalConstants::MEMBER_TYPES);
    }

    public function getChildAgeLimitAttribute(): int
    {
        return $this->member_config['child_age_limit'] ?? MedicalConstants::DEFAULT_CHILD_AGE_LIMIT;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function getWaitingPeriodDays(string $type): int
    {
        $defaults = $this->default_waiting_periods ?? [];
        
        return $defaults[$type] ?? match($type) {
            MedicalConstants::WAITING_TYPE_GENERAL => MedicalConstants::DEFAULT_GENERAL_WAITING_DAYS,
            MedicalConstants::WAITING_TYPE_MATERNITY => MedicalConstants::DEFAULT_MATERNITY_WAITING_DAYS,
            MedicalConstants::WAITING_TYPE_PRE_EXISTING => MedicalConstants::DEFAULT_PRE_EXISTING_WAITING_DAYS,
            MedicalConstants::WAITING_TYPE_CHRONIC => MedicalConstants::DEFAULT_CHRONIC_WAITING_DAYS,
            default => 0,
        };
    }

    public function isMemberTypeAllowed(string $memberType): bool
    {
        return in_array($memberType, $this->allowed_member_types);
    }
}