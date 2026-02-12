<?php

namespace Modules\Medical\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Modules\Medical\Events\ClaimStatusUpdated;
use Modules\Medical\Events\PreAuthStatusUpdated;
use Modules\Medical\Events\FraudAlertCreated;
use Modules\Medical\Events\BenefitBalanceUpdated;
use Modules\Medical\Jobs\Notifications\SendClaimNotificationJob;
use Modules\Medical\Jobs\Notifications\SendPreAuthNotificationJob;

/**
 * Medical Event Subscriber
 *
 * Listens to medical events and triggers appropriate notifications
 * (email, SMS, push notifications) in addition to WebSocket broadcasts.
 */
class MedicalEventSubscriber
{
    /**
     * Handle claim status updates
     */
    public function handleClaimStatusUpdated(ClaimStatusUpdated $event): void
    {
        Log::info('MedicalEventSubscriber: Claim status updated', [
            'claim_id' => $event->claimId,
            'claim_number' => $event->claimNumber,
            'status' => $event->status,
            'previous_status' => $event->previousStatus,
        ]);

        // Dispatch notification job for email/SMS based on status
        if ($this->shouldNotifyClaimStatus($event->status)) {
            SendClaimNotificationJob::dispatch(
                $event->claimId,
                $event->status,
                $event->details
            )->onQueue('medical-notifications');
        }
    }

    /**
     * Handle pre-auth status updates
     */
    public function handlePreAuthStatusUpdated(PreAuthStatusUpdated $event): void
    {
        Log::info('MedicalEventSubscriber: PreAuth status updated', [
            'preauth_id' => $event->preauthId,
            'preauth_number' => $event->preauthNumber,
            'status' => $event->status,
        ]);

        // Dispatch notification job for email/SMS based on status
        if ($this->shouldNotifyPreAuthStatus($event->status)) {
            SendPreAuthNotificationJob::dispatch(
                $event->preauthId,
                $event->status,
                $event->details
            )->onQueue('medical-notifications');
        }
    }

    /**
     * Handle fraud alert creation
     */
    public function handleFraudAlertCreated(FraudAlertCreated $event): void
    {
        Log::warning('MedicalEventSubscriber: Fraud alert created', [
            'alert_id' => $event->alertId,
            'alert_number' => $event->alertNumber,
            'severity' => $event->severity,
            'fraud_score' => $event->fraudScore,
        ]);

        // For critical alerts, could trigger immediate notification to fraud team
        if ($event->severity === 'critical') {
            // Could dispatch urgent notification job here
            Log::critical('MedicalEventSubscriber: CRITICAL fraud alert requires immediate attention', [
                'alert_number' => $event->alertNumber,
            ]);
        }
    }

    /**
     * Handle benefit balance updates
     */
    public function handleBenefitBalanceUpdated(BenefitBalanceUpdated $event): void
    {
        Log::debug('MedicalEventSubscriber: Benefit balance updated', [
            'member_id' => $event->memberId,
            'benefit_id' => $event->benefitId,
            'change_type' => $event->changeType,
            'change_amount' => $event->changeAmount,
        ]);

        // Could trigger low balance notifications
        if ($event->newBalance < ($event->previousBalance * 0.1)) {
            // Benefit is below 10% - could notify member
            Log::info('MedicalEventSubscriber: Low benefit balance detected', [
                'member_id' => $event->memberId,
                'benefit_name' => $event->benefitName,
                'remaining' => $event->newBalance,
            ]);
        }
    }

    /**
     * Determine if claim status change should trigger notification
     */
    private function shouldNotifyClaimStatus(string $status): bool
    {
        return in_array($status, [
            'approved',
            'partially_approved',
            'rejected',
            'paid',
            'pending', // When flagged for review
        ]);
    }

    /**
     * Determine if pre-auth status change should trigger notification
     */
    private function shouldNotifyPreAuthStatus(string $status): bool
    {
        return in_array($status, [
            'approved',
            'auto_approved',
            'rejected',
            'expired',
            'under_review',
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            ClaimStatusUpdated::class => 'handleClaimStatusUpdated',
            PreAuthStatusUpdated::class => 'handlePreAuthStatusUpdated',
            FraudAlertCreated::class => 'handleFraudAlertCreated',
            BenefitBalanceUpdated::class => 'handleBenefitBalanceUpdated',
        ];
    }
}
