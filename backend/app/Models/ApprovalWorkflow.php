<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ApprovalWorkflow extends Model implements Auditable
{
    use HasFactory, HasUuids, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'code',
        'entity_type',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // =========================================================================
    // ENTITY TYPE CONSTANTS
    // =========================================================================

    public const ENTITY_APPLICATION = 'application';
    public const ENTITY_CLAIM = 'claim';
    public const ENTITY_PAYMENT = 'payment';
    public const ENTITY_INVOICE = 'invoice';
    public const ENTITY_POLICY_ACTIVATION = 'policy_activation';
    public const ENTITY_SCHEME = 'scheme';
    public const ENTITY_ENDORSEMENT = 'endorsement';
    public const ENTITY_REFUND = 'refund';

    public static function getEntityTypes(): array
    {
        return [
            self::ENTITY_APPLICATION => 'Application',
            self::ENTITY_CLAIM => 'Claim',
            self::ENTITY_PAYMENT => 'Payment',
            self::ENTITY_INVOICE => 'Invoice',
            self::ENTITY_POLICY_ACTIVATION => 'Policy Activation',
            self::ENTITY_SCHEME => 'Scheme',
            self::ENTITY_ENDORSEMENT => 'Endorsement',
            self::ENTITY_REFUND => 'Refund',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the user who created this workflow
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this workflow
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the steps in this workflow
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'workflow_id')->orderBy('step_order');
    }

    /**
     * Get active steps only
     */
    public function activeSteps(): HasMany
    {
        return $this->steps()->where('is_active', true);
    }

    /**
     * Get the approval requests using this workflow
     */
    public function requests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'workflow_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope: Active workflows only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by entity type
     */
    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Get the first step of this workflow
     */
    public function getFirstStep(): ?ApprovalStep
    {
        return $this->activeSteps()->orderBy('step_order')->first();
    }

    /**
     * Get the next step after a given step
     */
    public function getNextStep(ApprovalStep $currentStep): ?ApprovalStep
    {
        return $this->activeSteps()
            ->where('step_order', '>', $currentStep->step_order)
            ->orderBy('step_order')
            ->first();
    }

    /**
     * Check if this is the last step
     */
    public function isLastStep(ApprovalStep $step): bool
    {
        return $this->getNextStep($step) === null;
    }

    /**
     * Get total number of active steps
     */
    public function getStepsCountAttribute(): int
    {
        return $this->activeSteps()->count();
    }

    /**
     * Check if this workflow has any steps configured
     */
    public function hasSteps(): bool
    {
        return $this->activeSteps()->exists();
    }
}
