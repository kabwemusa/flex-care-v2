<?php

namespace Modules\Medical\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Medical\Models\Member;
use Modules\Medical\Services\Eligibility\RealTimeEligibilityService;

class ProviderPreAuthController extends Controller
{
    public function __construct(
        private RealTimeEligibilityService $eligibilityService
    ) {}

    /**
     * Submit pre-authorization request
     *
     * POST /v1/provider/preauth
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_number' => 'required|string|max:50',
            'priority' => 'nullable|in:standard,urgent,emergency',

            // Service details
            'requested_service_date' => 'required|date|after_or_equal:today',
            'service_end_date' => 'nullable|date|after_or_equal:requested_service_date',
            'admission_date' => 'nullable|date',
            'discharge_date' => 'nullable|date|after_or_equal:admission_date',
            'estimated_days' => 'nullable|integer|min:1',

            // Diagnosis
            'primary_diagnosis' => 'required|string|max:255',
            'primary_icd_code' => 'nullable|string|max:10',
            'secondary_diagnoses' => 'nullable|array',
            'treatment_plan' => 'nullable|string|max:2000',
            'clinical_notes' => 'nullable|string|max:2000',

            // Provider/Doctor info
            'attending_doctor' => 'nullable|string|max:255',
            'doctor_license' => 'nullable|string|max:50',

            // Lines
            'lines' => 'required|array|min:1',
            'lines.*.benefit_code' => 'nullable|string|max:30',
            'lines.*.service_code' => 'nullable|string|max:30',
            'lines.*.service_description' => 'required|string|max:255',
            'lines.*.quantity' => 'nullable|numeric|min:0.01',
            'lines.*.unit' => 'nullable|string|max:20',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.requested_amount' => 'required|numeric|min:0.01',
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

        $member = Member::find($eligibility->member->id);
        $policy = $member->policy;

        $request->attributes->set('member_id', $member->id);
        $request->attributes->set('policy_id', $policy->id);

        try {
            $preauth = DB::transaction(function () use ($validated, $member, $policy, $provider) {
                $totalAmount = collect($validated['lines'])->sum('requested_amount');

                // Create pre-authorization
                $preauth = \Modules\Medical\Models\PreAuthorization::create([
                    'preauth_number' => $this->generatePreAuthNumber(),
                    'provider_id' => $provider?->id,
                    'policy_id' => $policy->id,
                    'member_id' => $member->id,

                    'requested_service_date' => $validated['requested_service_date'],
                    'service_end_date' => $validated['service_end_date'] ?? null,
                    'admission_date' => $validated['admission_date'] ?? null,
                    'discharge_date' => $validated['discharge_date'] ?? null,
                    'estimated_days' => $validated['estimated_days'] ?? null,

                    'primary_diagnosis' => $validated['primary_diagnosis'],
                    'primary_icd_code' => $validated['primary_icd_code'] ?? null,
                    'secondary_diagnoses' => $validated['secondary_diagnoses'] ?? null,
                    'treatment_plan' => $validated['treatment_plan'] ?? null,
                    'clinical_notes' => $validated['clinical_notes'] ?? null,

                    'facility_name' => $provider?->name,
                    'facility_type' => $provider?->provider_type,
                    'attending_doctor' => $validated['attending_doctor'] ?? null,
                    'doctor_license' => $validated['doctor_license'] ?? null,

                    'currency' => $policy->currency ?? 'ZMW',
                    'requested_amount' => $totalAmount,

                    'status' => 'pending',
                    'priority' => $validated['priority'] ?? 'standard',

                    'validity_hours' => 72,
                    'expires_at' => now()->addHours(72),

                    'requested_at' => now(),
                    'requested_by_type' => 'provider',
                    'requested_by_id' => $provider?->id,
                    'submission_channel' => 'api',
                ]);

                // Create lines
                $lineNumber = 1;
                foreach ($validated['lines'] as $lineData) {
                    $benefitId = null;
                    if (!empty($lineData['benefit_code'])) {
                        $benefit = \Modules\Medical\Models\Benefit::where('code', $lineData['benefit_code'])->first();
                        $benefitId = $benefit?->id;
                    }

                    $preauth->lines()->create([
                        'line_number' => $lineNumber++,
                        'benefit_id' => $benefitId,
                        'service_code' => $lineData['service_code'] ?? null,
                        'service_description' => $lineData['service_description'],
                        'quantity' => $lineData['quantity'] ?? 1,
                        'unit' => $lineData['unit'] ?? 'service',
                        'unit_price' => $lineData['unit_price'] ?? $lineData['requested_amount'],
                        'requested_amount' => $lineData['requested_amount'],
                        'status' => 'pending',
                    ]);
                }

                // Add note
                $preauth->notes()->create([
                    'note_type' => 'system',
                    'content' => "Pre-authorization submitted via Provider API by {$provider?->name}",
                    'is_internal' => true,
                    'created_by_type' => 'system',
                ]);

                return $preauth->fresh(['lines']);
            });

            $request->attributes->set('preauth_id', $preauth->id);

            // TODO: Dispatch ProcessPreAuthJob for auto-approval logic
            // ProcessPreAuthJob::dispatch($preauth->id)->onQueue('medical-preauth');

            return response()->json([
                'success' => true,
                'data' => [
                    'preauth_number' => $preauth->preauth_number,
                    'status' => $preauth->status,
                    'priority' => $preauth->priority,
                    'requested_amount' => $preauth->requested_amount,
                    'lines_count' => $preauth->lines->count(),
                    'validity' => [
                        'hours' => $preauth->validity_hours,
                        'expires_at' => $preauth->expires_at->toIso8601String(),
                    ],
                    'next_steps' => [
                        'Pre-authorization will be reviewed',
                        'Standard requests: 24-48 hours',
                        'Urgent requests: 4-8 hours',
                        'Emergency requests: 1-2 hours',
                        'Use GET /preauth/' . $preauth->preauth_number . ' to check status',
                    ],
                ],
                'timestamp' => now()->toIso8601String(),
            ], 201);

        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PREAUTH_SUBMISSION_FAILED',
                    'message' => 'Failed to submit pre-authorization request',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 500);
        }
    }

    /**
     * Get pre-authorization status
     *
     * GET /v1/provider/preauth/{preauthNumber}
     *
     * @param Request $request
     * @param string $preauthNumber
     * @return JsonResponse
     */
    public function show(Request $request, string $preauthNumber): JsonResponse
    {
        $provider = $request->attributes->get('provider');

        $preauth = \Modules\Medical\Models\PreAuthorization::with(['lines.benefit', 'member', 'policy.plan'])
            ->where('preauth_number', $preauthNumber)
            ->first();

        if (!$preauth) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PREAUTH_NOT_FOUND',
                    'message' => 'Pre-authorization not found',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        // Verify provider access
        if ($preauth->provider_id && $provider && $preauth->provider_id !== $provider->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCESS_DENIED',
                    'message' => 'You do not have access to this pre-authorization',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 403);
        }

        $request->attributes->set('preauth_id', $preauth->id);
        $request->attributes->set('member_id', $preauth->member_id);

        return response()->json([
            'success' => true,
            'data' => [
                'preauth_number' => $preauth->preauth_number,
                'status' => $preauth->status,
                'priority' => $preauth->priority,

                'service_details' => [
                    'requested_service_date' => $preauth->requested_service_date?->format('Y-m-d'),
                    'admission_date' => $preauth->admission_date?->format('Y-m-d'),
                    'discharge_date' => $preauth->discharge_date?->format('Y-m-d'),
                    'estimated_days' => $preauth->estimated_days,
                ],

                'diagnosis' => [
                    'primary' => $preauth->primary_diagnosis,
                    'icd_code' => $preauth->primary_icd_code,
                    'treatment_plan' => $preauth->treatment_plan,
                ],

                'financials' => [
                    'currency' => $preauth->currency,
                    'requested_amount' => $preauth->requested_amount,
                    'approved_amount' => $preauth->approved_amount,
                    'reserved_amount' => $preauth->reserved_amount,
                ],

                'validity' => [
                    'hours' => $preauth->validity_hours,
                    'expires_at' => $preauth->expires_at?->toIso8601String(),
                    'is_expired' => $preauth->is_expired,
                    'extension_count' => $preauth->extension_count,
                ],

                'decision' => $preauth->decision_at ? [
                    'at' => $preauth->decision_at->toIso8601String(),
                    'notes' => $preauth->decision_notes,
                    'rejection_reason' => $preauth->rejection_reason,
                ] : null,

                'lines' => $preauth->lines->map(fn($line) => [
                    'line_number' => $line->line_number,
                    'service_description' => $line->service_description,
                    'benefit_name' => $line->benefit?->name,
                    'quantity' => $line->quantity,
                    'requested_amount' => $line->requested_amount,
                    'approved_amount' => $line->approved_amount,
                    'status' => $line->status,
                    'rejection_reason' => $line->rejection_reason,
                ]),

                'member' => [
                    'name' => $preauth->member?->full_name,
                    'card_number' => $preauth->member?->card_number,
                ],

                'can_claim' => $preauth->status === 'approved' && !$preauth->is_expired && !$preauth->claim_id,
                'linked_claim' => $preauth->claim_id,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Cancel pre-authorization
     *
     * POST /v1/provider/preauth/{preauthNumber}/cancel
     *
     * @param Request $request
     * @param string $preauthNumber
     * @return JsonResponse
     */
    public function cancel(Request $request, string $preauthNumber): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $provider = $request->attributes->get('provider');

        $preauth = \Modules\Medical\Models\PreAuthorization::where('preauth_number', $preauthNumber)->first();

        if (!$preauth) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PREAUTH_NOT_FOUND',
                    'message' => 'Pre-authorization not found',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        if (!in_array($preauth->status, ['pending', 'approved', 'auto_approved'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_CANCEL',
                    'message' => 'Pre-authorization cannot be cancelled in its current status',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        if ($preauth->claim_id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ALREADY_CLAIMED',
                    'message' => 'Pre-authorization has already been used for a claim',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        $preauth->update([
            'status' => 'cancelled',
            'decision_notes' => $validated['reason'],
        ]);

        // TODO: Release any reserved benefits
        // BenefitReservationService::releaseForPreAuth($preauth);

        $preauth->notes()->create([
            'note_type' => 'status_change',
            'content' => "Cancelled by provider: {$validated['reason']}",
            'previous_status' => $preauth->getOriginal('status'),
            'new_status' => 'cancelled',
            'is_internal' => false,
            'visible_to_provider' => true,
            'created_by_type' => 'provider',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'preauth_number' => $preauth->preauth_number,
                'status' => 'cancelled',
                'message' => 'Pre-authorization has been cancelled',
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Request extension of pre-authorization validity
     *
     * POST /v1/provider/preauth/{preauthNumber}/extend
     *
     * @param Request $request
     * @param string $preauthNumber
     * @return JsonResponse
     */
    public function extend(Request $request, string $preauthNumber): JsonResponse
    {
        $validated = $request->validate([
            'additional_hours' => 'required|integer|min:24|max:168', // 1-7 days
            'reason' => 'required|string|max:500',
        ]);

        $preauth = \Modules\Medical\Models\PreAuthorization::where('preauth_number', $preauthNumber)->first();

        if (!$preauth) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PREAUTH_NOT_FOUND',
                    'message' => 'Pre-authorization not found',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        if (!in_array($preauth->status, ['approved', 'auto_approved'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_EXTEND',
                    'message' => 'Only approved pre-authorizations can be extended',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        if ($preauth->extension_count >= 3) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MAX_EXTENSIONS_REACHED',
                    'message' => 'Maximum number of extensions (3) has been reached',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        $newExpiry = $preauth->expires_at->addHours($validated['additional_hours']);

        $preauth->update([
            'expires_at' => $newExpiry,
            'validity_hours' => $preauth->validity_hours + $validated['additional_hours'],
            'extension_count' => $preauth->extension_count + 1,
            'last_extended_at' => now(),
            'is_expired' => false,
        ]);

        $preauth->notes()->create([
            'note_type' => 'system',
            'content' => "Extended by {$validated['additional_hours']} hours. Reason: {$validated['reason']}",
            'is_internal' => false,
            'visible_to_provider' => true,
            'created_by_type' => 'provider',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'preauth_number' => $preauth->preauth_number,
                'new_expires_at' => $newExpiry->toIso8601String(),
                'total_validity_hours' => $preauth->validity_hours,
                'extensions_remaining' => 3 - $preauth->extension_count,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Add line items to existing pre-authorization
     *
     * POST /v1/provider/preauth/{preauthNumber}/lines
     *
     * @param Request $request
     * @param string $preauthNumber
     * @return JsonResponse
     */
    public function addLines(Request $request, string $preauthNumber): JsonResponse
    {
        $preauth = \Modules\Medical\Models\PreAuthorization::where('preauth_number', $preauthNumber)->first();

        if (!$preauth) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PREAUTH_NOT_FOUND',
                    'message' => 'Pre-authorization not found',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        if ($preauth->status !== 'pending') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CANNOT_MODIFY',
                    'message' => 'Can only add lines to pending pre-authorizations',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        $validated = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.benefit_code' => 'nullable|string|max:30',
            'lines.*.service_code' => 'nullable|string|max:30',
            'lines.*.service_description' => 'required|string|max:255',
            'lines.*.quantity' => 'nullable|numeric|min:0.01',
            'lines.*.unit' => 'nullable|string|max:20',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.requested_amount' => 'required|numeric|min:0.01',
        ]);

        $maxLineNumber = $preauth->lines()->max('line_number') ?? 0;

        foreach ($validated['lines'] as $lineData) {
            $benefitId = null;
            if (!empty($lineData['benefit_code'])) {
                $benefit = \Modules\Medical\Models\Benefit::where('code', $lineData['benefit_code'])->first();
                $benefitId = $benefit?->id;
            }

            $preauth->lines()->create([
                'line_number' => ++$maxLineNumber,
                'benefit_id' => $benefitId,
                'service_code' => $lineData['service_code'] ?? null,
                'service_description' => $lineData['service_description'],
                'quantity' => $lineData['quantity'] ?? 1,
                'unit' => $lineData['unit'] ?? 'service',
                'unit_price' => $lineData['unit_price'] ?? $lineData['requested_amount'],
                'requested_amount' => $lineData['requested_amount'],
                'status' => 'pending',
            ]);
        }

        // Update total
        $preauth->update([
            'requested_amount' => $preauth->lines()->sum('requested_amount'),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'preauth_number' => $preauth->preauth_number,
                'lines_added' => count($validated['lines']),
                'total_lines' => $preauth->lines()->count(),
                'requested_amount' => $preauth->fresh()->requested_amount,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Upload supporting documents
     *
     * POST /v1/provider/preauth/{preauthNumber}/documents
     *
     * @param Request $request
     * @param string $preauthNumber
     * @return JsonResponse
     */
    public function uploadDocument(Request $request, string $preauthNumber): JsonResponse
    {
        $preauth = \Modules\Medical\Models\PreAuthorization::where('preauth_number', $preauthNumber)->first();

        if (!$preauth) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'PREAUTH_NOT_FOUND',
                    'message' => 'Pre-authorization not found',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        $validated = $request->validate([
            'document' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
            'document_type' => 'required|in:referral,medical_report,lab_result,prescription,other',
            'description' => 'nullable|string|max:255',
        ]);

        $file = $request->file('document');
        $path = $file->store("preauth/{$preauth->id}/documents", 'private');

        $document = $preauth->documents()->create([
            'document_type' => $validated['document_type'],
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'upload_source' => 'api',
        ]);

        $preauth->update(['has_supporting_documents' => true]);

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
     * List pre-authorizations for a member
     *
     * GET /v1/provider/preauth/member/{cardNumber}
     *
     * @param Request $request
     * @param string $cardNumber
     * @return JsonResponse
     */
    public function memberPreAuths(Request $request, string $cardNumber): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

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

        $query = \Modules\Medical\Models\PreAuthorization::where('member_id', $member->id)
            ->orderByDesc('requested_at');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $preauths = $query->limit($validated['limit'] ?? 20)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'member' => [
                    'name' => $member->full_name,
                    'card_number' => $member->card_number,
                ],
                'preauthorizations' => $preauths->map(fn($pa) => [
                    'preauth_number' => $pa->preauth_number,
                    'status' => $pa->status,
                    'priority' => $pa->priority,
                    'requested_service_date' => $pa->requested_service_date?->format('Y-m-d'),
                    'primary_diagnosis' => $pa->primary_diagnosis,
                    'requested_amount' => $pa->requested_amount,
                    'approved_amount' => $pa->approved_amount,
                    'expires_at' => $pa->expires_at?->toIso8601String(),
                    'is_expired' => $pa->is_expired,
                    'can_claim' => $pa->status === 'approved' && !$pa->is_expired && !$pa->claim_id,
                    'requested_at' => $pa->requested_at->toIso8601String(),
                ]),
                'total' => $preauths->count(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function generatePreAuthNumber(): string
    {
        $prefix = 'PA';
        $year = now()->format('Y');
        $sequence = \Modules\Medical\Models\PreAuthorization::whereYear('created_at', now()->year)->count() + 1;

        return sprintf('%s-%s-%06d', $prefix, $year, $sequence);
    }
}
