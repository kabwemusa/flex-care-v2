<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ApprovalProgressNotification extends BaseApprovalNotification
{
    protected function getTitle(): string
    {
        return "Progress Update: {$this->getEntityReference()}";
    }

    protected function getMessage(): string
    {
        $currentStep = $this->request->getCurrentStepNumber();
        $totalSteps = $this->request->getTotalSteps();
        $nextStepName = $this->request->currentStep?->name ?? 'Final Review';

        return "Your submission has progressed to step {$currentStep} of {$totalSteps}: {$nextStepName}";
    }

    protected function getActionText(): string
    {
        return 'View Progress';
    }

    protected function getNotificationType(): string
    {
        return 'approval_progress';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $currentStep = $this->request->getCurrentStepNumber();
        $totalSteps = $this->request->getTotalSteps();
        $progress = $this->request->getProgressPercentage();
        $nextStepName = $this->request->currentStep?->name ?? 'Final Review';
        $nextGroupName = $this->request->currentStep?->group?->name ?? 'Reviewers';

        return (new MailMessage)
            ->subject($this->getTitle())
            ->greeting("Hello {$notifiable->username},")
            ->line('Your submission has made progress in the approval workflow.')
            ->line("**Reference:** {$this->getEntityReference()}")
            ->line("**Workflow:** {$this->request->workflow->name}")
            ->line("**Progress:** Step {$currentStep} of {$totalSteps} ({$progress}%)")
            ->line("**Current Step:** {$nextStepName}")
            ->line("**Pending with:** {$nextGroupName}")
            ->action($this->getActionText(), $this->getActionUrl())
            ->line('You will be notified when there are further updates.');
    }
}
