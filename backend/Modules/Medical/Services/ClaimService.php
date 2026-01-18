<?php

namespace Modules\Medical\Services;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Modules\Medical\Models\Claim;
use Modules\Medical\Models\ClaimLine;
use Modules\Medical\Models\ClaimDocument;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\Member;
use Modules\Medical\Constants\MedicalConstants;
use Modules\Medical\Services\BenefitUtilizationService;

class ClaimService
{
    protected BenefitUtilizationService $benefitUtilizationService;

    public function __construct(BenefitUtilizationService $benefitUtilizationService)
    {
        $this->benefitUtilizationService = $benefitUtilizationService;
    }
    // =========================================================================
    // CLAIM CREATION
    // =========================================================================

    /**
     * Create a new claim.
     */
    public function createClaim(array $data): Claim
    {
        return DB::transaction(function () use ($data) {
            // Validate policy and member
            $policy = Policy::findOrFail($data['policy_id']);
            $member = Member::findOrFail($data['member_id']);

            // Validation checks
            $this->validateClaimEligibility($policy, $member, $data);

            // Create claim
            $claim = Claim::create([
                'policy_id' => $data['policy_id'],
                'member_id' => $data['member_id'],
                'claim_type' => $data['claim_type'],
                'submission_type' => $data['submission_type'] ?? MedicalConstants::SUBMISSION_TYPE_MEMBER,
                'submission_channel' => $data['submission_channel'] ?? MedicalConstants::SUBMISSION_CHANNEL_PORTAL,
                'service_date' => $data['service_date'],
                'service_end_date' => $data['service_end_date'] ?? null,
                'admission_date' => $data['admission_date'] ?? null,
                'discharge_date' => $data['discharge_date'] ?? null,
                'days_admitted' => $data['days_admitted'] ?? null,
                'provider_name' => $data['provider_name'] ?? null,
                'provider_type' => $data['provider_type'] ?? null,
                'provider_invoice_number' => $data['provider_invoice_number'] ?? null,
                'primary_diagnosis' => $data['primary_diagnosis'] ?? null,
                'primary_icd_code' => $data['primary_icd_code'] ?? null,
                'secondary_diagnoses' => $data['secondary_diagnoses'] ?? null,
                'diagnosis_notes' => $data['diagnosis_notes'] ?? null,
                'currency' => $data['currency'] ?? MedicalConstants::DEFAULT_CURRENCY,
                'claimed_amount' => $data['claimed_amount'],
                'requires_preauth' => $data['requires_preauth'] ?? false,
                'preauth_number' => $data['preauth_number'] ?? null,
                'status' => MedicalConstants::CLAIM_STATUS_SUBMITTED,
                'received_at' => now(),
            ]);

            // Add claim lines if provided
            if (!empty($data['lines'])) {
                $this->addClaimLines($claim, $data['lines']);
            }

            // Add system note
            $claim->addNote(
                MedicalConstants::CLAIM_NOTE_SYSTEM,
                'Claim submitted',
                null,
                ['is_system' => true, 'new_status' => MedicalConstants::CLAIM_STATUS_SUBMITTED]
            );

            return $claim->fresh(['policy', 'member', 'lines']);
        });
    }

    /**
     * Add lines to a claim.
     */
    public function addClaimLines(Claim $claim, array $lines): void
    {
        $lineNumber = $claim->lines()->max('line_number') ?? 0;

        foreach ($lines as $line) {
            $lineNumber++;
            $claim->lines()->create([
                'line_number' => $lineNumber,
                'benefit_id' => $line['benefit_id'] ?? null,
                'service_code' => $line['service_code'] ?? null,
                'service_description' => $line['service_description'],
                'service_date' => $line['service_date'] ?? $claim->service_date,
                'quantity' => $line['quantity'] ?? 1,
                'unit' => $line['unit'] ?? null,
                'unit_price' => $line['unit_price'] ?? $line['claimed_amount'],
                'claimed_amount' => $line['claimed_amount'],
            ]);
        }

        // Recalculate claim totals
        $claim->recalculateTotals();
    }

    // =========================================================================
    // CLAIM VALIDATION
    // =========================================================================

    /**
     * Validate claim eligibility.
     */
    protected function validateClaimEligibility(Policy $policy, Member $member, array $data): void
    {
        // Check policy is active
        if ($policy->status !== MedicalConstants::POLICY_STATUS_ACTIVE) {
            throw new Exception('Policy is not active. Current status: ' . $policy->status);
        }

        // Check member belongs to policy
        if ($member->policy_id !== $policy->id) {
            throw new Exception('Member does not belong to this policy');
        }

        // Check member is active
        if ($member->status !== MedicalConstants::MEMBER_STATUS_ACTIVE) {
            throw new Exception('Member is not active. Current status: ' . $member->status);
        }

        // Check service date is within policy period
        $serviceDate = Carbon::parse($data['service_date']);
        if ($serviceDate->lt($policy->inception_date) || $serviceDate->gt($policy->expiry_date)) {
            throw new Exception('Service date must be within policy period');
        }

        // Check service date is not in the future
        if ($serviceDate->gt(now())) {
            throw new Exception('Service date cannot be in the future');
        }

        // Check member was active on service date
        if ($member->effective_date && $serviceDate->lt($member->effective_date)) {
            throw new Exception('Service date is before member coverage start date');
        }

        if ($member->termination_date && $serviceDate->gt($member->termination_date)) {
            throw new Exception('Service date is after member coverage end date');
        }
    }

    // =========================================================================
    // CLAIM WORKFLOW
    // =========================================================================

    /**
     * Start review of a claim.
     */
    public function startReview(Claim $claim, string $reviewerId): Claim
    {
        if (!in_array($claim->status, [
            MedicalConstants::CLAIM_STATUS_SUBMITTED,
            MedicalConstants::CLAIM_STATUS_PENDING_DOCUMENTS,
        ])) {
            throw new Exception('Claim cannot be reviewed in current status: ' . $claim->status);
        }

        $oldStatus = $claim->status;

        $claim->update([
            'status' => MedicalConstants::CLAIM_STATUS_IN_REVIEW,
            'assigned_to' => $reviewerId,
            'assigned_at' => now(),
            'first_response_at' => $claim->first_response_at ?? now(),
        ]);

        $claim->addNote(
            MedicalConstants::CLAIM_NOTE_STATUS_CHANGE,
            'Claim review started',
            $reviewerId,
            ['old_status' => $oldStatus, 'new_status' => MedicalConstants::CLAIM_STATUS_IN_REVIEW, 'is_system' => true]
        );

        return $claim->fresh();
    }

    /**
     * Request additional documents.
     */
    public function requestDocuments(Claim $claim, string $requestedBy, array $requiredDocs, ?string $notes = null): Claim
    {
        $oldStatus = $claim->status;

        $claim->update([
            'status' => MedicalConstants::CLAIM_STATUS_PENDING_DOCUMENTS,
            'substatus' => 'awaiting_documents',
        ]);

        $docList = implode(', ', $requiredDocs);
        $claim->addNote(
            MedicalConstants::CLAIM_NOTE_QUERY,
            "Additional documents requested: {$docList}" . ($notes ? ". Notes: {$notes}" : ''),
            $requestedBy,
            ['old_status' => $oldStatus, 'new_status' => MedicalConstants::CLAIM_STATUS_PENDING_DOCUMENTS]
        );

        return $claim->fresh();
    }

    /**
     * Adjudicate claim lines and approve/reject.
     */
    public function adjudicateClaim(Claim $claim, array $lineDecisions, string $adjudicatorId): Claim
    {
        if (!$claim->can_be_approved) {
            throw new Exception('Claim cannot be adjudicated in current status: ' . $claim->status);
        }

        return DB::transaction(function () use ($claim, $lineDecisions, $adjudicatorId) {
            $oldStatus = $claim->status;
            $hasApproved = false;
            $hasRejected = false;

            foreach ($lineDecisions as $decision) {
                $line = $claim->lines()->findOrFail($decision['line_id']);

                if ($decision['action'] === 'approve') {
                    $approvedAmount = $decision['approved_amount'] ?? $line->claimed_amount;
                    $line->update([
                        'approved_amount' => $approvedAmount,
                        'copay_amount' => $decision['copay_amount'] ?? 0,
                        'deductible_amount' => $decision['deductible_amount'] ?? 0,
                        'excess_amount' => $decision['excess_amount'] ?? 0,
                        'excluded_amount' => $decision['excluded_amount'] ?? 0,
                        'payable_amount' => max(0, $approvedAmount 
                            - ($decision['copay_amount'] ?? 0)
                            - ($decision['deductible_amount'] ?? 0)
                            - ($decision['excess_amount'] ?? 0)
                            - ($decision['excluded_amount'] ?? 0)),
                        'status' => $approvedAmount >= $line->claimed_amount
                            ? MedicalConstants::CLAIM_LINE_STATUS_APPROVED
                            : MedicalConstants::CLAIM_LINE_STATUS_PARTIALLY_APPROVED,
                        'adjudication_notes' => $decision['notes'] ?? null,
                    ]);
                    $hasApproved = true;
                } elseif ($decision['action'] === 'reject') {
                    $line->update([
                        'approved_amount' => 0,
                        'payable_amount' => 0,
                        'status' => MedicalConstants::CLAIM_LINE_STATUS_REJECTED,
                        'rejection_reason' => $decision['reason'] ?? MedicalConstants::REJECTION_OTHER,
                        'adjudication_notes' => $decision['notes'] ?? null,
                    ]);
                    $hasRejected = true;
                }
            }

            // Recalculate claim totals
            $claim->recalculateTotals();

            // Determine overall claim status
            $newStatus = $this->determineClaimStatus($hasApproved, $hasRejected);

            $claim->update([
                'status' => $newStatus,
                'processed_by' => $adjudicatorId,
                'processed_at' => now(),
            ]);

            // Record benefit utilization for approved lines
            if ($hasApproved) {
                $this->benefitUtilizationService->recordClaimUsage($claim, $adjudicatorId);
            }

            $claim->addNote(
                MedicalConstants::CLAIM_NOTE_STATUS_CHANGE,
                'Claim adjudicated. ' . count($lineDecisions) . ' line(s) processed.',
                $adjudicatorId,
                ['old_status' => $oldStatus, 'new_status' => $newStatus, 'is_system' => true]
            );

            return $claim->fresh(['lines']);
        });
    }

    /**
     * Approve entire claim (quick approval).
     */
    public function approveClaim(Claim $claim, string $approverId, ?string $notes = null): Claim
    {
        if (!$claim->can_be_approved) {
            throw new Exception('Claim cannot be approved in current status: ' . $claim->status);
        }

        return DB::transaction(function () use ($claim, $approverId, $notes) {
            $oldStatus = $claim->status;

            // Approve all pending lines
            $claim->lines()->where('status', MedicalConstants::CLAIM_LINE_STATUS_PENDING)->each(function ($line) {
                $line->update([
                    'approved_amount' => $line->claimed_amount,
                    'payable_amount' => $line->claimed_amount - $line->copay_amount - $line->deductible_amount,
                    'status' => MedicalConstants::CLAIM_LINE_STATUS_APPROVED,
                ]);
            });

            // Recalculate totals
            $claim->recalculateTotals();

            // Update claim status
            $claim->update([
                'status' => MedicalConstants::CLAIM_STATUS_APPROVED,
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            // Record benefit utilization for approved lines
            $this->benefitUtilizationService->recordClaimUsage($claim, $approverId);

            $claim->addNote(
                MedicalConstants::CLAIM_NOTE_STATUS_CHANGE,
                'Claim approved' . ($notes ? ": {$notes}" : ''),
                $approverId,
                ['old_status' => $oldStatus, 'new_status' => MedicalConstants::CLAIM_STATUS_APPROVED, 'is_system' => true]
            );

            return $claim->fresh(['lines']);
        });
    }

    /**
     * Reject entire claim.
     */
    public function rejectClaim(Claim $claim, string $rejectedBy, string $reason, ?string $notes = null): Claim
    {
        if (!in_array($claim->status, [
            MedicalConstants::CLAIM_STATUS_SUBMITTED,
            MedicalConstants::CLAIM_STATUS_IN_REVIEW,
            MedicalConstants::CLAIM_STATUS_PENDING_APPROVAL,
        ])) {
            throw new Exception('Claim cannot be rejected in current status: ' . $claim->status);
        }

        $oldStatus = $claim->status;

        // Reject all lines
        $claim->lines()->update([
            'status' => MedicalConstants::CLAIM_LINE_STATUS_REJECTED,
            'approved_amount' => 0,
            'payable_amount' => 0,
            'rejection_reason' => $reason,
        ]);

        $claim->update([
            'status' => MedicalConstants::CLAIM_STATUS_REJECTED,
            'approved_amount' => 0,
            'payable_amount' => 0,
            'rejection_reason' => $reason,
            'rejection_notes' => $notes,
            'rejected_by' => $rejectedBy,
            'rejected_at' => now(),
            'completed_at' => now(),
            'tat_days' => $claim->calculateTat(),
        ]);

        $claim->addNote(
            MedicalConstants::CLAIM_NOTE_STATUS_CHANGE,
            "Claim rejected. Reason: {$reason}" . ($notes ? ". Notes: {$notes}" : ''),
            $rejectedBy,
            ['old_status' => $oldStatus, 'new_status' => MedicalConstants::CLAIM_STATUS_REJECTED, 'is_system' => true]
        );

        return $claim->fresh();
    }

    /**
     * Record payment for a claim.
     */
    public function recordPayment(Claim $claim, array $paymentData, string $processedBy): Claim
    {
        if (!$claim->can_be_paid) {
            throw new Exception('Claim cannot be paid in current status or has no outstanding amount');
        }

        $oldStatus = $claim->status;
        $paymentAmount = $paymentData['amount'];

        if ($paymentAmount > $claim->outstanding_amount) {
            throw new Exception('Payment amount exceeds outstanding amount');
        }

        $newPaidAmount = $claim->paid_amount + $paymentAmount;
        $isFullyPaid = $newPaidAmount >= $claim->payable_amount;

        $claim->update([
            'paid_amount' => $newPaidAmount,
            'payment_method' => $paymentData['payment_method'],
            'payment_reference' => $paymentData['payment_reference'] ?? null,
            'payment_date' => $paymentData['payment_date'] ?? now(),
            'paid_to' => $paymentData['paid_to'] ?? MedicalConstants::SUBMISSION_TYPE_MEMBER,
            'bank_name' => $paymentData['bank_name'] ?? null,
            'account_number' => $paymentData['account_number'] ?? null,
            'status' => $isFullyPaid ? MedicalConstants::CLAIM_STATUS_PAID : $claim->status,
            'completed_at' => $isFullyPaid ? now() : null,
            'tat_days' => $isFullyPaid ? $claim->calculateTat() : null,
        ]);

        $claim->addNote(
            MedicalConstants::CLAIM_NOTE_STATUS_CHANGE,
            "Payment recorded: {$claim->currency} " . number_format($paymentAmount, 2) . " via {$paymentData['payment_method']}",
            $processedBy,
            [
                'old_status' => $oldStatus,
                'new_status' => $isFullyPaid ? MedicalConstants::CLAIM_STATUS_PAID : $claim->status,
                'is_system' => true,
            ]
        );

        return $claim->fresh();
    }

    /**
     * Close a claim.
     */
    public function closeClaim(Claim $claim, string $closedBy, ?string $notes = null): Claim
    {
        if (!in_array($claim->status, [
            MedicalConstants::CLAIM_STATUS_PAID,
            MedicalConstants::CLAIM_STATUS_REJECTED,
        ])) {
            throw new Exception('Only paid or rejected claims can be closed');
        }

        $oldStatus = $claim->status;

        $claim->update([
            'status' => MedicalConstants::CLAIM_STATUS_CLOSED,
            'completed_at' => $claim->completed_at ?? now(),
            'tat_days' => $claim->tat_days ?? $claim->calculateTat(),
        ]);

        $claim->addNote(
            MedicalConstants::CLAIM_NOTE_STATUS_CHANGE,
            'Claim closed' . ($notes ? ": {$notes}" : ''),
            $closedBy,
            ['old_status' => $oldStatus, 'new_status' => MedicalConstants::CLAIM_STATUS_CLOSED, 'is_system' => true]
        );

        return $claim->fresh();
    }

    // =========================================================================
    // QUERY HELPERS
    // =========================================================================

    /**
     * Get claims for a policy.
     */
    public function getClaimsForPolicy(Policy $policy, array $filters = []): Builder
    {
        $query = Claim::forPolicy($policy->id);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['claim_type'])) {
            $query->ofType($filters['claim_type']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('service_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('service_date', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Get claims for a member.
     */
    public function getClaimsForMember(Member $member, array $filters = []): Builder
    {
        $query = Claim::forMember($member->id);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['claim_type'])) {
            $query->ofType($filters['claim_type']);
        }

        return $query->orderBy('created_at', 'desc');
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    /**
     * Get claim statistics.
     */
    public function getStatistics(array $filters = []): array
    {
        $query = Claim::query();

        if (!empty($filters['policy_id'])) {
            $query->forPolicy($filters['policy_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        $stats = $query->selectRaw("
            COUNT(*) as total,
            COUNT(CASE WHEN status = ? THEN 1 END) as submitted,
            COUNT(CASE WHEN status = ? THEN 1 END) as in_review,
            COUNT(CASE WHEN status = ? THEN 1 END) as pending_approval,
            COUNT(CASE WHEN status IN (?, ?) THEN 1 END) as approved,
            COUNT(CASE WHEN status = ? THEN 1 END) as rejected,
            COUNT(CASE WHEN status = ? THEN 1 END) as paid,
            COUNT(CASE WHEN status = ? THEN 1 END) as closed,
            SUM(claimed_amount) as total_claimed,
            SUM(approved_amount) as total_approved,
            SUM(paid_amount) as total_paid,
            AVG(tat_days) as avg_tat_days
        ", [
            MedicalConstants::CLAIM_STATUS_SUBMITTED,
            MedicalConstants::CLAIM_STATUS_IN_REVIEW,
            MedicalConstants::CLAIM_STATUS_PENDING_APPROVAL,
            MedicalConstants::CLAIM_STATUS_APPROVED,
            MedicalConstants::CLAIM_STATUS_PARTIALLY_APPROVED,
            MedicalConstants::CLAIM_STATUS_REJECTED,
            MedicalConstants::CLAIM_STATUS_PAID,
            MedicalConstants::CLAIM_STATUS_CLOSED,
        ])->first();

        // Claims by type
        $byType = Claim::when(!empty($filters['policy_id']), fn($q) => $q->forPolicy($filters['policy_id']))
            ->selectRaw('claim_type, COUNT(*) as count, SUM(claimed_amount) as total_claimed')
            ->groupBy('claim_type')
            ->get()
            ->keyBy('claim_type');

        return [
            'summary' => $stats->toArray(),
            'by_type' => $byType,
            'approval_rate' => $stats->total > 0
                ? round(($stats->approved / $stats->total) * 100, 1)
                : 0,
        ];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Determine claim status based on line approvals/rejections.
     */
    protected function determineClaimStatus(bool $hasApproved, bool $hasRejected): string
    {
        if ($hasApproved && $hasRejected) {
            return MedicalConstants::CLAIM_STATUS_PARTIALLY_APPROVED;
        } elseif ($hasApproved) {
            return MedicalConstants::CLAIM_STATUS_APPROVED;
        } else {
            return MedicalConstants::CLAIM_STATUS_REJECTED;
        }
    }
}
