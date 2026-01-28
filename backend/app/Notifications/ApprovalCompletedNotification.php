<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ApprovalCompletedNotification extends BaseApprovalNotification
{
    protected function getTitle(): string
    {
        return "Approved: {$this->getEntityReference()}";
    }

    protected function getMessage(): string
    {
        return 'Your submission has been fully approved and the workflow is now complete.';
    }

    protected function getActionText(): string
    {
        return 'View Details';
    }

    protected function getNotificationType(): string
    {
        return 'approval_completed';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->getTitle())
            ->greeting("Hello {$notifiable->username},")
            ->line('Great news! Your submission has been fully approved.')
            ->line("**Reference:** {$this->getEntityReference()}")
            ->line("**Workflow:** {$this->request->workflow->name}")
            ->line("**Completed at:** {$this->request->completed_at->format('M d, Y H:i')}")
            ->action($this->getActionText(), $this->getActionUrl())
            ->line('Thank you for using our platform.');
    }
}
