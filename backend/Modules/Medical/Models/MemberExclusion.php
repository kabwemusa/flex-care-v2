<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Medical\Constants\MedicalConstants;

class MemberExclusion extends Model
{
    protected $table = 'med_member_exclusions';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'member_id',
        'benefit_id',
        'exclusion_type',
        'exclusion_name',
        'description',
        'icd10_codes',
        'start_date',
        'end_date',
        'review_date',
        'status',
        'underwriting_notes',
        'applied_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'review_date' => 'date',
    ];

    protected $attributes = [
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

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class, 'benefit_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePermanent($query)
    {
        return $query->whereNull('end_date');
    }

    public function scopeTimeLimited($query)
    {
        return $query->whereNotNull('end_date');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('exclusion_type', $type);
    }

    public function scopeForBenefit($query, string $benefitId)
    {
        return $query->where('benefit_id', $benefitId);
    }

    public function scopeDueForReview($query)
    {
        return $query->where('status', 'active')
                     ->whereNotNull('review_date')
                     ->where('review_date', '<=', now()->addDays(30));
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getExclusionTypeLabelAttribute(): string
    {
        return MedicalConstants::EXCLUSION_TYPES[$this->exclusion_type] ?? $this->exclusion_type;
    }

    public function getIcdCodesArrayAttribute(): array
    {
        if (empty($this->icd10_codes)) {
            return [];
        }

        return array_map('trim', explode(',', $this->icd10_codes));
    }

    public function getIsPermanentAttribute(): bool
    {
        return $this->end_date === null;
    }

    public function getIsTimeLimitedAttribute(): bool
    {
        return $this->end_date !== null;
    }

    public function getIsActiveAttribute(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->is_time_limited && $this->end_date->isPast()) {
            return false;
        }

        return true;
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

    public function getDurationLabelAttribute(): string
    {
        if ($this->is_permanent) {
            return 'Permanent';
        }

        if ($this->end_date) {
            $days = $this->days_remaining;
            
            if ($days <= 0) {
                return 'Expired';
            }

            return "{$days} days remaining";
        }

        return 'N/A';
    }

    // =========================================================================
    // METHODS
    // =========================================================================

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

    public function appliesToIcdCode(string $code): bool
    {
        return in_array($code, $this->icd_codes_array);
    }

    public function setReviewDate(int $monthsFromNow = 12): bool
    {
        $this->review_date = now()->addMonths($monthsFromNow);
        
        return $this->save();
    }
}