<?php

namespace App\Notifications;

use App\Models\ApprovalRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class BaseApprovalNotification extends Notification
{
    protected ApprovalRequest $request;

    public function __construct(ApprovalRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the title for this notification
     */
    abstract protected function getTitle(): string;

    /**
     * Get the message for this notification
     */
    abstract protected function getMessage(): string;

    /**
     * Get the action text for email
     */
    protected function getActionText(): string
    {
        return 'View Details';
    }

    /**
     * Get the action URL
     */
    protected function getActionUrl(): string
    {
        // This should be configured based on your frontend URL
        $baseUrl = config('app.frontend_url', config('app.url'));
        return "{$baseUrl}/approvals/{$this->request->id}";
    }

    /**
     * Get the entity reference (e.g., "Application #APP-2025-000123")
     */
    protected function getEntityReference(): string
    {
        $entity = $this->request->entity;

        // Try to get a display reference from the entity
        if ($entity) {
            // Check for common reference fields
            if (isset($entity->application_number)) {
                return "Application #{$entity->application_number}";
            }
            if (isset($entity->claim_number)) {
                return "Claim #{$entity->claim_number}";
            }
            if (isset($entity->invoice_number)) {
                return "Invoice #{$entity->invoice_number}";
            }
            if (isset($entity->policy_number)) {
                return "Policy #{$entity->policy_number}";
            }
            if (isset($entity->reference_number)) {
                return "#{$entity->reference_number}";
            }
        }

        // Fallback to entity type and ID
        $entityType = ucfirst(str_replace('_', ' ', $this->request->entity_type));
        return "{$entityType} #{$this->request->entity_id}";
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->getTitle())
            ->greeting("Hello {$notifiable->username},")
            ->line($this->getMessage())
            ->line("**Reference:** {$this->getEntityReference()}")
            ->line("**Workflow:** {$this->request->workflow->name}")
            ->action($this->getActionText(), $this->getActionUrl())
            ->line('Thank you for using our platform.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->getTitle(),
            'message' => $this->getMessage(),
            'type' => $this->getNotificationType(),
            'request_id' => $this->request->id,
            'workflow_id' => $this->request->workflow_id,
            'workflow_name' => $this->request->workflow->name,
            'entity_type' => $this->request->entity_type,
            'entity_id' => $this->request->entity_id,
            'entity_reference' => $this->getEntityReference(),
            'status' => $this->request->status,
            'current_step' => $this->request->currentStep?->name,
            'action_url' => $this->getActionUrl(),
        ];
    }

    /**
     * Get the notification type identifier
     */
    abstract protected function getNotificationType(): string;
}
