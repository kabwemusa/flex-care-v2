<?php
namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RateCard extends Model
{
    protected $table = 'med_rate_cards';
    protected $fillable = ['plan_id', 'name', 'currency', 'is_active', 'valid_from', 'valid_until'];

    protected $casts = [
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(RateCardEntry::class);
    }
}