<?php

namespace Modules\Medical\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class EligibilityResponse implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly bool $eligible,
        public readonly ?string $ineligibleReason = null,
        public readonly ?string $ineligibleCode = null,
        public readonly ?MemberInfo $member = null,
        public readonly ?PolicyInfo $policy = null,
        public readonly array $benefits = [],
        public readonly array $exclusions = [],
        public readonly array $waitingPeriods = [],
        public readonly ?CopayEstimate $copayEstimate = null,
        public readonly int $processingTimeMs = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'eligible' => $this->eligible,
            'ineligible_reason' => $this->ineligibleReason,
            'ineligible_code' => $this->ineligibleCode,
            'member' => $this->member?->toArray(),
            'policy' => $this->policy?->toArray(),
            'benefits' => array_map(fn($b) => $b->toArray(), $this->benefits),
            'exclusions' => $this->exclusions,
            'waiting_periods' => $this->waitingPeriods,
            'copay_estimate' => $this->copayEstimate?->toArray(),
            'processing_time_ms' => $this->processingTimeMs,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function ineligible(
        string $reason,
        string $code,
        int $processingTimeMs = 0
    ): self {
        return new self(
            eligible: false,
            ineligibleReason: $reason,
            ineligibleCode: $code,
            processingTimeMs: $processingTimeMs,
        );
    }
}

class MemberInfo implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $memberNumber,
        public readonly ?string $cardNumber,
        public readonly string $name,
        public readonly string $memberType,
        public readonly string $memberTypeLabel,
        public readonly ?int $age,
        public readonly ?string $ageBand,
        public readonly string $gender,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly ?string $cardStatus,
        public readonly ?string $photoUrl = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'member_number' => $this->memberNumber,
            'card_number' => $this->cardNumber,
            'name' => $this->name,
            'member_type' => $this->memberType,
            'member_type_label' => $this->memberTypeLabel,
            'age' => $this->age,
            'age_band' => $this->ageBand,
            'gender' => $this->gender,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'card_status' => $this->cardStatus,
            'photo_url' => $this->photoUrl,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

class PolicyInfo implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $policyNumber,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly string $planId,
        public readonly string $planName,
        public readonly string $planCode,
        public readonly string $inceptionDate,
        public readonly string $expiryDate,
        public readonly ?string $schemeName = null,
        public readonly ?string $groupName = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'policy_number' => $this->policyNumber,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'plan_id' => $this->planId,
            'plan_name' => $this->planName,
            'plan_code' => $this->planCode,
            'inception_date' => $this->inceptionDate,
            'expiry_date' => $this->expiryDate,
            'scheme_name' => $this->schemeName,
            'group_name' => $this->groupName,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

class BenefitBalance implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly string $benefitId,
        public readonly string $benefitCode,
        public readonly string $benefitName,
        public readonly string $category,
        public readonly string $categoryName,
        public readonly string $limitType,
        public readonly ?float $limit,
        public readonly float $used,
        public readonly float $reserved,
        public readonly float $available,
        public readonly float $utilizationPercent,
        public readonly bool $isExhausted,
        public readonly bool $requiresPreauth,
        public readonly ?string $copayType = null,
        public readonly ?float $copayValue = null,
        public readonly ?float $estimatedCopay = null,
        public readonly ?int $waitingDaysRemaining = null,
        public readonly bool $inWaitingPeriod = false,
        public readonly ?string $formattedLimit = null,
        public readonly ?string $formattedUsed = null,
        public readonly ?string $formattedAvailable = null,
    ) {}

    public function toArray(): array
    {
        return [
            'benefit_id' => $this->benefitId,
            'benefit_code' => $this->benefitCode,
            'benefit_name' => $this->benefitName,
            'category' => $this->category,
            'category_name' => $this->categoryName,
            'limit_type' => $this->limitType,
            'limit' => $this->limit,
            'used' => $this->used,
            'reserved' => $this->reserved,
            'available' => $this->available,
            'utilization_percent' => $this->utilizationPercent,
            'is_exhausted' => $this->isExhausted,
            'requires_preauth' => $this->requiresPreauth,
            'copay_type' => $this->copayType,
            'copay_value' => $this->copayValue,
            'estimated_copay' => $this->estimatedCopay,
            'waiting_days_remaining' => $this->waitingDaysRemaining,
            'in_waiting_period' => $this->inWaitingPeriod,
            'formatted_limit' => $this->formattedLimit,
            'formatted_used' => $this->formattedUsed,
            'formatted_available' => $this->formattedAvailable,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

class CopayEstimate implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly float $totalAmount,
        public readonly float $copayAmount,
        public readonly float $deductibleAmount,
        public readonly float $payableAmount,
        public readonly array $lineEstimates = [],
    ) {}

    public function toArray(): array
    {
        return [
            'total_amount' => $this->totalAmount,
            'copay_amount' => $this->copayAmount,
            'deductible_amount' => $this->deductibleAmount,
            'payable_amount' => $this->payableAmount,
            'line_estimates' => $this->lineEstimates,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
