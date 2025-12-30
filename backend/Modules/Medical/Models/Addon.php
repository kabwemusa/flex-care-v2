<?php
// namespace Modules\Medical\Models;


// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Support\Str;

// class Addon extends BaseModel
// {
//     protected $table = 'med_addons';
//     protected $fillable = ['name',  'description', 'price', 'is_mandatory'];

//     // protected static function boot()
//     // {
//     //     parent::boot();
//     //     static::creating(function ($addon) {
//     //         $addon->code = self::generateAddonCode($addon);
//     //     });
//     // }

//     // private static function generateAddonCode($addon): string
//     // {
//     //     $prefix = Str::upper(Str::limit(preg_replace('/[^A-Z0-9]/i', '', $addon->name), 4, ''));
//     //     $base = "ADD-{$prefix}";
        
//     //     $latest = self::where('code', 'LIKE', "{$base}%")->orderBy('code', 'desc')->first();
//     //     $sequence = $latest ? ((int)Str::afterLast($latest->code, '-') + 1) : 1;

//     //     return "{$base}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
//     // }
// }



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
        'is_active',
        'effective_from',
        'effective_to',
        'icon',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'addon_type' => MedicalConstants::ADDON_TYPE_OPTIONAL,
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

    public function addonBenefits(): HasMany
    {
        return $this->hasMany(AddonBenefit::class, 'addon_id');
    }

    public function benefits(): HasManyThrough
    {
        return $this->hasManyThrough(
            Benefit::class,
            AddonBenefit::class,
            'addon_id',
            'id',
            'id',
            'benefit_id'
        );
    }

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

    public function rates(): HasMany
    {
        return $this->hasMany(AddonRate::class, 'addon_id');
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

    public function getActiveRateForPlan(?string $planId = null): ?AddonRate
    {
        $query = $this->rates()
            ->where('is_active', true)
            ->effective();

        if ($planId !== null) {
            // Prefer plan-specific rate, fallback to global
            $planRate = (clone $query)->where('plan_id', $planId)->first();
            
            if ($planRate) {
                return $planRate;
            }
        }

        // Return global rate (no plan_id)
        return $query->whereNull('plan_id')->first();
    }
}