<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClaimListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'claim_number' => $this->claim_number,
            'claim_type' => $this->claim_type,
            'claim_type_label' => $this->claim_type_label,
            'service_date' => $this->service_date?->toDateString(),
            'provider_name' => $this->provider_name,
            'primary_diagnosis' => $this->primary_diagnosis,

            // Amounts
            'currency' => $this->currency,
            'claimed_amount' => (float) $this->claimed_amount,
            'approved_amount' => (float) $this->approved_amount,
            'payable_amount' => (float) $this->payable_amount,
            'paid_amount' => (float) $this->paid_amount,

            // Status
            'status' => $this->status,
            'status_label' => $this->status_label,
            'priority' => $this->priority,
            'is_flagged' => $this->is_flagged,

            // Assignment
            'assigned_to' => $this->assigned_to,

            // TAT
            'received_at' => $this->received_at?->toIso8601String(),
            'tat_days' => $this->tat_days,

            // Policy & Member (compact)
            'policy' => $this->whenLoaded('policy', fn() => [
                'id' => $this->policy->id,
                'policy_number' => $this->policy->policy_number,
                'holder_name' => $this->policy->holder_name,
            ]),

            'member' => $this->whenLoaded('member', fn() => [
                'id' => $this->member->id,
                'member_number' => $this->member->member_number,
                'full_name' => $this->member->first_name . ' ' . $this->member->last_name,
                'relationship' => $this->member->relationship,
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
