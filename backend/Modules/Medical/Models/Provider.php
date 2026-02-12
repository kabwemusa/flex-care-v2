<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Medical\Constants\MedicalConstants;

class Provider extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'med_providers';

    // =========================================================================
    // CONSTANTS
    // =========================================================================

    public const TYPE_HOSPITAL = 'hospital';
    public const TYPE_CLINIC = 'clinic';
    public const TYPE_PHARMACY = 'pharmacy';
    public const TYPE_LAB = 'lab';
    public const TYPE_OPTICAL = 'optical';
    public const TYPE_DENTAL = 'dental';
    public const TYPE_SPECIALIST = 'specialist';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_TERMINATED = 'terminated';

    public const NETWORK_TIER_1 = 'tier1';
    public const NETWORK_TIER_2 = 'tier2';
    public const NETWORK_TIER_3 = 'tier3';

    public const RISK_LOW = 'low';
    public const RISK_MEDIUM = 'medium';
    public const RISK_HIGH = 'high';

    // =========================================================================
    // FILLABLE
    // =========================================================================

    protected $fillable = [
        'provider_code',
        'name',
        'trading_name',
        'provider_type',
        'license_number',
        'tax_id',
        'registration_number',
        'address',
        'city',
        'region',
        'country',
        'postal_code',
        'phone',
        'email',
        'website',
        'contact_person',
        'contact_phone',
        'contact_email',
        'is_network_provider',
        'network_tier',
        'contract_start_date',
        'contract_end_date',
        'contracted_services',
        'discount_percentage',
        'bank_name',
        'bank_branch',
        'account_name',
        'account_number',
        'swift_code',
        'status',
        'status_changed_at',
        'status_reason',
        'approved_by',
        'approved_at',
        'fraud_risk_score',
        'fraud_risk_level',
        'fraud_score_updated_at',
        'fraud_flags',
        'total_claims_count',
        'total_claims_amount',
        'approved_claims_count',
        'approved_claims_amount',
        'rejected_claims_count',
        'average_claim_amount',
        'rejection_rate',
        'stats_updated_at',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'is_network_provider' => 'boolean',
        'discount_percentage' => 'decimal:2',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'contracted_services' => 'array',
        'status_changed_at' => 'datetime',
        'approved_at' => 'datetime',
        'fraud_risk_score' => 'decimal:2',
        'fraud_score_updated_at' => 'datetime',
        'fraud_flags' => 'array',
        'total_claims_count' => 'integer',
        'total_claims_amount' => 'decimal:2',
        'approved_claims_count' => 'integer',
        'approved_claims_amount' => 'decimal:2',
        'rejected_claims_count' => 'integer',
        'average_claim_amount' => 'decimal:2',
        'rejection_rate' => 'decimal:4',
        'stats_updated_at' => 'datetime',
        'metadata' => 'array',
    ];

    // =========================================================================
    // BOOT
    // =========================================================================

    protected static function booted()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->provider_code)) {
                $model->provider_code = static::generateProviderCode($model->provider_type);
            }
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function apiCredentials(): HasMany
    {
        return $this->hasMany(ProviderApiCredential::class);
    }

    public function activeApiCredentials(): HasMany
    {
        return $this->hasMany(ProviderApiCredential::class)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ProviderApiLog::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ProviderContact::class);
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(ProviderContact::class)->where('is_primary', true);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class, 'provider_id_fk');
    }

    public function preauthorizations(): HasMany
    {
        return $this->hasMany(PreAuthorization::class);
    }

    public function riskProfile(): HasOne
    {
        return $this->hasOne(ProviderRiskProfile::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', self::STATUS_SUSPENDED);
    }

    public function scopeNetworkProviders($query)
    {
        return $query->where('is_network_provider', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('provider_type', $type);
    }

    public function scopeHighRisk($query)
    {
        return $query->where('fraud_risk_level', self::RISK_HIGH);
    }

    public function scopeInCity($query, string $city)
    {
        return $query->where('city', $city);
    }

    public function scopeWithActiveContract($query)
    {
        return $query->where('is_network_provider', true)
            ->where('contract_start_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('contract_end_date')
                    ->orWhere('contract_end_date', '>=', now());
            });
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    public function getProviderTypeLabel(): string
    {
        return match ($this->provider_type) {
            self::TYPE_HOSPITAL => 'Hospital',
            self::TYPE_CLINIC => 'Clinic',
            self::TYPE_PHARMACY => 'Pharmacy',
            self::TYPE_LAB => 'Laboratory',
            self::TYPE_OPTICAL => 'Optical',
            self::TYPE_DENTAL => 'Dental',
            self::TYPE_SPECIALIST => 'Specialist',
            default => ucfirst($this->provider_type),
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending Approval',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_TERMINATED => 'Terminated',
            default => ucfirst($this->status),
        };
    }

    public function getNetworkTierLabel(): ?string
    {
        if (!$this->network_tier) {
            return null;
        }

        return match ($this->network_tier) {
            self::NETWORK_TIER_1 => 'Tier 1 (Premium)',
            self::NETWORK_TIER_2 => 'Tier 2 (Standard)',
            self::NETWORK_TIER_3 => 'Tier 3 (Basic)',
            default => ucfirst($this->network_tier),
        };
    }

    public function getRiskLevelLabel(): string
    {
        return match ($this->fraud_risk_level) {
            self::RISK_LOW => 'Low Risk',
            self::RISK_MEDIUM => 'Medium Risk',
            self::RISK_HIGH => 'High Risk',
            default => ucfirst($this->fraud_risk_level),
        };
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getIsSuspendedAttribute(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function getHasActiveContractAttribute(): bool
    {
        if (!$this->is_network_provider || !$this->contract_start_date) {
            return false;
        }

        $now = now();
        return $this->contract_start_date <= $now
            && (!$this->contract_end_date || $this->contract_end_date >= $now);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->region,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    public static function generateProviderCode(string $type): string
    {
        $prefix = match ($type) {
            self::TYPE_HOSPITAL => 'HOS',
            self::TYPE_CLINIC => 'CLN',
            self::TYPE_PHARMACY => 'PHR',
            self::TYPE_LAB => 'LAB',
            self::TYPE_OPTICAL => 'OPT',
            self::TYPE_DENTAL => 'DNT',
            self::TYPE_SPECIALIST => 'SPC',
            default => 'PRV',
        };

        $year = now()->format('y');
        $sequence = static::whereYear('created_at', now()->year)->count() + 1;

        return sprintf('%s%s%05d', $prefix, $year, $sequence);
    }

    public function activate(?string $approvedBy = null): bool
    {
        if ($this->status === self::STATUS_ACTIVE) {
            return true;
        }

        $this->status = self::STATUS_ACTIVE;
        $this->status_changed_at = now();
        $this->status_reason = null;
        $this->approved_by = $approvedBy;
        $this->approved_at = now();

        return $this->save();
    }

    public function suspend(string $reason, ?string $suspendedBy = null): bool
    {
        $this->status = self::STATUS_SUSPENDED;
        $this->status_changed_at = now();
        $this->status_reason = $reason;

        // Deactivate all API credentials
        $this->apiCredentials()->update(['is_active' => false]);

        return $this->save();
    }

    public function terminate(string $reason, ?string $terminatedBy = null): bool
    {
        $this->status = self::STATUS_TERMINATED;
        $this->status_changed_at = now();
        $this->status_reason = $reason;

        // Revoke all API credentials
        $this->apiCredentials()->update([
            'is_active' => false,
            'revoked_at' => now(),
            'revoked_reason' => 'Provider terminated',
        ]);

        return $this->save();
    }

    public function updateStatistics(): void
    {
        $stats = $this->claims()
            ->selectRaw('
                COUNT(*) as total_claims,
                SUM(claimed_amount) as total_amount,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = ? THEN approved_amount ELSE 0 END) as approved_amount,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected_count,
                AVG(claimed_amount) as avg_amount
            ', [
                MedicalConstants::CLAIM_STATUS_APPROVED,
                MedicalConstants::CLAIM_STATUS_APPROVED,
                MedicalConstants::CLAIM_STATUS_REJECTED,
            ])
            ->first();

        $this->total_claims_count = $stats->total_claims ?? 0;
        $this->total_claims_amount = $stats->total_amount ?? 0;
        $this->approved_claims_count = $stats->approved_count ?? 0;
        $this->approved_claims_amount = $stats->approved_amount ?? 0;
        $this->rejected_claims_count = $stats->rejected_count ?? 0;
        $this->average_claim_amount = $stats->avg_amount ?? 0;

        if ($this->total_claims_count > 0) {
            $this->rejection_rate = $this->rejected_claims_count / $this->total_claims_count;
        }

        $this->stats_updated_at = now();
        $this->save();
    }

    public function updateFraudRiskScore(int $score, ?array $flags = null): void
    {
        $this->fraud_risk_score = $score;
        $this->fraud_risk_level = match (true) {
            $score >= 75 => self::RISK_HIGH,
            $score >= 40 => self::RISK_MEDIUM,
            default => self::RISK_LOW,
        };

        if ($flags !== null) {
            $this->fraud_flags = $flags;
        }

        $this->fraud_score_updated_at = now();
        $this->save();
    }

    public function canSubmitClaims(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->activeApiCredentials()->exists();
    }

    public function getNetworkDiscount(): float
    {
        if (!$this->is_network_provider || !$this->has_active_contract) {
            return 0;
        }

        return (float) ($this->discount_percentage ?? 0);
    }
}
