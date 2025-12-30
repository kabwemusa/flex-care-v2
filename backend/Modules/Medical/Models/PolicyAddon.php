<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PolicyAddon extends Model
{
    protected $table = 'med_policy_addons';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'policy_id',
        'addon_id',
        'addon_rate_id',
        'premium',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'premium' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    protected $attributes = [
        'premium' => 0,
        'is_active' => true,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
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

    public function scopeEffective($query, $date = null)
    {
        $date = $date ?? now()->toDateString();

        return $query->where(function ($q) use ($date) {
            $q->whereNull('effective_from')
              ->orWhere('effective_from', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('effective_to')
              ->orWhere('effective_to', '>=', $date);
        });
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getIsEffectiveAttribute(): bool
    {
        $today = now()->toDateString();

        if ($this->effective_from && $this->effective_from > $today) {
            return false;
        }

        if ($this->effective_to && $this->effective_to < $today) {
            return false;
        }

        return true;
    }
}