<?php

namespace Modules\Medical\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a member's benefit balance changes
 * Useful for real-time balance updates in member portals
 */
class BenefitBalanceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $memberId,
        public string $benefitId,
        public string $benefitName,
        public float $previousBalance,
        public float $newBalance,
        public float $changeAmount,
        public string $changeType, // 'used', 'reserved', 'released', 'adjusted'
        public ?string $claimId = null,
        public ?string $preauthId = null,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("member.{$this->memberId}"),
            new PrivateChannel("member.{$this->memberId}.benefits"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'benefit.balance.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'member_id' => $this->memberId,
            'benefit_id' => $this->benefitId,
            'benefit_name' => $this->benefitName,
            'previous_balance' => $this->previousBalance,
            'new_balance' => $this->newBalance,
            'change_amount' => $this->changeAmount,
            'change_type' => $this->changeType,
            'claim_id' => $this->claimId,
            'preauth_id' => $this->preauthId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
