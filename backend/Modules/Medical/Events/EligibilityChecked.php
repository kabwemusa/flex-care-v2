<?php

namespace Modules\Medical\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when an eligibility check is performed
 * Notifies providers of eligibility results in real-time
 */
class EligibilityChecked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $memberId,
        public string $memberName,
        public string $cardNumber,
        public bool $isEligible,
        public string $status,
        public ?string $providerId = null,
        public array $benefits = [],
        public ?string $ineligibilityReason = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("member.{$this->memberId}"),
        ];

        if ($this->providerId) {
            $channels[] = new PrivateChannel("provider.{$this->providerId}");
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'eligibility.checked';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'member_id' => $this->memberId,
            'member_name' => $this->memberName,
            'card_number' => $this->cardNumber,
            'is_eligible' => $this->isEligible,
            'status' => $this->status,
            'benefits_count' => count($this->benefits),
            'ineligibility_reason' => $this->ineligibilityReason,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
