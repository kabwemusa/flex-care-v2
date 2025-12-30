<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'policy_number' => $this->policy_number,
            'policy_type' => $this->policy_type,
            'policy_type_label' => $this->policy_type_label,
            
            // Product
            'scheme_id' => $this->scheme_id,
            'plan_id' => $this->plan_id,
            'rate_card_id' => $this->rate_card_id,
            'scheme' => new SchemeResource($this->whenLoaded('scheme')),
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'rate_card' => new RateCardResource($this->whenLoaded('rateCard')),
            
            // Corporate
            'group_id' => $this->group_id,
            'group' => new GroupResource($this->whenLoaded('group')),
            'is_corporate' => $this->is_corporate,
            'policy_holder_name' => $this->policy_holder_name,
            
            // Principal
            'principal_member_id' => $this->principal_member_id,
            'principal_member' => new MemberResource($this->whenLoaded('principalMember')),
            
            // Dates
            'inception_date' => $this->inception_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'renewal_date' => $this->renewal_date?->toDateString(),
            'policy_term_months' => $this->policy_term_months,
            'is_auto_renew' => $this->is_auto_renew,
            'days_to_expiry' => $this->days_to_expiry,
            'is_expiring' => $this->is_expiring,
            'is_expired' => $this->is_expired,
            
            // Premium
            'currency' => $this->currency,
            'billing_frequency' => $this->billing_frequency,
            'billing_frequency_label' => $this->billing_frequency_label,
            'base_premium' => (float) $this->base_premium,
            'addon_premium' => (float) $this->addon_premium,
            'loading_amount' => (float) $this->loading_amount,
            'discount_amount' => (float) $this->discount_amount,
            'total_premium' => (float) $this->total_premium,
            'tax_amount' => (float) $this->tax_amount,
            'gross_premium' => (float) $this->gross_premium,
            'monthly_premium' => $this->monthly_premium,
            'annual_premium' => $this->annual_premium,
            
            // Members
            'member_count' => $this->member_count,
            'principal_count' => $this->principal_count,
            'dependent_count' => $this->dependent_count,
            'members_count' => $this->whenCounted('members'),
            'members' => MemberResource::collection($this->whenLoaded('members')),
            
            // Status
            'status' => $this->status,
            'status_label' => $this->status_label,
            'is_draft' => $this->is_draft,
            'is_active' => $this->is_active,
            
            // Underwriting
            'underwriting_status' => $this->underwriting_status,
            'underwriting_status_label' => $this->underwriting_status_label,
            'underwriting_notes' => $this->underwriting_notes,
            'underwritten_by' => $this->underwritten_by,
            'underwritten_at' => $this->underwritten_at,
            
            // Cancellation
            'cancelled_at' => $this->cancelled_at?->toDateString(),
            'cancellation_reason' => $this->cancellation_reason,
            'cancellation_notes' => $this->cancellation_notes,
            
            // Renewal
            'previous_policy_id' => $this->previous_policy_id,
            'renewed_to_policy_id' => $this->renewed_to_policy_id,
            'renewal_count' => $this->renewal_count,
            'can_be_renewed' => $this->canBeRenewed(),
            
            // Addons
            'policy_addons' => PolicyAddonResource::collection($this->whenLoaded('policyAddons')),
            
            // Documents
            'documents' => PolicyDocumentResource::collection($this->whenLoaded('documents')),
            
            // Promo
            'promo_code_id' => $this->promo_code_id,
            'promo_code' => new PromoCodeResource($this->whenLoaded('promoCode')),
            'applied_discounts' => $this->applied_discounts,
            'applied_loadings' => $this->applied_loadings,
            
            // Sales
            'sales_agent_id' => $this->sales_agent_id,
            'broker_id' => $this->broker_id,
            'commission_rate' => $this->commission_rate,
            'commission_amount' => $this->commission_amount,
            'source' => $this->source,
            
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}