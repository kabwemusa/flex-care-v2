<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ApprovalRejectedNotification extends BaseApprovalNotification
{
    protected function getTitle(): string
    {
        return "Rejected: {$this->getEntityReference()}";
    }

    protected function getMessage(): string
    {
        $reason = $this->request->final_remarks ?? 'No reason provided';
        return "Your submission has been rejected. Reason: {$reason}";
    }

    protected function getActionText(): string
    {
        return 'View Details';
    }

    protected function getNotificationType(): string
    {
        return 'approval_rejected';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reason = $this->request->final_remarks ?? 'No reason provided';
        $lastAction = $this->request->getLastAction();
        $rejectedBy = $lastAction?->actionedBy?->username ?? 'Unknown';

        return (new MailMessage)
            ->subject($this->getTitle())
            ->greeting("Hello {$notifiable->username},")
            ->line('Unfortunately, your submission has been rejected.')
            ->line("**Reference:** {$this->getEntityReference()}")
            ->line("**Workflow:** {$this->request->workflow->name}")
            ->line("**Rejected by:** {$rejectedBy}")
            ->line("**Reason:** {$reason}")
            ->action($this->getActionText(), $this->getActionUrl())
            ->line('If you have questions, please contact the reviewer.');
    }
}
