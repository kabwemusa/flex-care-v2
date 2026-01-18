<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Medical\Constants\MedicalConstants;

class InvoiceItem extends BaseModel
{
    protected $table = 'med_invoice_items';

    protected $fillable = [
        'invoice_id',
        'member_id',
        'line_number',
        'item_type',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'tax_amount',
        'total',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected $attributes = [
        'line_number' => 1,
        'quantity' => 1,
        'unit_price' => 0,
        'amount' => 0,
        'tax_amount' => 0,
        'total' => 0,
    ];

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted()
    {
        parent::boot();

        static::saving(function ($model) {
            // Auto-calculate amounts
            $model->amount = $model->quantity * $model->unit_price;

            // For discounts, amount should be negative
            if ($model->item_type === MedicalConstants::INVOICE_ITEM_DISCOUNT) {
                $model->amount = -abs($model->amount);
                $model->total = $model->amount;
            } else {
                $model->total = $model->amount + $model->tax_amount;
            }
        });

        static::saved(function ($model) {
            // Recalculate invoice totals
            $model->invoice?->recalculateTotals();
        });

        static::deleted(function ($model) {
            // Recalculate invoice totals
            $model->invoice?->recalculateTotals();
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getItemTypeLabelAttribute(): string
    {
        return MedicalConstants::INVOICE_ITEM_TYPES[$this->item_type] ?? $this->item_type;
    }

    public function getIsDiscountAttribute(): bool
    {
        return $this->item_type === MedicalConstants::INVOICE_ITEM_DISCOUNT;
    }

    public function getIsTaxAttribute(): bool
    {
        return $this->item_type === MedicalConstants::INVOICE_ITEM_TAX;
    }

    public function getIsPremiumAttribute(): bool
    {
        return in_array($this->item_type, [
            MedicalConstants::INVOICE_ITEM_BASE_PREMIUM,
            MedicalConstants::INVOICE_ITEM_MEMBER_PREMIUM,
            MedicalConstants::INVOICE_ITEM_ADDON_PREMIUM,
        ]);
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Get the referenced entity (member, addon, etc.).
     */
    public function getReference(): ?BaseModel
    {
        if (!$this->reference_type || !$this->reference_id) {
            return null;
        }

        return match ($this->reference_type) {
            'member' => Member::find($this->reference_id),
            'addon' => Addon::find($this->reference_id),
            'policy_addon' => PolicyAddon::find($this->reference_id),
            'loading' => MemberLoading::find($this->reference_id),
            default => null,
        };
    }
}
