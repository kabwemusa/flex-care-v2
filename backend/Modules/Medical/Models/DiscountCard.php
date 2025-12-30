<?php
namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountCard extends BaseModel
{
    protected $table = 'med_discount_cards';

    protected $fillable = [
        'plan_id',
        'name',
        'code',
        'type',
        'value',
        'trigger_rule',
        'valid_from',
        'valid_until'
    ];

    /**
     * Cast JSON and Dates to native types
     */
    protected $casts = [
        'trigger_rule' => 'array',
        'valid_from'   => 'date',
        'valid_until'  => 'date',
        'value'        => 'decimal:2'
    ];

    /**
     * If plan_id is null, this discount is considered "Global"
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * Scope to filter currently valid discounts
     */
    public function scopeActive($query)
    {
        return $query->where('valid_from', '<=', now())
                     ->where(function ($q) {
                         $q->whereNull('valid_until')
                           ->orWhere('valid_until', '>=', now());
                     });
    }
}