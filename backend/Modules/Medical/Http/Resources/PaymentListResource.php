<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'status' => $this->status,
            'status_label' => $this->status_label,

            // Policy & Group summary
            'policy_id' => $this->policy_id,
            'policy' => $this->whenLoaded('policy', fn() => [
                'id' => $this->policy->id,
                'policy_number' => $this->policy->policy_number,
                'holder_name' => $this->policy->holder_name,
            ]),
            'group_id' => $this->group_id,
            'group' => $this->whenLoaded('group', fn() => [
                'id' => $this->group->id,
                'code' => $this->group->code,
                'name' => $this->group->name,
            ]),

            // Payment Details
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'currency' => $this->currency,
            'amount' => (float) $this->amount,
            'allocated_amount' => (float) $this->allocated_amount,
            'unallocated_amount' => (float) $this->unallocated_amount,
            'allocation_progress' => $this->allocation_progress,

            // Payment Method
            'payment_method' => $this->payment_method,
            'payment_method_label' => $this->payment_method_label,
            'payment_reference' => $this->payment_reference,

            // Payer
            'payer_name' => $this->payer_name,

            // Staff
            'received_by' => $this->whenLoaded('receivedByUser',
                fn() => $this->receivedByUser?->username ?? $this->receivedByUser?->email ?? null,
                $this->received_by
            ),

            // State
            'is_valid' => $this->is_valid,
            'is_fully_allocated' => $this->is_fully_allocated,
            'is_reconciled' => $this->is_reconciled,
            'can_be_allocated' => $this->can_be_allocated,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
