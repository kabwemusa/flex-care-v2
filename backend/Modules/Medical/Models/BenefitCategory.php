<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Medical\Constants\MedicalConstants;

class BenefitCategory extends BaseModel
{
    protected $table = 'med_benefit_categories';

    protected $fillable = [
        'code',
        'name',
        'description',
        'icon',
        'color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected function getCodePrefix(): string
    {
        return MedicalConstants::PREFIX_BENEFIT_CATEGORY;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function benefits(): HasMany
    {
        return $this->hasMany(Benefit::class, 'category_id');
    }

    public function activeBenefits(): HasMany
    {
        return $this->hasMany(Benefit::class, 'category_id')
            ->where('is_active', true);
    }

    public function rootBenefits(): HasMany
    {
        return $this->hasMany(Benefit::class, 'category_id')
            ->whereNull('parent_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function getBenefitCount(): int
    {
        return $this->benefits()->count();
    }
}