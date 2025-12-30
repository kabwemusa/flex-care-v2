<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Medical\Constants\MedicalConstants;

class Group extends BaseModel
{
    protected $table = 'med_corporate_groups';

    protected $fillable = [
        'code',
        'name',
        'trading_name',
        'registration_number',
        'tax_number',
        'industry',
        'company_size',
        'employee_count',
        'email',
        'phone',
        'website',
        'physical_address',
        'city',
        'province',
        'country',
        'postal_code',
        'billing_email',
        'billing_address',
        'payment_terms',
        'preferred_payment_method',
        'account_manager_id',
        'broker_id',
        'broker_commission_rate',
        'status',
        'onboarded_at',
        'notes',
    ];

    protected $casts = [
        'employee_count' => 'integer',
        'broker_commission_rate' => 'decimal:2',
        'onboarded_at' => 'date',
    ];

    protected $attributes = [
        'country' => 'ZMW',
        'payment_terms' => MedicalConstants::PAYMENT_TERMS_30_DAYS,
        'status' => MedicalConstants::GROUP_STATUS_PROSPECT,
    ];

    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    protected function getCodePrefix(): string
    {
        return MedicalConstants::PREFIX_GROUP;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function contacts(): HasMany
    {
        return $this->hasMany(GroupContact::class, 'group_id');
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(GroupContact::class, 'group_id')
                    ->where('is_primary', true);
    }

    public function hrContacts(): HasMany
    {
        return $this->hasMany(GroupContact::class, 'group_id')
                    ->where('contact_type', MedicalConstants::CONTACT_TYPE_HR);
    }

    public function policies(): HasMany
    {
        return $this->hasMany(Policy::class, 'group_id');
    }

    public function activePolicies(): HasMany
    {
        return $this->hasMany(Policy::class, 'group_id')
                    ->where('status', MedicalConstants::POLICY_STATUS_ACTIVE);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeProspects($query)
    {
        return $query->where('status', MedicalConstants::GROUP_STATUS_PROSPECT);
    }

    public function scopeActive($query)
    {
        return $query->where('status', MedicalConstants::GROUP_STATUS_ACTIVE);
    }

    public function scopeByIndustry($query, string $industry)
    {
        return $query->where('industry', $industry);
    }

    public function scopeBySize($query, string $size)
    {
        return $query->where('company_size', $size);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'LIKE', "%{$term}%")
              ->orWhere('trading_name', 'LIKE', "%{$term}%")
              ->orWhere('code', 'LIKE', "%{$term}%")
              ->orWhere('registration_number', 'LIKE', "%{$term}%");
        });
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getStatusLabelAttribute(): string
    {
        return MedicalConstants::GROUP_STATUSES[$this->status] ?? $this->status;
    }

    public function getCompanySizeLabelAttribute(): string
    {
        return MedicalConstants::COMPANY_SIZES[$this->company_size] ?? $this->company_size ?? 'N/A';
    }

    public function getPaymentTermsLabelAttribute(): string
    {
        return MedicalConstants::PAYMENT_TERMS[$this->payment_terms] ?? $this->payment_terms;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->trading_name ?? $this->name;
    }

    public function getIsProspectAttribute(): bool
    {
        return $this->status === MedicalConstants::GROUP_STATUS_PROSPECT;
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === MedicalConstants::GROUP_STATUS_ACTIVE;
    }

    public function getTotalMembersAttribute(): int
    {
        return $this->policies()
                    ->withCount('members')
                    ->get()
                    ->sum('members_count');
    }

    public function getActiveMembersAttribute(): int
    {
        return $this->activePolicies()
                    ->withCount(['members' => fn($q) => $q->where('status', MedicalConstants::MEMBER_STATUS_ACTIVE)])
                    ->get()
                    ->sum('members_count');
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public function activate(): bool
    {
        $this->status = MedicalConstants::GROUP_STATUS_ACTIVE;
        $this->onboarded_at = $this->onboarded_at ?? now();
        
        return $this->save();
    }

    public function suspend(string $reason): bool
    {
        $this->status = MedicalConstants::GROUP_STATUS_SUSPENDED;
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Suspended: {$reason}";
        }
        
        return $this->save();
    }

    public function terminate(string $reason): bool
    {
        $this->status = MedicalConstants::GROUP_STATUS_TERMINATED;
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Terminated: {$reason}";
        }
        
        return $this->save();
    }

    public function canCreatePolicy(): bool
    {
        return in_array($this->status, [
            MedicalConstants::GROUP_STATUS_PROSPECT,
            MedicalConstants::GROUP_STATUS_ACTIVE,
        ]);
    }
}