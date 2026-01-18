<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClaimResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'claim_number' => $this->claim_number,

            // Links
            'policy_id' => $this->policy_id,
            'member_id' => $this->member_id,

            // Type & Source
            'claim_type' => $this->claim_type,
            'claim_type_label' => $this->claim_type_label,
            'submission_type' => $this->submission_type,
            'submission_type_label' => $this->submission_type_label,
            'submission_channel' => $this->submission_channel,

            // Service Details
            'service_date' => $this->service_date?->toDateString(),
            'service_end_date' => $this->service_end_date?->toDateString(),
            'admission_date' => $this->admission_date?->toDateString(),
            'discharge_date' => $this->discharge_date?->toDateString(),
            'days_admitted' => $this->days_admitted,
            'is_in_patient' => $this->is_in_patient,

            // Provider
            'provider_id' => $this->provider_id,
            'provider_name' => $this->provider_name,
            'provider_type' => $this->provider_type,
            'provider_type_label' => $this->provider_type_label,
            'provider_invoice_number' => $this->provider_invoice_number,

            // Diagnosis
            'primary_diagnosis' => $this->primary_diagnosis,
            'primary_icd_code' => $this->primary_icd_code,
            'secondary_diagnoses' => $this->secondary_diagnoses,
            'diagnosis_notes' => $this->diagnosis_notes,

            // Amounts
            'currency' => $this->currency,
            'claimed_amount' => (float) $this->claimed_amount,
            'approved_amount' => (float) $this->approved_amount,
            'copay_amount' => (float) $this->copay_amount,
            'deductible_amount' => (float) $this->deductible_amount,
            'excess_amount' => (float) $this->excess_amount,
            'excluded_amount' => (float) $this->excluded_amount,
            'payable_amount' => (float) $this->payable_amount,
            'paid_amount' => (float) $this->paid_amount,
            'net_payable' => (float) $this->net_payable,
            'outstanding_amount' => (float) $this->outstanding_amount,

            // Payment
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'payment_date' => $this->payment_date?->toDateString(),
            'paid_to' => $this->paid_to,
            'bank_name' => $this->bank_name,
            'account_number' => $this->account_number,

            // Pre-auth
            'requires_preauth' => $this->requires_preauth,
            'preauth_number' => $this->preauth_number,
            'preauth_status' => $this->preauth_status,
            'preauth_amount' => $this->preauth_amount ? (float) $this->preauth_amount : null,
            'preauth_at' => $this->preauth_at?->toIso8601String(),

            // Status
            'status' => $this->status,
            'status_label' => $this->status_label,
            'substatus' => $this->substatus,
            'priority' => $this->priority,

            // Rejection
            'rejection_reason' => $this->rejection_reason,
            'rejection_reason_label' => $this->rejection_reason_label,
            'rejection_notes' => $this->rejection_notes,

            // Flags
            'is_flagged' => $this->is_flagged,
            'flag_reason' => $this->flag_reason,
            'fraud_score' => $this->fraud_score,
            'requires_audit' => $this->requires_audit,

            // Workflow permissions
            'can_be_edited' => $this->can_be_edited,
            'can_be_processed' => $this->can_be_processed,
            'can_be_approved' => $this->can_be_approved,
            'can_be_paid' => $this->can_be_paid,

            // Assignment
            'assigned_to' => $this->assigned_to,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'processed_by' => $this->processed_by,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_by' => $this->rejected_by,
            'rejected_at' => $this->rejected_at?->toIso8601String(),

            // TAT
            'received_at' => $this->received_at?->toIso8601String(),
            'first_response_at' => $this->first_response_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'tat_days' => $this->tat_days,

            // Relationships
            'policy' => $this->whenLoaded('policy', fn() => [
                'id' => $this->policy->id,
                'policy_number' => $this->policy->policy_number,
                'holder_name' => $this->policy->holder_name,
                'status' => $this->policy->status,
                'inception_date' => $this->policy->inception_date?->toDateString(),
                'expiry_date' => $this->policy->expiry_date?->toDateString(),
                'plan' => $this->when($this->policy->relationLoaded('plan'), fn() => [
                    'id' => $this->policy->plan?->id,
                    'name' => $this->policy->plan?->name,
                    'code' => $this->policy->plan?->code,
                ]),
                'scheme' => $this->when($this->policy->relationLoaded('scheme'), fn() => [
                    'id' => $this->policy->scheme?->id,
                    'name' => $this->policy->scheme?->name,
                    'code' => $this->policy->scheme?->code,
                ]),
            ]),

            'member' => $this->whenLoaded('member', fn() => new MemberListResource($this->member)),

            'lines' => $this->whenLoaded('lines', fn() => $this->lines->map(fn($line) => [
                'id' => $line->id,
                'line_number' => $line->line_number,
                'service_code' => $line->service_code,
                'service_description' => $line->service_description,
                'service_date' => $line->service_date?->toDateString(),
                'quantity' => (float) $line->quantity,
                'unit' => $line->unit,
                'unit_price' => (float) $line->unit_price,
                'claimed_amount' => (float) $line->claimed_amount,
                'approved_amount' => (float) $line->approved_amount,
                'copay_amount' => (float) $line->copay_amount,
                'deductible_amount' => (float) $line->deductible_amount,
                'excess_amount' => (float) $line->excess_amount,
                'excluded_amount' => (float) $line->excluded_amount,
                'payable_amount' => (float) $line->payable_amount,
                'status' => $line->status,
                'status_label' => $line->status_label,
                'rejection_reason' => $line->rejection_reason,
                'rejection_reason_label' => $line->rejection_reason_label,
                'adjudication_notes' => $line->adjudication_notes,
                'benefit' => $line->relationLoaded('benefit') && $line->benefit ? [
                    'id' => $line->benefit->id,
                    'name' => $line->benefit->name,
                    'code' => $line->benefit->code,
                    'category' => $line->benefit->category,
                ] : null,
                'benefit_limit' => $line->benefit_limit ? (float) $line->benefit_limit : null,
                'benefit_used_before' => $line->benefit_used_before ? (float) $line->benefit_used_before : null,
                'benefit_remaining' => $line->benefit_remaining ? (float) $line->benefit_remaining : null,
            ])),

            'documents' => $this->whenLoaded('documents', fn() => $this->documents->map(fn($doc) => [
                'id' => $doc->id,
                'document_type' => $doc->document_type,
                'document_type_label' => $doc->document_type_label,
                'title' => $doc->title,
                'file_name' => $doc->file_name,
                'mime_type' => $doc->mime_type,
                'file_size' => $doc->file_size,
                'file_size_formatted' => $doc->file_size_formatted,
                'is_verified' => $doc->is_verified,
                'verified_at' => $doc->verified_at?->toIso8601String(),
                'created_at' => $doc->created_at?->toIso8601String(),
            ])),

            'notes' => $this->whenLoaded('notes', fn() => $this->notes->map(fn($note) => [
                'id' => $note->id,
                'note_type' => $note->note_type,
                'note_type_label' => $note->note_type_label,
                'content' => $note->content,
                'old_status' => $note->old_status,
                'old_status_label' => $note->old_status_label,
                'new_status' => $note->new_status,
                'new_status_label' => $note->new_status_label,
                'is_internal' => $note->is_internal,
                'is_system' => $note->is_system,
                'created_by' => $note->created_by,
                'created_at' => $note->created_at?->toIso8601String(),
            ])),

            'documents_count' => $this->whenCounted('documents'),
            'notes_count' => $this->whenCounted('notes'),
            'lines_count' => $this->whenCounted('lines'),

            'metadata' => $this->metadata,
            'internal_notes' => $this->internal_notes,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
