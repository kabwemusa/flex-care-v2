<?php

namespace Modules\Medical\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a new fraud alert is created
 * Only broadcast to internal staff channels
 */
class FraudAlertCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $alertId,
        public string $alertNumber,
        public string $entityType,
        public string $entityId,
        public int $fraudScore,
        public string $severity,
        public array $flags = [],
        public ?string $memberId = null,
        public ?string $providerId = null,
        public ?float $flaggedAmount = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     * Fraud alerts only go to internal staff channels
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('fraud-alerts'),
            new PrivateChannel("fraud-alerts.{$this->severity}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'fraud.alert.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'alert_id' => $this->alertId,
            'alert_number' => $this->alertNumber,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'fraud_score' => $this->fraudScore,
            'severity' => $this->severity,
            'flags' => $this->flags,
            'member_id' => $this->memberId,
            'provider_id' => $this->providerId,
            'flagged_amount' => $this->flaggedAmount,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Determine if this event should broadcast.
     */
    public function broadcastWhen(): bool
    {
        // Only broadcast high severity alerts in real-time
        return in_array($this->severity, ['high', 'critical']);
    }
}
