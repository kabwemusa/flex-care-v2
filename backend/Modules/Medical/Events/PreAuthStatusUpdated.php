<?php

namespace Modules\Medical\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PreAuthStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $preauthId,
        public string $preauthNumber,
        public string $status,
        public array $details = [],
        public ?string $memberId = null,
        public ?string $providerId = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("preauth.{$this->preauthId}"),
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
        return 'preauth.status.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'preauth_id' => $this->preauthId,
            'preauth_number' => $this->preauthNumber,
            'status' => $this->status,
            'details' => $this->details,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
