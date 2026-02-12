<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreAuthorizationLine extends Model
{
    use HasUuids;

    protected $table = 'med_preauthorization_lines';

    protected $fillable = [
        'preauthorization_id',
        'line_number',
        'benefit_id',
        'service_code',
        'service_description',
        'service_type',
        'quantity',
        'unit',
        'unit_price',
        'requested_amount',
        'approved_amount',
        'reserved_amount',
        'benefit_limit',
        'benefit_used_before',
        'benefit_available',
        'status',
        'rejection_reason',
        'notes',
        'decided_at',
        'decided_by',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'reserved_amount' => 'decimal:2',
        'benefit_limit' => 'decimal:2',
        'benefit_used_before' => 'decimal:2',
        'benefit_available' => 'decimal:2',
        'decided_at' => 'datetime',
    ];

    public function preauthorization(): BelongsTo
    {
        return $this->belongsTo(PreAuthorization::class, 'preauthorization_id');
    }

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'approved' => 'Approved',
            'partially_approved' => 'Partially Approved',
            'rejected' => 'Rejected',
            default => ucfirst($this->status),
        };
    }
}
