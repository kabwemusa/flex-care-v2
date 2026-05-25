<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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

            // Policy & Group
            'policy_id' => $this->policy_id,
            'policy' => $this->whenLoaded('policy', fn() => [
                'id' => $this->policy->id,
                'policy_number' => $this->policy->policy_number,
                'holder_name' => $this->policy->holder_name,
                'status' => $this->policy->status,
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
            'bank_name' => $this->bank_name,
            'cheque_number' => $this->cheque_number,
            'transaction_id' => $this->transaction_id,

            // Payer
            'payer_name' => $this->payer_name,
            'payer_reference' => $this->payer_reference,

            // Confirmation
            'confirmed_date' => $this->confirmed_date?->format('Y-m-d'),

            // Reconciliation
            'is_reconciled' => $this->is_reconciled,
            'reconciled_by' => $this->whenLoaded('reconciledByUser',
                fn() => $this->reconciledByUser?->username ?? $this->reconciledByUser?->email ?? null,
                $this->reconciled_by
            ),
            'reconciled_at' => $this->reconciled_at?->toIso8601String(),
            'reconciliation_reference' => $this->reconciliation_reference,

            // Allocations
            'allocations' => $this->whenLoaded('allocations', fn() => $this->allocations->map(fn($alloc) => [
                'id' => $alloc->id,
                'invoice_id' => $alloc->invoice_id,
                'amount' => (float) $alloc->amount,
                'allocation_date' => $alloc->allocation_date?->format('Y-m-d'),
                'invoice' => $alloc->relationLoaded('invoice') && $alloc->invoice ? [
                    'id' => $alloc->invoice->id,
                    'invoice_number' => $alloc->invoice->invoice_number,
                    'total_amount' => (float) $alloc->invoice->total_amount,
                    'balance' => (float) $alloc->invoice->balance,
                    'status' => $alloc->invoice->status,
                ] : null,
            ])),

            // State flags
            'is_pending' => $this->is_pending,
            'is_received' => $this->is_received,
            'is_confirmed' => $this->is_confirmed,
            'is_bounced' => $this->is_bounced,
            'is_reversed' => $this->is_reversed,
            'is_refunded' => $this->is_refunded,
            'is_valid' => $this->is_valid,
            'is_fully_allocated' => $this->is_fully_allocated,
            'has_unallocated' => $this->has_unallocated,

            // Actions
            'can_be_allocated' => $this->can_be_allocated,
            'can_be_reversed' => $this->can_be_reversed,
            'can_be_reconciled' => $this->can_be_reconciled,

            // Metadata
            'notes' => $this->notes,
            'received_by' => $this->whenLoaded('receivedByUser',
                fn() => $this->receivedByUser?->username ?? $this->receivedByUser?->email ?? null,
                $this->received_by
            ),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
