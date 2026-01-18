<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EndorsementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'endorsement_number' => $this->endorsement_number,

            // Policy
            'policy_id' => $this->policy_id,
            'policy' => $this->whenLoaded('policy', fn() => [
                'id' => $this->policy->id,
                'policy_number' => $this->policy->policy_number,
                'holder_name' => $this->policy->holder_name,
                'status' => $this->policy->status,
            ]),

            // Type
            'endorsement_type' => $this->endorsement_type,
            'type_label' => $this->type_label,
            'description' => $this->description,

            // Dates
            'effective_date' => $this->effective_date?->toDateString(),
            'request_date' => $this->request_date?->toDateString(),

            // Financial Impact
            'premium_adjustment' => (float) $this->premium_adjustment,
            'prorated_amount' => (float) $this->prorated_amount,
            'generates_invoice' => $this->generates_invoice,
            'generates_refund' => $this->generates_refund,
            'has_financial_impact' => $this->has_financial_impact,
            'invoice_id' => $this->invoice_id,

            // Change Details
            'changes' => $this->changes,
            'before_snapshot' => $this->before_snapshot,
            'after_snapshot' => $this->after_snapshot,

            // Related Records
            'member_id' => $this->member_id,
            'member' => $this->whenLoaded('member', fn() => [
                'id' => $this->member->id,
                'member_number' => $this->member->member_number,
                'full_name' => $this->member->full_name,
                'member_type' => $this->member->member_type,
                'status' => $this->member->status,
            ]),
            'addon_id' => $this->addon_id,
            'addon' => $this->whenLoaded('addon', fn() => [
                'id' => $this->addon->id,
                'name' => $this->addon->name,
                'code' => $this->addon->code,
            ]),
            'new_plan_id' => $this->new_plan_id,
            'new_plan' => $this->whenLoaded('newPlan', fn() => [
                'id' => $this->newPlan->id,
                'name' => $this->newPlan->name,
                'code' => $this->newPlan->code,
            ]),

            // Status
            'status' => $this->status,
            'status_label' => $this->status_label,
            'is_pending' => $this->is_pending,
            'is_approved' => $this->is_approved,
            'is_rejected' => $this->is_rejected,
            'is_processed' => $this->is_processed,
            'is_cancelled' => $this->is_cancelled,

            // Workflow permissions
            'can_be_approved' => $this->can_be_approved,
            'can_be_rejected' => $this->can_be_rejected,
            'can_be_processed' => $this->can_be_processed,
            'can_be_cancelled' => $this->can_be_cancelled,

            // Workflow tracking
            'requested_by' => $this->requested_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'processed_by' => $this->processed_by,
            'processed_at' => $this->processed_at?->toDateTimeString(),
            'rejection_reason' => $this->rejection_reason,

            // Additional Info
            'notes' => $this->notes,
            'supporting_documents' => $this->supporting_documents,

            // Type flags
            'is_addition' => $this->is_addition,
            'is_removal' => $this->is_removal,
            'is_plan_change' => $this->is_plan_change,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
