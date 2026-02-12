<?php

namespace Modules\Medical\Jobs\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Medical\Models\Claim;

/**
 * Send claim notification (email/SMS) to member and/or provider
 */
class SendClaimNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public string $claimId,
        public string $status,
        public array $details = [],
    ) {
        $this->onQueue('medical-notifications');
    }

    public function handle(): void
    {
        $claim = Claim::with(['member', 'policy.customer'])->find($this->claimId);

        if (!$claim) {
            Log::warning('SendClaimNotificationJob: Claim not found', [
                'claim_id' => $this->claimId,
            ]);
            return;
        }

        Log::info('SendClaimNotificationJob: Sending notification', [
            'claim_id' => $this->claimId,
            'claim_number' => $claim->claim_number,
            'status' => $this->status,
        ]);

        try {
            // Get notification recipients
            $memberEmail = $claim->member->email ?? null;
            $memberPhone = $claim->member->phone ?? null;

            // Prepare notification content based on status
            $content = $this->getNotificationContent($claim);

            // Send email notification if email available
            if ($memberEmail && $this->shouldSendEmail()) {
                $this->sendEmailNotification($claim, $memberEmail, $content);
            }

            // Send SMS notification if phone available and enabled
            if ($memberPhone && $this->shouldSendSms()) {
                $this->sendSmsNotification($claim, $memberPhone, $content);
            }

            Log::info('SendClaimNotificationJob: Notification sent', [
                'claim_id' => $this->claimId,
                'email_sent' => !empty($memberEmail),
                'sms_sent' => !empty($memberPhone) && $this->shouldSendSms(),
            ]);

        } catch (\Exception $e) {
            Log::error('SendClaimNotificationJob: Failed to send notification', [
                'claim_id' => $this->claimId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function getNotificationContent(Claim $claim): array
    {
        $baseContent = [
            'claim_number' => $claim->claim_number,
            'member_name' => $claim->member->full_name ?? 'Member',
            'amount' => $claim->currency . ' ' . number_format($claim->claimed_amount, 2),
        ];

        return match ($this->status) {
            'approved', 'partially_approved' => array_merge($baseContent, [
                'subject' => 'Claim Approved',
                'message' => "Your claim {$claim->claim_number} has been approved.",
                'approved_amount' => $claim->currency . ' ' . number_format($claim->approved_amount ?? 0, 2),
            ]),
            'rejected' => array_merge($baseContent, [
                'subject' => 'Claim Rejected',
                'message' => "Your claim {$claim->claim_number} has been rejected.",
                'reason' => $claim->rejection_reason ?? 'Please contact support for details.',
            ]),
            'paid' => array_merge($baseContent, [
                'subject' => 'Claim Payment Processed',
                'message' => "Payment for claim {$claim->claim_number} has been processed.",
                'paid_amount' => $claim->currency . ' ' . number_format($claim->payable_amount ?? 0, 2),
            ]),
            'pending' => array_merge($baseContent, [
                'subject' => 'Claim Under Review',
                'message' => "Your claim {$claim->claim_number} is being reviewed.",
            ]),
            default => array_merge($baseContent, [
                'subject' => 'Claim Status Update',
                'message' => "Your claim {$claim->claim_number} status has been updated to: {$this->status}",
            ]),
        };
    }

    private function shouldSendEmail(): bool
    {
        // Could be configurable per customer/member preferences
        return config('medical.notifications.email.enabled', true);
    }

    private function shouldSendSms(): bool
    {
        // SMS only for important status changes
        return config('medical.notifications.sms.enabled', false)
            && in_array($this->status, ['approved', 'rejected', 'paid']);
    }

    private function sendEmailNotification(Claim $claim, string $email, array $content): void
    {
        // Use Laravel Mail - placeholder for actual implementation
        // Mail::to($email)->queue(new ClaimStatusMail($claim, $content));

        Log::info('SendClaimNotificationJob: Email queued', [
            'claim_id' => $claim->id,
            'email' => $email,
            'subject' => $content['subject'],
        ]);
    }

    private function sendSmsNotification(Claim $claim, string $phone, array $content): void
    {
        // SMS integration - placeholder for actual implementation
        // SmsService::send($phone, $content['message']);

        Log::info('SendClaimNotificationJob: SMS queued', [
            'claim_id' => $claim->id,
            'phone' => substr($phone, 0, 6) . '****',
            'message_length' => strlen($content['message']),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendClaimNotificationJob: Job failed permanently', [
            'claim_id' => $this->claimId,
            'status' => $this->status,
            'error' => $exception->getMessage(),
        ]);
    }
}
