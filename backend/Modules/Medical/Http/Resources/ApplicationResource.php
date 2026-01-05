<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
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
            
            // References
            'scheme_id' => $this->scheme_id,
            'scheme' => $this->whenLoaded('scheme', fn() => [
                'id' => $this->scheme->id,
                'code' => $this->scheme->code,
                'name' => $this->scheme->name,
            ]),
            'plan_id' => $this->plan_id,
            'plan' => $this->whenLoaded('plan', fn() => [
                'id' => $this->plan->id,
                'code' => $this->plan->code,
                'name' => $this->plan->name,
            ]),
            'rate_card_id' => $this->rate_card_id,
            'rate_card' => $this->whenLoaded('rateCard', fn() => [
                'id' => $this->rateCard->id,
                'code' => $this->rateCard->code,
                'name' => $this->rateCard->name,
            ]),
            'group_id' => $this->group_id,
            'group' => $this->whenLoaded('group', fn() => [
                'id' => $this->group->id,
                'code' => $this->group->code,
                'name' => $this->group->name,
            ]),
            
            // Contact
            'contact_name' => $this->contact_name,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'applicant_name' => $this->applicant_name,
            
            // Dates
            'proposed_start_date' => $this->proposed_start_date?->format('Y-m-d'),
            'proposed_end_date' => $this->proposed_end_date?->format('Y-m-d'),
            'policy_term_months' => $this->policy_term_months,
            'quote_valid_until' => $this->quote_valid_until?->format('Y-m-d'),
            'days_until_expiry' => $this->days_until_expiry,
            
            // Billing
            'billing_frequency' => $this->billing_frequency,
            'billing_frequency_label' => $this->billing_frequency_label,
            'currency' => $this->currency,
            
            // Premium
            'base_premium' => (float) $this->base_premium,
            'addon_premium' => (float) $this->addon_premium,
            'loading_amount' => (float) $this->loading_amount,
            'discount_amount' => (float) $this->discount_amount,
            'total_premium' => (float) $this->total_premium,
            'tax_amount' => (float) $this->tax_amount,
            'gross_premium' => (float) $this->gross_premium,
            'monthly_premium' => $this->monthly_premium,
            'annual_premium' => $this->annual_premium,
            
            // Counts
            'member_count' => $this->member_count,
            'principal_count' => $this->principal_count,
            'dependent_count' => $this->dependent_count,
            
            // Status
            'status' => $this->status,
            'status_label' => $this->status_label,
            'underwriting_status' => $this->underwriting_status,
            'underwriting_status_label' => $this->underwriting_status_label,
            'underwriting_notes' => $this->underwriting_notes,
            'underwriter_id' => $this->underwriter_id,
            
            // Status flags
            'is_draft' => $this->is_draft,
            'is_quoted' => $this->is_quoted,
            'is_submitted' => $this->is_submitted,
            'is_underwriting' => $this->is_underwriting,
            'is_approved' => $this->is_approved,
            'is_declined' => $this->is_declined,
            'is_accepted' => $this->is_accepted,
            'is_converted' => $this->is_converted,
            'is_expired' => $this->is_expired,
            'is_corporate' => $this->is_corporate,
            'is_renewal' => $this->is_renewal,
            
            // Action flags
            'can_be_edited' => $this->can_be_edited,
            'can_be_submitted' => $this->can_be_submitted,
            'can_be_underwritten' => $this->can_be_underwritten,
            'can_be_accepted' => $this->can_be_accepted,
            'can_be_converted' => $this->can_be_converted,
            
            // Timestamps
            'quoted_at' => $this->quoted_at?->toISOString(),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'underwriting_started_at' => $this->underwriting_started_at?->toISOString(),
            'underwriting_completed_at' => $this->underwriting_completed_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'acceptance_reference' => $this->acceptance_reference,
            'converted_at' => $this->converted_at?->toISOString(),
            
            // Conversion
            'renewal_of_policy_id' => $this->renewal_of_policy_id,
            'renewal_of_policy' => $this->whenLoaded('renewalOfPolicy', fn() => [
                'id' => $this->renewalOfPolicy->id,
                'policy_number' => $this->renewalOfPolicy->policy_number,
            ]),
            'converted_policy_id' => $this->converted_policy_id,
            'converted_policy' => $this->whenLoaded('convertedPolicy', fn() => [
                'id' => $this->convertedPolicy->id,
                'policy_number' => $this->convertedPolicy->policy_number,
            ]),
            
            // Sales
            'source' => $this->source,
            'sales_agent_id' => $this->sales_agent_id,
            'broker_id' => $this->broker_id,
            'commission_rate' => $this->commission_rate,
            'promo_code_id' => $this->promo_code_id,
            'applied_discounts' => $this->applied_discounts,
            
            // Relations
            'members' => ApplicationMemberResource::collection($this->whenLoaded('activeMembers')),
            'addons' => $this->whenLoaded('activeAddons', fn() => $this->activeAddons->map(fn($a) => [
                'id' => $a->id,
                'addon_id' => $a->addon_id,
                'addon_name' => $a->addon?->name,
                'addon_code' => $a->addon?->code,
                'premium' => (float) $a->premium,
            ])),
            'documents' => $this->whenLoaded('documents', fn() => $this->documents->map(fn($d) => [
                'id' => $d->id,
                'document_type' => $d->document_type,
                'title' => $d->title,
                'file_name' => $d->file_name,
                'is_verified' => $d->is_verified,
            ])),
            
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}