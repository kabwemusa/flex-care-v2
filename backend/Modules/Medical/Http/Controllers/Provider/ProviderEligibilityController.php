<?php

namespace Modules\Medical\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Medical\Services\Eligibility\RealTimeEligibilityService;

class ProviderEligibilityController extends Controller
{
    public function __construct(
        private RealTimeEligibilityService $eligibilityService
    ) {}

    /**
     * Quick eligibility check
     *
     * POST /v1/provider/eligibility/check
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_number' => 'required|string|max:50',
            'service_codes' => 'nullable|array',
            'service_codes.*' => 'string|max:30',
            'service_date' => 'nullable|date',
            'estimated_amount' => 'nullable|numeric|min:0',
        ]);

        $provider = $request->attributes->get('provider');

        $result = $this->eligibilityService->checkEligibility(
            cardNumber: $validated['card_number'],
            serviceCodes: $validated['service_codes'] ?? [],
            providerId: $provider?->id,
            estimatedAmount: $validated['estimated_amount'] ?? null,
        );

        // Attach member_id to request for logging
        if ($result->member) {
            $request->attributes->set('member_id', $result->member->id);
        }

        return response()->json([
            'success' => $result->eligible,
            'data' => $result->toArray(),
            'timestamp' => now()->toIso8601String(),
        ], $result->eligible ? 200 : 200); // Always 200, check 'eligible' field
    }

    /**
     * Get member benefits summary
     *
     * GET /v1/provider/eligibility/member/{cardNumber}/benefits
     *
     * @param Request $request
     * @param string $cardNumber
     * @return JsonResponse
     */
    public function benefits(Request $request, string $cardNumber): JsonResponse
    {
        // First validate the card and get member
        $validation = $this->eligibilityService->validateCard($cardNumber);

        if (!$validation['valid']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $validation['code'],
                    'message' => $validation['reason'],
                ],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        // Get full eligibility with all benefits
        $result = $this->eligibilityService->checkEligibility(
            cardNumber: $cardNumber,
            serviceCodes: [], // All benefits
        );

        if (!$result->eligible) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $result->ineligibleCode,
                    'message' => $result->ineligibleReason,
                ],
                'timestamp' => now()->toIso8601String(),
            ], 200);
        }

        // Attach member_id to request for logging
        if ($result->member) {
            $request->attributes->set('member_id', $result->member->id);
        }

        // Group benefits by category
        $grouped = [];
        foreach ($result->benefits as $benefit) {
            $category = $benefit->category;
            if (!isset($grouped[$category])) {
                $grouped[$category] = [
                    'category' => $category,
                    'category_name' => $benefit->categoryName,
                    'benefits' => [],
                ];
            }
            $grouped[$category]['benefits'][] = $benefit->toArray();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'member' => $result->member->toArray(),
                'policy' => $result->policy->toArray(),
                'benefit_categories' => array_values($grouped),
                'exclusions' => $result->exclusions,
                'waiting_periods' => $result->waitingPeriods,
                'total_benefits' => count($result->benefits),
            ],
            'processing_time_ms' => $result->processingTimeMs,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Estimate copay for proposed services
     *
     * POST /v1/provider/eligibility/estimate-copay
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function estimateCopay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'card_number' => 'required|string|max:50',
            'services' => 'required|array|min:1',
            'services.*.benefit_code' => 'nullable|string|max:30',
            'services.*.service_code' => 'nullable|string|max:30',
            'services.*.amount' => 'required|numeric|min:0',
            'services.*.description' => 'nullable|string|max:255',
        ]);

        $provider = $request->attributes->get('provider');

        // First check eligibility
        $eligibility = $this->eligibilityService->checkEligibility(
            cardNumber: $validated['card_number'],
            providerId: $provider?->id,
        );

        if (!$eligibility->eligible) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $eligibility->ineligibleCode,
                    'message' => $eligibility->ineligibleReason,
                ],
                'timestamp' => now()->toIso8601String(),
            ], 200);
        }

        // Attach member_id to request for logging
        if ($eligibility->member) {
            $request->attributes->set('member_id', $eligibility->member->id);
        }

        // Calculate copay estimates
        $estimate = $this->eligibilityService->estimateCopayForServices(
            cardNumber: $validated['card_number'],
            services: $validated['services'],
            providerId: $provider?->id,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'member' => $eligibility->member->toArray(),
                'estimate' => $estimate->toArray(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Validate member card number
     *
     * GET /v1/provider/eligibility/validate-card/{cardNumber}
     *
     * @param Request $request
     * @param string $cardNumber
     * @return JsonResponse
     */
    public function validateCard(Request $request, string $cardNumber): JsonResponse
    {
        $result = $this->eligibilityService->validateCard($cardNumber);

        return response()->json([
            'success' => $result['valid'],
            'data' => $result,
            'timestamp' => now()->toIso8601String(),
        ], $result['valid'] ? 200 : 200);
    }

    /**
     * Lookup benefit by code
     *
     * GET /v1/provider/lookup/benefit/{benefitCode}
     *
     * @param Request $request
     * @param string $benefitCode
     * @return JsonResponse
     */
    public function lookupBenefit(Request $request, string $benefitCode): JsonResponse
    {
        $benefit = \Modules\Medical\Models\Benefit::with('category')
            ->where('code', $benefitCode)
            ->where('is_active', true)
            ->first();

        if (!$benefit) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'BENEFIT_NOT_FOUND',
                    'message' => 'Benefit code not found',
                ],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $benefit->id,
                'code' => $benefit->code,
                'name' => $benefit->name,
                'short_name' => $benefit->short_name,
                'category' => $benefit->category?->code,
                'category_name' => $benefit->category?->name,
                'requires_preauth' => $benefit->requires_preauth,
                'requires_referral' => $benefit->requires_referral,
                'description' => $benefit->description,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Lookup ICD-10 code
     *
     * GET /v1/provider/lookup/icd10/{code}
     *
     * @param Request $request
     * @param string $code
     * @return JsonResponse
     */
    public function lookupIcd10(Request $request, string $code): JsonResponse
    {
        // This would typically query an ICD-10 database
        // For now, return a placeholder response
        return response()->json([
            'success' => true,
            'data' => [
                'code' => strtoupper($code),
                'description' => 'ICD-10 lookup not implemented',
                'category' => null,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Search ICD-10 codes by description
     *
     * GET /v1/provider/lookup/icd10?q=search_term
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchIcd10(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:100',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        // This would typically search an ICD-10 database
        // For now, return a placeholder response
        return response()->json([
            'success' => true,
            'data' => [
                'query' => $validated['q'],
                'results' => [],
                'total' => 0,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
