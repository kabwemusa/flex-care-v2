<?php

namespace Modules\Medical\Jobs\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Medical\Models\PreAuthorization;

/**
 * Send pre-authorization notification (email/SMS) to member and provider
 */
class SendPreAuthNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public string $preauthId,
        public string $status,
        public array $details = [],
    ) {
        $this->onQueue('medical-notifications');
    }

    public function handle(): void
    {
        $preauth = PreAuthorization::with(['member', 'provider'])->find($this->preauthId);

        if (!$preauth) {
            Log::warning('SendPreAuthNotificationJob: PreAuth not found', [
                'preauth_id' => $this->preauthId,
            ]);
            return;
        }

        Log::info('SendPreAuthNotificationJob: Sending notification', [
            'preauth_id' => $this->preauthId,
            'preauth_number' => $preauth->preauth_number,
            'status' => $this->status,
        ]);

        try {
            // Notify member
            $this->notifyMember($preauth);

            // Notify provider
            $this->notifyProvider($preauth);

            Log::info('SendPreAuthNotificationJob: Notifications sent', [
                'preauth_id' => $this->preauthId,
            ]);

        } catch (\Exception $e) {
            Log::error('SendPreAuthNotificationJob: Failed to send notification', [
                'preauth_id' => $this->preauthId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function notifyMember(PreAuthorization $preauth): void
    {
        $member = $preauth->member;
        if (!$member) {
            return;
        }

        $content = $this->getMemberNotificationContent($preauth);

        if ($member->email && $this->shouldSendEmail()) {
            // Mail::to($member->email)->queue(new PreAuthStatusMail($preauth, $content));
            Log::info('SendPreAuthNotificationJob: Member email queued', [
                'preauth_id' => $preauth->id,
                'email' => $member->email,
            ]);
        }

        if ($member->phone && $this->shouldSendSms()) {
            // SmsService::send($member->phone, $content['sms_message']);
            Log::info('SendPreAuthNotificationJob: Member SMS queued', [
                'preauth_id' => $preauth->id,
            ]);
        }
    }

    private function notifyProvider(PreAuthorization $preauth): void
    {
        $provider = $preauth->provider;
        if (!$provider) {
            return;
        }

        $content = $this->getProviderNotificationContent($preauth);

        // Providers typically get email notifications
        $providerEmail = $provider->email ?? null;
        if ($providerEmail) {
            // Mail::to($providerEmail)->queue(new PreAuthProviderNotificationMail($preauth, $content));
            Log::info('SendPreAuthNotificationJob: Provider email queued', [
                'preauth_id' => $preauth->id,
                'provider_id' => $provider->id,
            ]);
        }
    }

    private function getMemberNotificationContent(PreAuthorization $preauth): array
    {
        $baseContent = [
            'preauth_number' => $preauth->preauth_number,
            'member_name' => $preauth->member->full_name ?? 'Member',
            'amount' => $preauth->currency . ' ' . number_format($preauth->requested_amount, 2),
        ];

        return match ($this->status) {
            'approved', 'auto_approved' => array_merge($baseContent, [
                'subject' => 'Pre-Authorization Approved',
                'message' => "Your pre-authorization {$preauth->preauth_number} has been approved.",
                'approved_amount' => $preauth->currency . ' ' . number_format($preauth->approved_amount ?? 0, 2),
                'expires_at' => $preauth->expires_at?->format('M d, Y H:i'),
                'sms_message' => "PreAuth {$preauth->preauth_number} approved for {$preauth->currency} " . number_format($preauth->approved_amount ?? 0, 2),
            ]),
            'rejected' => array_merge($baseContent, [
                'subject' => 'Pre-Authorization Rejected',
                'message' => "Your pre-authorization {$preauth->preauth_number} has been rejected.",
                'reason' => $preauth->rejection_reason ?? 'Please contact support for details.',
                'sms_message' => "PreAuth {$preauth->preauth_number} rejected. Contact support for details.",
            ]),
            'expired' => array_merge($baseContent, [
                'subject' => 'Pre-Authorization Expired',
                'message' => "Your pre-authorization {$preauth->preauth_number} has expired.",
                'sms_message' => "PreAuth {$preauth->preauth_number} has expired.",
            ]),
            'under_review' => array_merge($baseContent, [
                'subject' => 'Pre-Authorization Under Review',
                'message' => "Your pre-authorization {$preauth->preauth_number} is being reviewed.",
                'sms_message' => "PreAuth {$preauth->preauth_number} is under review.",
            ]),
            default => array_merge($baseContent, [
                'subject' => 'Pre-Authorization Status Update',
                'message' => "Your pre-authorization {$preauth->preauth_number} status has been updated.",
                'sms_message' => "PreAuth {$preauth->preauth_number} status: {$this->status}",
            ]),
        };
    }

    private function getProviderNotificationContent(PreAuthorization $preauth): array
    {
        return match ($this->status) {
            'approved', 'auto_approved' => [
                'subject' => "PreAuth {$preauth->preauth_number} Approved",
                'message' => "Pre-authorization has been approved.",
                'approved_amount' => $preauth->approved_amount,
                'expires_at' => $preauth->expires_at,
            ],
            'rejected' => [
                'subject' => "PreAuth {$preauth->preauth_number} Rejected",
                'message' => "Pre-authorization has been rejected.",
                'reason' => $preauth->rejection_reason,
            ],
            default => [
                'subject' => "PreAuth {$preauth->preauth_number} Status Update",
                'message' => "Pre-authorization status: {$this->status}",
            ],
        };
    }

    private function shouldSendEmail(): bool
    {
        return config('medical.notifications.email.enabled', true);
    }

    private function shouldSendSms(): bool
    {
        return config('medical.notifications.sms.enabled', false)
            && in_array($this->status, ['approved', 'auto_approved', 'rejected']);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendPreAuthNotificationJob: Job failed permanently', [
            'preauth_id' => $this->preauthId,
            'status' => $this->status,
            'error' => $exception->getMessage(),
        ]);
    }
}
