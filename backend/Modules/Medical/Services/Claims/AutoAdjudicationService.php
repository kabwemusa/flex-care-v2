<?php

namespace Modules\Medical\Services\Claims;

use Illuminate\Support\Facades\DB;
use Modules\Medical\Models\Claim;
use Modules\Medical\Models\ClaimLine;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\MemberBenefitUtilization;
use Modules\Medical\Constants\MedicalConstants;

class AutoAdjudicationService
{
    // =========================================================================
    // CONFIGURATION
    // =========================================================================

    /**
     * Criteria for auto-approval
     */
    private const AUTO_APPROVE_CRITERIA = [
        'max_claim_amount' => 10000.00,      // Claims under this can be auto-approved
        'max_fraud_score' => 40,              // Fraud score must be below this
        'max_line_amount' => 5000.00,         // Individual line amount threshold
    ];

    /**
     * Claim types that can be auto-approved
     */
    private const AUTO_APPROVE_TYPES = [
        'out_patient',
        'dental',
        'optical',
        'wellness',
    ];

    /**
     * Claim types that always require manual review
     */
    private const MANUAL_REVIEW_TYPES = [
        'in_patient',
        'maternity',
    ];

    /**
     * Service codes that always require manual review
     */
    private const MANUAL_REVIEW_SERVICE_CODES = [
        'SURG',  // Surgery
        'ICU',   // Intensive Care
        'TRANS', // Transplant
        'CHEMO', // Chemotherapy
        'DIAL',  // Dialysis
    ];

    // =========================================================================
    // MAIN ADJUDICATION
    // =========================================================================

    /**
     * Process claim through auto-adjudication rules
     */
    public function process(Claim $claim): AutoAdjudicationResult
    {
        $canAutoApprove = true;
        $reviewReasons = [];
        $lineResults = [];

        // 1. Check if claim type allows auto-approval
        if (!$this->claimTypeAllowsAutoApproval($claim, $reviewReasons)) {
            $canAutoApprove = false;
        }

        // 2. Check overall claim criteria
        if (!$this->meetsAutoApprovalCriteria($claim, $reviewReasons)) {
            $canAutoApprove = false;
        }

        // 3. Process each line
        $totalApproved = 0;
        $totalPayable = 0;
        $totalCopay = 0;

        foreach ($claim->lines as $line) {
            $lineResult = $this->processLine($claim, $line);
            $lineResults[] = $lineResult;

            if ($lineResult['requires_review']) {
                $canAutoApprove = false;
                if ($lineResult['review_reason']) {
                    $reviewReasons[] = "Line {$line->line_number}: {$lineResult['review_reason']}";
                }
            }

            $totalApproved += $lineResult['approved_amount'];
            $totalPayable += $lineResult['payable_amount'];
            $totalCopay += $lineResult['copay_amount'];

            // Update the line record
            $line->update([
                'approved_amount' => $lineResult['approved_amount'],
                'copay_amount' => $lineResult['copay_amount'] ?? 0,
                'payable_amount' => $lineResult['payable_amount'],
                'status' => $lineResult['status'],
                'rejection_reason' => $lineResult['rejection_reason'] ?? null,
                'adjudication_notes' => $lineResult['notes'] ?? null,
                'auto_adjudicated' => true,
                'auto_adjudication_details' => $lineResult,
            ]);
        }

        // 4. Determine overall status
        $status = $this->determineOverallStatus($canAutoApprove, $lineResults);

        return new AutoAdjudicationResult(
            canAutoApprove: $canAutoApprove,
            status: $status,
            lineResults: $lineResults,
            approvedAmount: round($totalApproved, 2),
            payableAmount: round($totalPayable, 2),
            copayAmount: round($totalCopay, 2),
            reviewReasons: $reviewReasons,
            reviewReason: !empty($reviewReasons) ? implode('; ', array_slice($reviewReasons, 0, 3)) : null,
        );
    }

    // =========================================================================
    // CLAIM LEVEL CHECKS
    // =========================================================================

    private function claimTypeAllowsAutoApproval(Claim $claim, array &$reviewReasons): bool
    {
        if (in_array($claim->claim_type, self::MANUAL_REVIEW_TYPES)) {
            $reviewReasons[] = "Claim type '{$claim->claim_type}' requires manual review";
            return false;
        }

        return true;
    }

    private function meetsAutoApprovalCriteria(Claim $claim, array &$reviewReasons): bool
    {
        $meetsAll = true;

        // Check total amount
        if ($claim->claimed_amount > self::AUTO_APPROVE_CRITERIA['max_claim_amount']) {
            $reviewReasons[] = "Claim amount exceeds auto-approval threshold";
            $meetsAll = false;
        }

        // Check fraud score
        if (($claim->fraud_score ?? 0) > self::AUTO_APPROVE_CRITERIA['max_fraud_score']) {
            $reviewReasons[] = "Fraud score ({$claim->fraud_score}) exceeds threshold";
            $meetsAll = false;
        }

        // Check if flagged
        if ($claim->is_flagged) {
            $reviewReasons[] = "Claim is flagged: {$claim->flag_reason}";
            $meetsAll = false;
        }

        // Check for pre-auth requirement
        if ($this->requiresPreAuth($claim) && !$claim->preauthorization_id) {
            $reviewReasons[] = "Claim requires pre-authorization";
            $meetsAll = false;
        }

        return $meetsAll;
    }

    private function requiresPreAuth(Claim $claim): bool
    {
        // Check if any benefit requires pre-auth
        foreach ($claim->lines as $line) {
            if ($line->benefit && $line->benefit->requires_preauth) {
                return true;
            }
        }

        return false;
    }

    // =========================================================================
    // LINE LEVEL PROCESSING
    // =========================================================================

    private function processLine(Claim $claim, ClaimLine $line): array
    {
        $member = $claim->member;
        $requiresReview = false;
        $reviewReason = null;
        $rejectionReason = null;

        // 1. Check for manual review service codes
        if ($this->lineRequiresManualReview($line)) {
            $requiresReview = true;
            $reviewReason = "Service requires manual review";
        }

        // 2. Check line amount threshold
        if ($line->claimed_amount > self::AUTO_APPROVE_CRITERIA['max_line_amount']) {
            $requiresReview = true;
            $reviewReason = "Amount exceeds line threshold";
        }

        // 3. Check benefit eligibility
        $eligibility = $this->checkBenefitEligibility($member, $line, $claim);

        if (!$eligibility['eligible']) {
            return [
                'line_id' => $line->id,
                'line_number' => $line->line_number,
                'status' => 'rejected',
                'approved_amount' => 0,
                'copay_amount' => 0,
                'payable_amount' => 0,
                'rejection_reason' => $eligibility['reason_code'],
                'requires_review' => false, // Can auto-reject
                'review_reason' => null,
                'notes' => $eligibility['reason'],
            ];
        }

        // 4. Calculate approved amount (capped by available benefit)
        $approvedAmount = min(
            $line->claimed_amount,
            $eligibility['available_amount'] ?? $line->claimed_amount
        );

        // 5. Apply copay
        $copay = $this->calculateLineCopay($claim, $line, $approvedAmount);

        // 6. Calculate payable
        $payableAmount = max(0, $approvedAmount - $copay['amount']);

        // 7. Determine status
        $status = 'approved';
        if ($approvedAmount < $line->claimed_amount) {
            $status = 'partially_approved';
            if (!$requiresReview) {
                $reviewReason = "Partial approval due to benefit limit";
            }
        }

        if ($requiresReview) {
            $status = 'pending';
        }

        return [
            'line_id' => $line->id,
            'line_number' => $line->line_number,
            'status' => $status,
            'claimed_amount' => $line->claimed_amount,
            'approved_amount' => round($approvedAmount, 2),
            'copay_amount' => round($copay['amount'], 2),
            'copay_type' => $copay['type'],
            'copay_value' => $copay['value'],
            'payable_amount' => round($payableAmount, 2),
            'rejection_reason' => $rejectionReason,
            'requires_review' => $requiresReview,
            'review_reason' => $reviewReason,
            'benefit_limit' => $eligibility['limit'] ?? null,
            'benefit_used' => $eligibility['used'] ?? null,
            'benefit_available' => $eligibility['available_amount'] ?? null,
            'notes' => null,
        ];
    }

    private function lineRequiresManualReview(ClaimLine $line): bool
    {
        $serviceCode = $line->service_code ?? '';

        foreach (self::MANUAL_REVIEW_SERVICE_CODES as $code) {
            if (str_starts_with(strtoupper($serviceCode), $code)) {
                return true;
            }
        }

        return false;
    }

    private function checkBenefitEligibility(Member $member, ClaimLine $line, Claim $claim): array
    {
        if (!$line->benefit_id) {
            // No benefit specified - approve at full amount
            return [
                'eligible' => true,
                'available_amount' => $line->claimed_amount,
            ];
        }

        // Get current utilization
        $utilization = MemberBenefitUtilization::where('member_id', $member->id)
            ->where('benefit_id', $line->benefit_id)
            ->where('period_start', '<=', $claim->service_date)
            ->where('period_end', '>=', $claim->service_date)
            ->first();

        if (!$utilization) {
            // No utilization record - might be first claim, check plan benefit
            $planBenefit = DB::table('med_plan_benefits')
                ->where('plan_id', $claim->policy->plan_id)
                ->where('benefit_id', $line->benefit_id)
                ->where('is_active', true)
                ->first();

            if (!$planBenefit) {
                return [
                    'eligible' => false,
                    'reason' => 'Benefit not covered under plan',
                    'reason_code' => 'BENEFIT_NOT_COVERED',
                ];
            }

            // Create utilization record would be done separately
            return [
                'eligible' => true,
                'limit' => $planBenefit->limit_amount,
                'used' => 0,
                'available_amount' => $planBenefit->limit_amount ?? $line->claimed_amount,
            ];
        }

        // Check if exhausted
        if ($utilization->is_exhausted) {
            return [
                'eligible' => false,
                'reason' => 'Benefit limit exhausted',
                'reason_code' => 'BENEFIT_EXHAUSTED',
            ];
        }

        // Calculate available (accounting for reservations)
        $available = $utilization->remaining_amount - ($utilization->reserved_amount ?? 0);

        if ($available <= 0) {
            return [
                'eligible' => false,
                'reason' => 'No remaining benefit balance',
                'reason_code' => 'NO_BALANCE',
            ];
        }

        return [
            'eligible' => true,
            'limit' => $utilization->limit_amount,
            'used' => $utilization->used_amount,
            'reserved' => $utilization->reserved_amount ?? 0,
            'available_amount' => $available,
        ];
    }

    private function calculateLineCopay(Claim $claim, ClaimLine $line, float $approvedAmount): array
    {
        $copayAmount = 0;
        $copayType = null;
        $copayValue = null;

        if (!$line->benefit_id) {
            return ['amount' => 0, 'type' => null, 'value' => null];
        }

        // Get copay configuration from plan benefit
        $planBenefit = DB::table('med_plan_benefits')
            ->where('plan_id', $claim->policy->plan_id)
            ->where('benefit_id', $line->benefit_id)
            ->first();

        if ($planBenefit && $planBenefit->cost_sharing) {
            $costSharing = json_decode($planBenefit->cost_sharing, true);

            if (isset($costSharing['copay'])) {
                $copayConfig = $costSharing['copay'];
                $copayType = $copayConfig['type'] ?? null;
                $copayValue = $copayConfig['value'] ?? null;

                if ($copayType === 'percentage' && $copayValue) {
                    $copayAmount = $approvedAmount * ($copayValue / 100);
                } elseif ($copayType === 'fixed' && $copayValue) {
                    $copayAmount = min($copayValue, $approvedAmount);
                }
            }
        }

        return [
            'amount' => round($copayAmount, 2),
            'type' => $copayType,
            'value' => $copayValue,
        ];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function determineOverallStatus(bool $canAutoApprove, array $lineResults): string
    {
        if (!$canAutoApprove) {
            return MedicalConstants::CLAIM_STATUS_PENDING;
        }

        $hasRejected = false;
        $hasApproved = false;
        $hasPending = false;

        foreach ($lineResults as $result) {
            match ($result['status']) {
                'approved', 'partially_approved' => $hasApproved = true,
                'rejected' => $hasRejected = true,
                'pending' => $hasPending = true,
                default => null,
            };
        }

        if ($hasPending) {
            return MedicalConstants::CLAIM_STATUS_PENDING;
        }

        if ($hasApproved && $hasRejected) {
            return MedicalConstants::CLAIM_STATUS_PARTIALLY_APPROVED;
        }

        if ($hasApproved) {
            return MedicalConstants::CLAIM_STATUS_APPROVED;
        }

        if ($hasRejected) {
            return MedicalConstants::CLAIM_STATUS_REJECTED;
        }

        return MedicalConstants::CLAIM_STATUS_PENDING;
    }
}

// =========================================================================
// RESULT DTO
// =========================================================================

class AutoAdjudicationResult
{
    public function __construct(
        public readonly bool $canAutoApprove,
        public readonly string $status,
        public readonly array $lineResults,
        public readonly float $approvedAmount,
        public readonly float $payableAmount,
        public readonly float $copayAmount,
        public readonly array $reviewReasons = [],
        public readonly ?string $reviewReason = null,
    ) {}

    public function toArray(): array
    {
        return [
            'can_auto_approve' => $this->canAutoApprove,
            'status' => $this->status,
            'approved_amount' => $this->approvedAmount,
            'payable_amount' => $this->payableAmount,
            'copay_amount' => $this->copayAmount,
            'review_reasons' => $this->reviewReasons,
            'line_count' => count($this->lineResults),
            'approved_lines' => collect($this->lineResults)->where('status', 'approved')->count(),
            'rejected_lines' => collect($this->lineResults)->where('status', 'rejected')->count(),
        ];
    }
}
