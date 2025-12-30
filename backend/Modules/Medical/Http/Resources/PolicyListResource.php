<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PolicyListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'policy_number' => $this->policy_number,
            'policy_type' => $this->policy_type,
            'policy_type_label' => $this->policy_type_label,
            'policy_holder_name' => $this->policy_holder_name,
            
            // Product (minimal)
            'scheme_id' => $this->scheme_id,
            'plan_id' => $this->plan_id,
            'scheme' => $this->whenLoaded('scheme', fn() => [
                'id' => $this->scheme->id,
                'code' => $this->scheme->code,
                'name' => $this->scheme->name,
            ]),
            'plan' => $this->whenLoaded('plan', fn() => [
                'id' => $this->plan->id,
                'code' => $this->plan->code,
                'name' => $this->plan->name,
            ]),
            
            // Corporate
            'group_id' => $this->group_id,
            'group' => $this->whenLoaded('group', fn() => [
                'id' => $this->group->id,
                'code' => $this->group->code,
                'name' => $this->group->name,
            ]),
            
            // Dates
            'inception_date' => $this->inception_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'days_to_expiry' => $this->days_to_expiry,
            'is_expiring' => $this->is_expiring,
            
            // Premium
            'billing_frequency' => $this->billing_frequency,
            'gross_premium' => (float) $this->gross_premium,
            'monthly_premium' => $this->monthly_premium,
            
            // Members
            'member_count' => $this->member_count,
            'members_count' => $this->whenCounted('members'),
            
            // Status
            'status' => $this->status,
            'status_label' => $this->status_label,
            'underwriting_status' => $this->underwriting_status,
            
            'created_at' => $this->created_at,
        ];
    }
}