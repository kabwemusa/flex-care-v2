<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreAuthorizationNote extends Model
{
    use HasUuids;

    protected $table = 'med_preauthorization_notes';

    public $timestamps = false;

    protected $fillable = [
        'preauthorization_id',
        'note_type',
        'content',
        'previous_status',
        'new_status',
        'is_internal',
        'visible_to_provider',
        'created_by',
        'created_by_type',
        'created_at',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'visible_to_provider' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->created_at ?? now();
        });
    }

    public function preauthorization(): BelongsTo
    {
        return $this->belongsTo(PreAuthorization::class, 'preauthorization_id');
    }

    public function getNoteTypeLabelAttribute(): string
    {
        return match ($this->note_type) {
            'comment' => 'Comment',
            'status_change' => 'Status Change',
            'escalation' => 'Escalation',
            'query' => 'Query',
            'response' => 'Response',
            'system' => 'System',
            default => ucfirst($this->note_type),
        };
    }
}
