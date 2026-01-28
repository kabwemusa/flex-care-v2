<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ApprovalRequiredNotification extends BaseApprovalNotification
{
    protected function getTitle(): string
    {
        return "Approval Required: {$this->getEntityReference()}";
    }

    protected function getMessage(): string
    {
        $stepName = $this->request->currentStep?->name ?? 'Review';
        return "A new item requires your approval at the '{$stepName}' step.";
    }

    protected function getActionText(): string
    {
        return 'Review & Approve';
    }

    protected function getNotificationType(): string
    {
        return 'approval_required';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $stepName = $this->request->currentStep?->name ?? 'Review';

        return (new MailMessage)
            ->subject($this->getTitle())
            ->greeting("Hello {$notifiable->username},")
            ->line('You have a new approval request waiting for your review.')
            ->line("**Reference:** {$this->getEntityReference()}")
            ->line("**Workflow:** {$this->request->workflow->name}")
            ->line("**Current Step:** {$stepName}")
            ->line("**Submitted by:** {$this->request->initiator->username}")
            ->action($this->getActionText(), $this->getActionUrl())
            ->line('Please review and take action at your earliest convenience.');
    }
}
