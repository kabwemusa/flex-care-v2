<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ApprovalReturnedNotification extends BaseApprovalNotification
{
    protected function getTitle(): string
    {
        return "Amendment Required: {$this->getEntityReference()}";
    }

    protected function getMessage(): string
    {
        $reason = $this->request->final_remarks ?? 'Please review and make necessary changes';
        return "Your submission has been returned for amendment. Reason: {$reason}";
    }

    protected function getActionText(): string
    {
        return 'Review & Amend';
    }

    protected function getNotificationType(): string
    {
        return 'approval_returned';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reason = $this->request->final_remarks ?? 'Please review and make necessary changes';
        $lastAction = $this->request->getLastAction();
        $returnedBy = $lastAction?->actionedBy?->username ?? 'Unknown';

        return (new MailMessage)
            ->subject($this->getTitle())
            ->greeting("Hello {$notifiable->username},")
            ->line('Your submission has been returned for amendment.')
            ->line("**Reference:** {$this->getEntityReference()}")
            ->line("**Workflow:** {$this->request->workflow->name}")
            ->line("**Returned by:** {$returnedBy}")
            ->line("**Reason:** {$reason}")
            ->action($this->getActionText(), $this->getActionUrl())
            ->line('Please make the necessary changes and resubmit for approval.');
    }
}
