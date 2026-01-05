<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'application_number' => $this->application_number,
            'application_type' => $this->application_type,
            'application_type_label' => $this->application_type_label,
            'policy_type' => $this->policy_type,
            'policy_type_label' => $this->policy_type_label,
            
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
            'group' => $this->whenLoaded('group', fn() => [
                'id' => $this->group->id,
                'code' => $this->group->code,
                'name' => $this->group->name,
            ]),
            
            'applicant_name' => $this->applicant_name,
            'contact_email' => $this->contact_email,
            
            'proposed_start_date' => $this->proposed_start_date?->format('Y-m-d'),
            'quote_valid_until' => $this->quote_valid_until?->format('Y-m-d'),
            'days_until_expiry' => $this->days_until_expiry,
            
            'billing_frequency' => $this->billing_frequency,
            'currency' => $this->currency,
            'gross_premium' => (float) $this->gross_premium,
            
            'member_count' => $this->member_count ?? $this->active_members_count ?? 0,
            
            'status' => $this->status,
            'status_label' => $this->status_label,
            'underwriting_status' => $this->underwriting_status,
            
            'is_corporate' => $this->is_corporate,
            'is_expired' => $this->is_expired,
            'is_converted' => $this->is_converted,
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}