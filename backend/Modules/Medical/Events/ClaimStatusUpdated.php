<?php

namespace Modules\Medical\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClaimStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $claimId,
        public string $claimNumber,
        public string $status,
        public string $previousStatus,
        public array $details = [],
        public ?string $memberId = null,
        public ?string $providerId = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("claim.{$this->claimId}"),
        ];

        if ($this->memberId) {
            $channels[] = new PrivateChannel("member.{$this->memberId}");
        }

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
        return 'claim.status.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'claim_id' => $this->claimId,
            'claim_number' => $this->claimNumber,
            'status' => $this->status,
            'previous_status' => $this->previousStatus,
            'details' => $this->details,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Determine if this event should broadcast.
     */
    public function broadcastWhen(): bool
    {
        // Always broadcast status updates
        return true;
    }
}
