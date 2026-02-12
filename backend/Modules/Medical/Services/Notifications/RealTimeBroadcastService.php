<?php

namespace Modules\Medical\Services\Notifications;

use Illuminate\Support\Facades\Log;
use Modules\Medical\Models\Claim;
use Modules\Medical\Models\PreAuthorization;
use Modules\Medical\Models\FraudAlert;
use Modules\Medical\Events\ClaimStatusUpdated;
use Modules\Medical\Events\PreAuthStatusUpdated;
use Modules\Medical\Events\FraudAlertCreated;
use Modules\Medical\Events\BenefitBalanceUpdated;
use Modules\Medical\Events\EligibilityChecked;

/**
 * Real-Time Broadcast Service
 *
 * Provides a centralized way to broadcast real-time updates
 * via WebSocket to connected clients.
 */
class RealTimeBroadcastService
{
    /**
     * Broadcast claim status update
     */
    public function broadcastClaimUpdate(
        Claim $claim,
        string $previousStatus,
        array $details = []
    ): void {
        try {
            event(new ClaimStatusUpdated(
                claimId: $claim->id,
                claimNumber: $claim->claim_number,
                status: $claim->status,
                previousStatus: $previousStatus,
                details: $details,
                memberId: $claim->member_id,
                providerId: $claim->provider_id_fk,
            ));

            Log::debug('RealTimeBroadcastService: Claim update broadcast', [
                'claim_id' => $claim->id,
                'status' => $claim->status,
            ]);
        } catch (\Exception $e) {
            Log::warning('RealTimeBroadcastService: Failed to broadcast claim update', [
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast pre-authorization status update
     */
    public function broadcastPreAuthUpdate(
        PreAuthorization $preauth,
        array $details = []
    ): void {
        try {
            event(new PreAuthStatusUpdated(
                preauthId: $preauth->id,
                preauthNumber: $preauth->preauth_number,
                status: $preauth->status,
                details: $details,
                memberId: $preauth->member_id,
                providerId: $preauth->provider_id,
            ));

            Log::debug('RealTimeBroadcastService: PreAuth update broadcast', [
                'preauth_id' => $preauth->id,
                'status' => $preauth->status,
            ]);
        } catch (\Exception $e) {
            Log::warning('RealTimeBroadcastService: Failed to broadcast preauth update', [
                'preauth_id' => $preauth->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast fraud alert creation
     */
    public function broadcastFraudAlert(FraudAlert $alert): void
    {
        try {
            event(new FraudAlertCreated(
                alertId: $alert->id,
                alertNumber: $alert->alert_number,
                entityType: $alert->entity_type,
                entityId: $alert->entity_id,
                fraudScore: $alert->fraud_score,
                severity: $alert->severity,
                flags: $alert->triggered_rules ?? [],
                memberId: $alert->member_id,
                providerId: $alert->provider_id,
                flaggedAmount: (float) $alert->flagged_amount,
            ));

            Log::debug('RealTimeBroadcastService: Fraud alert broadcast', [
                'alert_id' => $alert->id,
                'severity' => $alert->severity,
            ]);
        } catch (\Exception $e) {
            Log::warning('RealTimeBroadcastService: Failed to broadcast fraud alert', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast benefit balance update
     */
    public function broadcastBenefitUpdate(
        string $memberId,
        string $benefitId,
        string $benefitName,
        float $previousBalance,
        float $newBalance,
        string $changeType,
        ?string $claimId = null,
        ?string $preauthId = null
    ): void {
        try {
            event(new BenefitBalanceUpdated(
                memberId: $memberId,
                benefitId: $benefitId,
                benefitName: $benefitName,
                previousBalance: $previousBalance,
                newBalance: $newBalance,
                changeAmount: abs($newBalance - $previousBalance),
                changeType: $changeType,
                claimId: $claimId,
                preauthId: $preauthId,
            ));

            Log::debug('RealTimeBroadcastService: Benefit balance broadcast', [
                'member_id' => $memberId,
                'benefit_id' => $benefitId,
                'change_type' => $changeType,
            ]);
        } catch (\Exception $e) {
            Log::warning('RealTimeBroadcastService: Failed to broadcast benefit update', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast eligibility check result
     */
    public function broadcastEligibilityCheck(
        string $memberId,
        string $memberName,
        string $cardNumber,
        bool $isEligible,
        string $status,
        ?string $providerId = null,
        array $benefits = [],
        ?string $ineligibilityReason = null
    ): void {
        try {
            event(new EligibilityChecked(
                memberId: $memberId,
                memberName: $memberName,
                cardNumber: $cardNumber,
                isEligible: $isEligible,
                status: $status,
                providerId: $providerId,
                benefits: $benefits,
                ineligibilityReason: $ineligibilityReason,
            ));

            Log::debug('RealTimeBroadcastService: Eligibility check broadcast', [
                'member_id' => $memberId,
                'is_eligible' => $isEligible,
            ]);
        } catch (\Exception $e) {
            Log::warning('RealTimeBroadcastService: Failed to broadcast eligibility check', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast queue statistics update (for dashboard)
     */
    public function broadcastQueueStats(array $stats): void
    {
        try {
            // This would broadcast to the claims-queue channel
            // broadcast(new QueueStatsUpdated($stats))->toOthers();

            Log::debug('RealTimeBroadcastService: Queue stats broadcast', [
                'pending_claims' => $stats['pending_claims'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::warning('RealTimeBroadcastService: Failed to broadcast queue stats', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
