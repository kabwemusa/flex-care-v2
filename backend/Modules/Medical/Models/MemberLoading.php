<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Medical\Constants\MedicalConstants;

class MemberLoading extends Model
{
    protected $table = 'med_member_loadings';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'member_id',
        'loading_rule_id',
        'condition_name',
        'icd10_code',
        'loading_type',
        'loading_value',
        'loading_amount',
        'duration_type',
        'duration_months',
        'start_date',
        'end_date',
        'review_date',
        'status',
        'underwriting_notes',
        'applied_by',
    ];

    protected $casts = [
        'loading_value' => 'decimal:2',
        'loading_amount' => 'decimal:2',
        'duration_months' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'review_date' => 'date',
    ];

    protected $attributes = [
        'loading_amount' => 0,
        'status' => 'active',
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

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function loadingRule(): BelongsTo
    {
        return $this->belongsTo(LoadingRule::class, 'loading_rule_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopePermanent($query)
    {
        return $query->where('duration_type', MedicalConstants::LOADING_DURATION_PERMANENT);
    }

    public function scopeTimeLimited($query)
    {
        return $query->where('duration_type', MedicalConstants::LOADING_DURATION_TIME_LIMITED);
    }

    public function scopeDueForReview($query)
    {
        return $query->where('status', 'active')
                     ->where('duration_type', MedicalConstants::LOADING_DURATION_REVIEWABLE)
                     ->where('review_date', '<=', now()->addDays(30));
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getLoadingTypeLabelAttribute(): string
    {
        return MedicalConstants::LOADING_TYPES[$this->loading_type] ?? $this->loading_type;
    }

    public function getDurationTypeLabelAttribute(): string
    {
        return MedicalConstants::LOADING_DURATIONS[$this->duration_type] ?? $this->duration_type;
    }

    public function getFormattedLoadingAttribute(): string
    {
        if ($this->loading_type === MedicalConstants::LOADING_TYPE_PERCENTAGE) {
            return $this->loading_value . '%';
        }

        if ($this->loading_type === MedicalConstants::LOADING_TYPE_EXCLUSION) {
            return 'Exclusion';
        }

        return 'ZMW ' . number_format($this->loading_amount, 2);
    }

    public function getIsPermanentAttribute(): bool
    {
        return $this->duration_type === MedicalConstants::LOADING_DURATION_PERMANENT;
    }

    public function getIsTimeLimitedAttribute(): bool
    {
        return $this->duration_type === MedicalConstants::LOADING_DURATION_TIME_LIMITED;
    }

    public function getIsReviewableAttribute(): bool
    {
        return $this->duration_type === MedicalConstants::LOADING_DURATION_REVIEWABLE;
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getIsExpiredAttribute(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if ($this->is_time_limited && $this->end_date) {
            return $this->end_date->isPast();
        }

        return false;
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->end_date || $this->is_permanent) {
            return null;
        }

        return max(0, now()->diffInDays($this->end_date, false));
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function calculateAmount(float $basePremium): float
    {
        if ($this->loading_type === MedicalConstants::LOADING_TYPE_EXCLUSION) {
            return 0;
        }

        if ($this->loading_type === MedicalConstants::LOADING_TYPE_PERCENTAGE) {
            return round($basePremium * ($this->loading_value / 100), 2);
        }

        return (float) $this->loading_value;
    }

    public function expire(): bool
    {
        $this->status = 'expired';
        
        return $this->save();
    }

    public function waive(?string $reason = null): bool
    {
        $this->status = 'waived';
        
        if ($reason) {
            $this->underwriting_notes = ($this->underwriting_notes ? $this->underwriting_notes . "\n" : '') 
                                       . "Waived: {$reason}";
        }
        
        return $this->save();
    }

    public function remove(?string $reason = null): bool
    {
        $this->status = 'removed';
        
        if ($reason) {
            $this->underwriting_notes = ($this->underwriting_notes ? $this->underwriting_notes . "\n" : '') 
                                       . "Removed: {$reason}";
        }
        
        return $this->save();
    }

    public function setReviewDate(int $monthsFromNow = 12): bool
    {
        $this->review_date = now()->addMonths($monthsFromNow);
        
        return $this->save();
    }
}