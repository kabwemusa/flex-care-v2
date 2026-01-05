<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ApplicationAddon extends Model
{
    use HasUuids;

    protected $table = 'med_application_addons';

    protected $fillable = [
        'application_id',
        'addon_id',
        'addon_rate_id',
        'premium',
        'is_active',
    ];

    protected $casts = [
        'premium' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'premium' => 0,
        'is_active' => true,
    ];

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            if ($model->application) {
                $model->application->recalculatePremiums();
            }
        });

        static::deleted(function ($model) {
            if ($model->application) {
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

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class, 'addon_id');
    }

    public function addonRate(): BelongsTo
    {
        return $this->belongsTo(AddonRate::class, 'addon_rate_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getAddonNameAttribute(): ?string
    {
        return $this->addon?->name;
    }

    public function getAddonCodeAttribute(): ?string
    {
        return $this->addon?->code;
    }
}