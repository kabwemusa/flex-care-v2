<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderContact extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'med_provider_contacts';

    protected $fillable = [
        'provider_id',
        'name',
        'title',
        'department',
        'email',
        'phone',
        'mobile',
        'is_primary',
        'is_billing_contact',
        'is_technical_contact',
        'receives_notifications',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_billing_contact' => 'boolean',
        'is_technical_contact' => 'boolean',
        'receives_notifications' => 'boolean',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeBilling($query)
    {
        return $query->where('is_billing_contact', true);
    }

    public function scopeTechnical($query)
    {
        return $query->where('is_technical_contact', true);
    }

    public function scopeReceivesNotifications($query)
    {
        return $query->where('receives_notifications', true);
    }
}
