<?php

namespace Modules\Medical\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Medical\Models\Claim;
use Modules\Medical\Models\ClaimLine;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\Provider;
use Modules\Medical\Services\Eligibility\RealTimeEligibilityService;
use Modules\Medical\Constants\MedicalConstants;

class ProviderClaimController extends Controller
{
    public function __construct(
        private RealTimeEligibilityService $eligibilityService
    ) {}

    /**
     * Submit new claim
     *
     * POST /v1/provider/claims
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_number' => 'required|string|max:50',
            'claim_type' => 'required|in:in_patient,out_patient,dental,optical,maternity,wellness',
            'service_date' => 'required|date|before_or_equal:today',
            'service_end_date' => 'nullable|date|after_or_equal:service_date',

            // Diagnosis
            'primary_diagnosis' => 'required|string|max:255',
            'primary_icd_code' => 'nullable|string|max:10',
            'secondary_diagnoses' => 'nullable|array',

            // Provider invoice
            'provider_invoice_number' => 'nullable|string|max:50',
            'provider_notes' => 'nullable|string|max:1000',

            // Pre-auth reference (if applicable)
            'preauth_number' => 'nullable|string|max:30',

            // Claim lines
            'lines' => 'required|array|min:1',
            'lines.*.benefit_code' => 'nullable|string|max:30',
            'lines.*.service_code' => 'nullable|string|max:30',
            'lines.*.service_description' => 'required|string|max:255',
            'lines.*.service_date' => 'nullable|date',
            'lines.*.quantity' => 'nullable|numeric|min:0.01',
            'lines.*.unit' => 'nullable|string|max:20',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.claimed_amount' => 'required|numeric|min:0.01',
        ]);

        $provider = $request->attributes->get('provider');
        $credential = $request->attributes->get('provider_credential');

        // 1. Verify member eligibility
        $eligibility = $this->eligibilityService->checkEligibility($validated['card_number']);

        if (!$eligibility->eligible) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $eligibility->ineligibleCode,
                    'message' => $eligibility->ineligibleReason,
                ],
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        // Get member and policy from eligibility result
        $member = Member::find($eligibility->member->id);
        $policy = $member->policy;

        // Attach to request for logging
        $request->attributes->set('member_id', $member->id);
        $request->attributes->set('policy_id', $policy->id);

        try {
            $claim = DB::transaction(function () use ($validated, $member, $policy, $provider, $credential) {
                // 2. Create claim
                $claim = Claim::create([
                    'claim_number' => $this->generateClaimNumber(),
                    'policy_id' => $policy->id,
                    'member_id' => $member->id,
                    'provider_id_fk' => $provider?->id,
                    'provider_name' => $provider?->name,
                    'provider_type' => $provider?->provider_type,
                    'provider_invoice_number' => $validated['provider_invoice_number'] ?? null,

                    'claim_type' => $validated['claim_type'],
                    'submission_type' => 'provider',
                    'submission_source' => 'api',
                    'submission_channel' => MedicalConstants::SUBMISSION_CHANNEL_API,

                    'service_date' => $validated['service_date'],
                    'service_end_date' => $validated['service_end_date'] ?? null,

                    'primary_diagnosis' => $validated['primary_diagnosis'],
                    'primary_icd_code' => $validated['primary_icd_code'] ?? null,
                    'secondary_diagnoses' => $validated['secondary_diagnoses'] ?? null,

                    'preauth_number' => $validated['preauth_number'] ?? null,

                    'status' => MedicalConstants::CLAIM_STATUS_SUBMITTED,
                    'received_at' => now(),

                    'claimed_amount' => collect($validated['lines'])->sum('claimed_amount'),
                    'currency' => $policy->currency ?? 'ZMW',

                    'api_credential_id' => $credential?->id,
                    'api_request_id' => Str::uuid()->toString(),

                    'queue_priority' => $this->determineQueuePriority($validated['claim_type']),
                ]);

                // 3. Create claim lines
                $lineNumber = 1;
                foreach ($validated['lines'] as $lineData) {
                    // Find benefit by code if provided
                    $benefitId = null;
                    if (!empty($lineData['benefit_code'])) {
                        $benefit = \Modules\Medical\Models\Benefit::where('code', $lineData['benefit_code'])->first();
                        $benefitId = $benefit?->id;
                    }

                    ClaimLine::create([
                        'claim_id' => $claim->id,
                        'line_number' => $lineNumber++,
                        'benefit_id' => $benefitId,
                        'service_code' => $lineData['service_code'] ?? null,
                        'service_description' => $lineData['service_description'],
                        'service_date' => $lineData['service_date'] ?? $validated['service_date'],
                        'quantity' => $lineData['quantity'] ?? 1,
                        'unit' => $lineData['unit'] ?? 'service',
                        'unit_price' => $lineData['unit_price'] ?? $lineData['claimed_amount'],
                        'claimed_amount' => $lineData['claimed_amount'],
                        'status' => 'pending',
                    ]);
                }

                // 4. Add submission note
                $claim->notes()->create([
                    'note_type' => 'system',
                    'content' => "Claim submitted via Provider API by {$provider?->name}",
                    'is_internal' => true,
                ]);

                // 5. Recalculate totals
                $claim->recalculateTotals();

                return $claim->fresh(['lines', 'member', 'policy']);
            });

            // Attach claim_id to request for logging
            $request->attributes->set('claim_id', $claim->id);

            // TODO: Dispatch ProcessClaimJob to queue for async processing
            // ProcessClaimJob::dispatch($claim->id)->onQueue('medical-claims-standard');

            return response()->json([
                'success' => true,
                'data' => [
                    'claim_number' => $claim->claim_number,
                    'status' => $claim->status,
                    'status_label' => $claim->status_label,
                    'claimed_amount' => $claim->claimed_amount,
                    'lines_count' => $claim->lines->count(),
                    'preliminary_result' => [
                        'auto_adjudication_status' => 'queued',
                        'estimated_processing_time' => '15 minutes',
                        'fraud_check_status' => 'pending',
                    ],
                    'next_steps' => [
                        'Claim will be processed automatically',
                        'You will receive status updates via webhook (if configured)',
                        'Use GET /claims/' . $claim->claim_number . ' to check status',
                    ],
                ],
                'timestamp' => now()->toIso8601String(),
            ], 201);

        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLAIM_SUBMISSION_FAILED',
                    'message' => 'Failed to submit claim. Please try again.',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 500);
        }
    }

    /**
     * Get claim status
     *
     * GET /v1/provider/claims/{claimNumber}
     *
     * @param Request $request
     * @param string $claimNumber
     * @return JsonResponse
     */
    public function show(Request $request, string $claimNumber): JsonResponse
    {
        $provider = $request->attributes->get('provider');

        $claim = Claim::with(['lines.benefit', 'documents', 'member', 'policy.plan'])
            ->where('claim_number', $claimNumber)
            ->first();

        if (!$claim) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLAIM_NOT_FOUND',
                    'message' => 'Claim not found',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        // Verify provider owns this claim (if submitted via API)
        if ($claim->provider_id_fk && $provider && $claim->provider_id_fk !== $provider->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCESS_DENIED',
                    'message' => 'You do not have access to this claim',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 403);
        }

        $request->attributes->set('claim_id', $claim->id);
        $request->attributes->set('member_id', $claim->member_id);

        return response()->json([
            'success' => true,
            'data' => [
                'claim_number' => $claim->claim_number,
                'status' => $claim->status,
                'status_label' => $claim->status_label,
                'claim_type' => $claim->claim_type,
                'claim_type_label' => $claim->claim_type_label,

                'service_date' => $claim->service_date?->format('Y-m-d'),
                'service_end_date' => $claim->service_end_date?->format('Y-m-d'),

                'primary_diagnosis' => $claim->primary_diagnosis,
                'primary_icd_code' => $claim->primary_icd_code,

                'financials' => [
                    'currency' => $claim->currency,
                    'claimed_amount' => $claim->claimed_amount,
                    'approved_amount' => $claim->approved_amount,
                    'copay_amount' => $claim->copay_amount,
                    'deductible_amount' => $claim->deductible_amount,
                    'payable_amount' => $claim->payable_amount,
                    'paid_amount' => $claim->paid_amount,
                ],

                'processing' => [
                    'received_at' => $claim->received_at?->toIso8601String(),
                    'processed_at' => $claim->processed_at?->toIso8601String(),
                    'approved_at' => $claim->approved_at?->toIso8601String(),
                    'paid_at' => $claim->paid_at?->toIso8601String(),
                    'tat_days' => $claim->tat_days,
                    'auto_adjudicated' => $claim->auto_adjudicated ?? false,
                    'fraud_score' => $claim->fraud_score,
                    'fraud_check_status' => $claim->fraud_check_status,
                ],

                'rejection' => $claim->status === MedicalConstants::CLAIM_STATUS_REJECTED ? [
                    'reason' => $claim->rejection_reason,
                    'reason_label' => $claim->rejection_reason_label,
                    'notes' => $claim->rejection_notes,
                    'rejected_at' => $claim->rejected_at?->toIso8601String(),
                ] : null,

                'lines' => $claim->lines->map(fn($line) => [
                    'line_number' => $line->line_number,
                    'service_code' => $line->service_code,
                    'service_description' => $line->service_description,
                    'benefit_name' => $line->benefit?->name,
                    'quantity' => $line->quantity,
                    'claimed_amount' => $line->claimed_amount,
                    'approved_amount' => $line->approved_amount,
                    'status' => $line->status,
                    'rejection_reason' => $line->rejection_reason,
                ]),

                'documents_count' => $claim->documents->count(),

                'member' => [
                    'name' => $claim->member?->full_name,
                    'member_number' => $claim->member?->member_number,
                    'card_number' => $claim->member?->card_number,
                ],

                'policy' => [
                    'policy_number' => $claim->policy?->policy_number,
                    'plan_name' => $claim->policy?->plan?->name,
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Add lines to existing claim
     *
     * POST /v1/provider/claims/{claimNumber}/lines
     *
     * @param Request $request
     * @param string $claimNumber
     * @return JsonResponse
     */
    public function addLines(Request $request, string $claimNumber): JsonResponse
    {
        $provider = $request->attributes->get('provider');

        $claim = Claim::where('claim_number', $claimNumber)->first();

        if (!$claim) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLAIM_NOT_FOUND',
                    'message' => 'Claim not found',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        // Check if claim can be edited
        if (!in_array($claim->status, [MedicalConstants::CLAIM_STATUS_SUBMITTED, MedicalConstants::CLAIM_STATUS_PENDING_DOCUMENTS])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLAIM_NOT_EDITABLE',
                    'message' => 'Claim cannot be modified in its current status',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        $validated = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.benefit_code' => 'nullable|string|max:30',
            'lines.*.service_code' => 'nullable|string|max:30',
            'lines.*.service_description' => 'required|string|max:255',
            'lines.*.service_date' => 'nullable|date',
            'lines.*.quantity' => 'nullable|numeric|min:0.01',
            'lines.*.unit' => 'nullable|string|max:20',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.claimed_amount' => 'required|numeric|min:0.01',
        ]);

        $maxLineNumber = $claim->lines()->max('line_number') ?? 0;

        foreach ($validated['lines'] as $lineData) {
            $benefitId = null;
            if (!empty($lineData['benefit_code'])) {
                $benefit = \Modules\Medical\Models\Benefit::where('code', $lineData['benefit_code'])->first();
                $benefitId = $benefit?->id;
            }

            ClaimLine::create([
                'claim_id' => $claim->id,
                'line_number' => ++$maxLineNumber,
                'benefit_id' => $benefitId,
                'service_code' => $lineData['service_code'] ?? null,
                'service_description' => $lineData['service_description'],
                'service_date' => $lineData['service_date'] ?? $claim->service_date,
                'quantity' => $lineData['quantity'] ?? 1,
                'unit' => $lineData['unit'] ?? 'service',
                'unit_price' => $lineData['unit_price'] ?? $lineData['claimed_amount'],
                'claimed_amount' => $lineData['claimed_amount'],
                'status' => 'pending',
            ]);
        }

        $claim->recalculateTotals();

        return response()->json([
            'success' => true,
            'data' => [
                'claim_number' => $claim->claim_number,
                'lines_added' => count($validated['lines']),
                'total_lines' => $claim->lines()->count(),
                'claimed_amount' => $claim->fresh()->claimed_amount,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Upload document to claim
     *
     * POST /v1/provider/claims/{claimNumber}/documents
     *
     * @param Request $request
     * @param string $claimNumber
     * @return JsonResponse
     */
    public function uploadDocument(Request $request, string $claimNumber): JsonResponse
    {
        $claim = Claim::where('claim_number', $claimNumber)->first();

        if (!$claim) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLAIM_NOT_FOUND',
                    'message' => 'Claim not found',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        $validated = $request->validate([
            'document' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
            'document_type' => 'required|in:invoice,receipt,prescription,medical_report,lab_result,referral,preauth,discharge_summary,id_copy,other',
            'description' => 'nullable|string|max:255',
        ]);

        $file = $request->file('document');
        $path = $file->store("claims/{$claim->id}/documents", 'private');

        $document = $claim->documents()->create([
            'document_type' => $validated['document_type'],
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'upload_source' => 'api',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'document_id' => $document->id,
                'document_type' => $document->document_type,
                'file_name' => $document->file_name,
                'uploaded_at' => $document->created_at->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ], 201);
    }

    /**
     * Get claim documents
     *
     * GET /v1/provider/claims/{claimNumber}/documents
     *
     * @param Request $request
     * @param string $claimNumber
     * @return JsonResponse
     */
    public function documents(Request $request, string $claimNumber): JsonResponse
    {
        $claim = Claim::with('documents')
            ->where('claim_number', $claimNumber)
            ->first();

        if (!$claim) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CLAIM_NOT_FOUND',
                    'message' => 'Claim not found',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'claim_number' => $claim->claim_number,
                'documents' => $claim->documents->map(fn($doc) => [
                    'id' => $doc->id,
                    'document_type' => $doc->document_type,
                    'file_name' => $doc->file_name,
                    'mime_type' => $doc->mime_type,
                    'file_size' => $doc->file_size,
                    'is_verified' => $doc->is_verified,
                    'uploaded_at' => $doc->created_at->toIso8601String(),
                ]),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get member claim history
     *
     * GET /v1/provider/claims/member/{cardNumber}
     *
     * @param Request $request
     * @param string $cardNumber
     * @return JsonResponse
     */
    public function memberHistory(Request $request, string $cardNumber): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        // Find member
        $member = Member::where('card_number', $cardNumber)->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MEMBER_NOT_FOUND',
                    'message' => 'Member not found',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        $request->attributes->set('member_id', $member->id);

        $query = Claim::where('member_id', $member->id)
            ->orderByDesc('service_date');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['from_date'])) {
            $query->where('service_date', '>=', $validated['from_date']);
        }

        if (!empty($validated['to_date'])) {
            $query->where('service_date', '<=', $validated['to_date']);
        }

        $claims = $query->limit($validated['limit'] ?? 20)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'member' => [
                    'name' => $member->full_name,
                    'card_number' => $member->card_number,
                ],
                'claims' => $claims->map(fn($claim) => [
                    'claim_number' => $claim->claim_number,
                    'status' => $claim->status,
                    'status_label' => $claim->status_label,
                    'claim_type' => $claim->claim_type,
                    'service_date' => $claim->service_date?->format('Y-m-d'),
                    'claimed_amount' => $claim->claimed_amount,
                    'approved_amount' => $claim->approved_amount,
                    'submitted_at' => $claim->created_at->toIso8601String(),
                ]),
                'total' => $claims->count(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Bulk claim submission
     *
     * POST /v1/provider/claims/bulk
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkStore(Request $request): JsonResponse
    {
        // Placeholder for bulk submission
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NOT_IMPLEMENTED',
                'message' => 'Bulk submission is not yet implemented',
            ],
            'timestamp' => now()->toIso8601String(),
        ], 501);
    }

    /**
     * Get bulk submission status
     *
     * GET /v1/provider/claims/bulk/{batchId}
     *
     * @param Request $request
     * @param string $batchId
     * @return JsonResponse
     */
    public function bulkStatus(Request $request, string $batchId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NOT_IMPLEMENTED',
                'message' => 'Bulk status is not yet implemented',
            ],
            'timestamp' => now()->toIso8601String(),
        ], 501);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function generateClaimNumber(): string
    {
        $prefix = 'CLM';
        $year = now()->format('Y');
        $sequence = Claim::whereYear('created_at', now()->year)->count() + 1;

        return sprintf('%s-%s-%06d', $prefix, $year, $sequence);
    }

    private function determineQueuePriority(string $claimType): int
    {
        // Lower number = higher priority
        return match ($claimType) {
            'in_patient' => 2,  // Urgent
            'maternity' => 2,   // Urgent
            'out_patient' => 5, // Standard
            'dental' => 6,
            'optical' => 6,
            'wellness' => 7,
            default => 5,
        };
    }
}
