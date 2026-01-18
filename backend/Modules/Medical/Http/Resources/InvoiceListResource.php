<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceListResource extends JsonResource
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

            // Policy & Group summary
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

            // Dates
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'paid_date' => $this->paid_date?->format('Y-m-d'),
            'billing_period' => $this->billing_period,

            // Amounts
            'currency' => $this->currency,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'balance' => (float) $this->balance,
            'payment_progress' => $this->payment_progress,

            // Overdue
            'days_overdue' => $this->days_overdue,
            'is_overdue' => $this->is_overdue,

            // Bill To
            'bill_to_name' => $this->bill_to_name,

            // State flags
            'is_paid' => $this->is_paid,
            'is_partially_paid' => $this->is_partially_paid,
            'can_receive_payment' => $this->can_receive_payment,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
