<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'invoice_type' => $this->invoice_type,
            'invoice_type_label' => $this->invoice_type_label,
            'status' => $this->status,
            'status_label' => $this->status_label,

            // Policy & Group
            'policy_id' => $this->policy_id,
            'policy' => $this->whenLoaded('policy', fn() => [
                'id' => $this->policy->id,
                'policy_number' => $this->policy->policy_number,
                'holder_name' => $this->policy->holder_name,
                'holder_email' => $this->policy->holder_email,
                'status' => $this->policy->status,
                'inception_date' => $this->policy->inception_date?->format('Y-m-d'),
                'expiry_date' => $this->policy->expiry_date?->format('Y-m-d'),
            ]),
            'group_id' => $this->group_id,
            'group' => $this->whenLoaded('group', fn() => [
                'id' => $this->group->id,
                'code' => $this->group->code,
                'name' => $this->group->name,
                'billing_email' => $this->group->billing_email,
                'address' => $this->group->address,
            ]),

            // Endorsement (if applicable)
            'endorsement_id' => $this->endorsement_id,
            'endorsement' => $this->whenLoaded('endorsement', fn() => [
                'id' => $this->endorsement->id,
                'endorsement_number' => $this->endorsement->endorsement_number,
                'endorsement_type' => $this->endorsement->endorsement_type,
                'effective_date' => $this->endorsement->effective_date?->format('Y-m-d'),
            ]),

            // Billing Period
            'billing_period_start' => $this->billing_period_start?->format('Y-m-d'),
            'billing_period_end' => $this->billing_period_end?->format('Y-m-d'),
            'billing_period' => $this->billing_period,

            // Dates
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'paid_date' => $this->paid_date?->format('Y-m-d'),

            // Amounts
            'currency' => $this->currency,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'balance' => (float) $this->balance,
            'payment_progress' => $this->payment_progress,

            // Overdue
            'days_overdue' => $this->days_overdue,
            'is_overdue' => $this->is_overdue,

            // Bill To
            'bill_to_name' => $this->bill_to_name,
            'bill_to_email' => $this->bill_to_email,
            'bill_to_address' => $this->bill_to_address,

            // Communication
            'sent_at' => $this->sent_at?->toIso8601String(),
            'sent_via' => $this->sent_via,
            'reminder_count' => $this->reminder_count,
            'last_reminder_at' => $this->last_reminder_at?->toIso8601String(),

            // Line Items
            'items' => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'id' => $item->id,
                'line_number' => $item->line_number,
                'item_type' => $item->item_type,
                'item_type_label' => $item->item_type_label,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'amount' => (float) $item->amount,
                'tax_amount' => (float) $item->tax_amount,
                'total' => (float) $item->total,
                'member_id' => $item->member_id,
                'member' => $item->relationLoaded('member') && $item->member ? [
                    'id' => $item->member->id,
                    'member_number' => $item->member->member_number,
                    'full_name' => $item->member->full_name,
                ] : null,
                'reference_type' => $item->reference_type,
                'reference_id' => $item->reference_id,
            ])),

            // Payment Allocations
            'allocations' => $this->whenLoaded('allocations', fn() => $this->allocations->map(fn($alloc) => [
                'id' => $alloc->id,
                'payment_id' => $alloc->payment_id,
                'amount' => (float) $alloc->amount,
                'allocation_date' => $alloc->allocation_date?->format('Y-m-d'),
                'payment' => $alloc->relationLoaded('payment') && $alloc->payment ? [
                    'id' => $alloc->payment->id,
                    'payment_number' => $alloc->payment->payment_number,
                    'payment_date' => $alloc->payment->payment_date?->format('Y-m-d'),
                    'amount' => (float) $alloc->payment->amount,
                    'status' => $alloc->payment->status,
                ] : null,
            ])),
            'total_allocated' => $this->whenLoaded('allocations', fn() => (float) $this->total_allocated),

            // State flags
            'is_draft' => $this->is_draft,
            'is_sent' => $this->is_sent,
            'is_paid' => $this->is_paid,
            'is_partially_paid' => $this->is_partially_paid,
            'is_cancelled' => $this->is_cancelled,
            'is_written_off' => $this->is_written_off,
            'is_credit_note' => $this->is_credit_note,

            // Actions
            'can_be_sent' => $this->can_be_sent,
            'can_receive_payment' => $this->can_receive_payment,
            'can_be_cancelled' => $this->can_be_cancelled,

            // Metadata
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
