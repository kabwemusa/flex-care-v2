<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\PolicyDocument;
use Modules\Medical\Http\Requests\PolicyRequest;
use Modules\Medical\Http\Requests\PolicyAddonRequest;
use Modules\Medical\Http\Resources\PolicyResource;
use Modules\Medical\Http\Resources\PolicyListResource;
use Modules\Medical\Http\Resources\PolicyAddonResource;
use Modules\Medical\Http\Resources\PolicyDocumentResource;
use Modules\Medical\Services\PolicyService;
use Modules\Medical\Services\PremiumCalculator;
use Modules\Medical\Constants\MedicalConstants;
use App\Traits\ApiResponse;
use Throwable;
use Exception;

class PolicyController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PolicyService $policyService,
        protected PremiumCalculator $premiumCalculator
    ) {}

    /**
     * List policies with filtering.
     * GET /v1/medical/policies
     */
    public function index(): JsonResponse
    {
        try {
            $query = Policy::query()
                ->with(['scheme:id,code,name', 'plan:id,code,name', 'group:id,code,name'])
                ->withCount('members');

            // Search
            if ($search = request('search')) {
                $query->search($search);
            }

            // Filters
            if ($status = request('status')) {
                $query->where('status', $status);
            }

            if ($policyType = request('policy_type')) {
                $query->where('policy_type', $policyType);
            }

            if ($schemeId = request('scheme_id')) {
                $query->where('scheme_id', $schemeId);
            }

            if ($planId = request('plan_id')) {
                $query->where('plan_id', $planId);
            }

            if ($groupId = request('group_id')) {
                $query->where('group_id', $groupId);
            }

            if (request('expiring_soon')) {
                $query->expiringWithin(30);
            }

            if (request('for_renewal')) {
                $query->forRenewal();
            }

            // Date range filters
            if ($from = request('inception_from')) {
                $query->where('inception_date', '>=', $from);
            }
            if ($to = request('inception_to')) {
                $query->where('inception_date', '<=', $to);
            }

            // Sorting
            $sortBy = request('sort_by', 'created_at');
            $sortOrder = request('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $policies = $query->paginate(request('per_page', 20));

            return $this->success(
                PolicyListResource::collection($policies),
                'Policies retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve policies: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new policy.
     * POST /v1/medical/policies
     */
    public function store(PolicyRequest $request): JsonResponse
    {
        try {
            $policy = DB::transaction(function () use ($request) {
                $data = $request->validated();
                
                // Set renewal date based on expiry
                if (!isset($data['renewal_date']) && isset($data['expiry_date'])) {
                    $data['renewal_date'] = $data['expiry_date'];
                }

                $policy = Policy::create($data);

                // Add selected addons
                if ($addons = $request->input('addons')) {
                    foreach ($addons as $addon) {
                        $policy->policyAddons()->create([
                            'addon_id' => $addon['addon_id'],
                            'addon_rate_id' => $addon['addon_rate_id'] ?? null,
                            'premium' => $addon['premium'] ?? 0,
                        ]);
                    }
                }

                // Apply promo code if provided
                if ($promoCode = $request->input('promo_code')) {
                    $this->policyService->applyPromoCode($policy, $promoCode);
                }

                // Calculate premiums
                $this->premiumCalculator->calculate($policy);

                return $policy;
            });

            $policy->load(['scheme', 'plan', 'group', 'policyAddons.addon']);

            return $this->success(
                new PolicyResource($policy),
                'Policy created',
                201
            );
        } catch (Throwable $e) {
            return $this->error('Failed to create policy: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Show policy details.
     * GET /v1/medical/policies/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $policy = Policy::with([
                'scheme',
                'plan.planBenefits.benefit',
                'rateCard',
                'group.primaryContact',
                'principalMember',
                'members' => fn($q) => $q->orderBy('member_type')->orderBy('created_at'),
                'policyAddons.addon',
                'documents' => fn($q) => $q->active()->latest(),
                'promoCode',
            ])
            ->withCount(['members', 'principals' => fn($q) => $q->where('member_type', 'principal')])
            ->findOrFail($id);

            return $this->success(
                new PolicyResource($policy),
                'Policy retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve policy details', 500);
        }
    }

    /**
     * Update policy.
     * PUT /v1/medical/policies/{id}
     */
    public function update(PolicyRequest $request, string $id): JsonResponse
    {
        try {
            $policy = DB::transaction(function () use ($request, $id) {
                $policy = Policy::findOrFail($id);

                // Prevent updates on certain statuses
                if (in_array($policy->status, [MedicalConstants::POLICY_STATUS_CANCELLED, MedicalConstants::POLICY_STATUS_EXPIRED])) {
                    throw new Exception('Cannot update cancelled or expired policies', 422);
                }

                $policy->update($request->validated());

                // Recalculate premiums if relevant fields changed
                if ($request->hasAny(['base_premium', 'addon_premium', 'loading_amount', 'discount_amount'])) {
                    $policy->calculatePremium();
                    $policy->save();
                }

                return $policy->fresh(['scheme', 'plan', 'group']);
            });

            return $this->success(
                new PolicyResource($policy),
                'Policy updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Delete policy (only drafts).
     * DELETE /v1/medical/policies/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $policy = Policy::findOrFail($id);

                if ($policy->status !== MedicalConstants::POLICY_STATUS_DRAFT) {
                    throw new Exception('Only draft policies can be deleted', 422);
                }

                $policy->delete();
            });

            return $this->success(null, 'Policy deleted');
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    // =========================================================================
    // STATUS MANAGEMENT
    // =========================================================================

    /**
     * Activate policy.
     * POST /v1/medical/policies/{id}/activate
     */
    public function activate(string $id): JsonResponse
    {
        try {
            $policy = DB::transaction(function () use ($id) {
                $policy = Policy::findOrFail($id);

                if (!$policy->canActivate()) {
                    throw new Exception('Policy cannot be activated. Check underwriting status and payment.', 422);
                }

                $policy->activate();

                // Activate all pending members
                $policy->members()->pending()->update([
                    'status' => MedicalConstants::MEMBER_STATUS_ACTIVE,
                    'status_changed_at' => now(),
                ]);

                return $policy->fresh();
            });

            return $this->success(
                new PolicyResource($policy),
                'Policy activated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Suspend policy.
     * POST /v1/medical/policies/{id}/suspend
     */
    public function suspend(string $id): JsonResponse
    {
        try {
            $policy = DB::transaction(function () use ($id) {
                $policy = Policy::findOrFail($id);

                if (!$policy->is_active) {
                    throw new Exception('Only active policies can be suspended', 422);
                }

                $policy->suspend(request('reason'));

                // Suspend all active members
                $policy->members()->active()->update([
                    'status' => MedicalConstants::MEMBER_STATUS_SUSPENDED,
                    'status_changed_at' => now(),
                    'status_reason' => 'Policy suspended',
                ]);

                return $policy->fresh();
            });

            return $this->success(
                new PolicyResource($policy),
                'Policy suspended'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Cancel policy.
     * POST /v1/medical/policies/{id}/cancel
     */
    public function cancel(string $id): JsonResponse
    {
        try {
            $policy = DB::transaction(function () use ($id) {
                $policy = Policy::findOrFail($id);

                if (in_array($policy->status, [MedicalConstants::POLICY_STATUS_CANCELLED, MedicalConstants::POLICY_STATUS_EXPIRED])) {
                    throw new Exception('Policy is already cancelled or expired', 422);
                }

                $reason = request('reason', 'Cancellation requested');
                $policy->cancel($reason);

                // Terminate all members
                $policy->members()
                    ->whereNotIn('status', [MedicalConstants::MEMBER_STATUS_TERMINATED, MedicalConstants::MEMBER_STATUS_DECEASED])
                    ->update([
                        'status' => MedicalConstants::MEMBER_STATUS_TERMINATED,
                        'status_changed_at' => now(),
                        'terminated_at' => now(),
                        'termination_reason' => 'policy_cancelled',
                        'cover_end_date' => now(),
                        'card_status' => MedicalConstants::CARD_STATUS_BLOCKED,
                    ]);

                return $policy->fresh();
            });

            return $this->success(
                new PolicyResource($policy),
                'Policy cancelled'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Reinstate suspended policy.
     * POST /v1/medical/policies/{id}/reinstate
     */
    public function reinstate(string $id): JsonResponse
    {
        try {
            $policy = DB::transaction(function () use ($id) {
                $policy = Policy::findOrFail($id);

                if ($policy->status !== MedicalConstants::POLICY_STATUS_SUSPENDED) {
                    throw new Exception('Only suspended policies can be reinstated', 422);
                }

                $policy->status = MedicalConstants::POLICY_STATUS_ACTIVE;
                $policy->save();

                // Reactivate suspended members
                $policy->members()
                    ->where('status', MedicalConstants::MEMBER_STATUS_SUSPENDED)
                    ->where('status_reason', 'Policy suspended')
                    ->update([
                        'status' => MedicalConstants::MEMBER_STATUS_ACTIVE,
                        'status_changed_at' => now(),
                        'status_reason' => null,
                    ]);

                return $policy->fresh();
            });

            return $this->success(
                new PolicyResource($policy),
                'Policy reinstated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    // =========================================================================
    // UNDERWRITING
    // =========================================================================

    /**
     * Approve policy underwriting.
     * POST /v1/medical/policies/{id}/approve
     */
    public function approve(string $id): JsonResponse
    {
        try {
            $policy = DB::transaction(function () use ($id) {
                $policy = Policy::findOrFail($id);

                if ($policy->underwriting_status !== MedicalConstants::UW_STATUS_PENDING) {
                    throw new Exception('Policy is not pending underwriting approval', 422);
                }

                $policy->approve(request('notes'));
                return $policy->fresh();
            });

            return $this->success(
                new PolicyResource($policy),
                'Policy approved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Decline policy underwriting.
     * POST /v1/medical/policies/{id}/decline
     */
    public function decline(string $id): JsonResponse
    {
        try {
            $policy = DB::transaction(function () use ($id) {
                $policy = Policy::findOrFail($id);

                $reason = request('reason');
                if (!$reason) {
                    throw new Exception('Decline reason is required', 422);
                }

                $policy->decline($reason);
                return $policy->fresh();
            });

            return $this->success(
                new PolicyResource($policy),
                'Policy declined'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Refer policy for review.
     * POST /v1/medical/policies/{id}/refer
     */
    public function refer(string $id): JsonResponse
    {
        try {
            $policy = DB::transaction(function () use ($id) {
                $policy = Policy::findOrFail($id);

                $reason = request('reason');
                if (!$reason) {
                    throw new Exception('Referral reason is required', 422);
                }

                $policy->refer($reason);
                return $policy->fresh();
            });

            return $this->success(
                new PolicyResource($policy),
                'Policy referred for review'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    // =========================================================================
    // RENEWAL
    // =========================================================================

    /**
     * Renew policy.
     * POST /v1/medical/policies/{id}/renew
     */
    public function renew(string $id): JsonResponse
    {
        try {
            $newPolicy = DB::transaction(function () use ($id) {
                $policy = Policy::findOrFail($id);

                if (!$policy->canBeRenewed()) {
                    throw new Exception('Policy cannot be renewed', 422);
                }

                return $this->policyService->renewPolicy($policy, request()->all());
            });

            return $this->success(
                new PolicyResource($newPolicy),
                'Policy renewed',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Get policies due for renewal.
     * GET /v1/medical/policies/for-renewal
     */
    public function forRenewal(): JsonResponse
    {
        try {
            $policies = Policy::forRenewal()
                ->with(['scheme:id,name', 'plan:id,name', 'group:id,name'])
                ->withCount('members')
                ->orderBy('expiry_date')
                ->paginate(request('per_page', 20));

            return $this->success(
                PolicyListResource::collection($policies)->response()->getData(true),
                'Policies for renewal retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve renewal list', 500);
        }
    }

    // =========================================================================
    // POLICY ADDONS
    // =========================================================================

    /**
     * Add addon to policy.
     * POST /v1/medical/policies/{id}/addons
     */
    public function addAddon(PolicyAddonRequest $request, string $id): JsonResponse
    {
        try {
            $addon = DB::transaction(function () use ($request, $id) {
                $policy = Policy::findOrFail($id);

                // Check if addon already exists
                if ($policy->policyAddons()->where('addon_id', $request->addon_id)->exists()) {
                    throw new Exception('Addon already added to this policy', 422);
                }

                $addon = $policy->policyAddons()->create($request->validated());

                // Recalculate premiums
                $this->premiumCalculator->calculate($policy);

                return $addon;
            });

            return $this->success(
                new PolicyAddonResource($addon->load('addon')),
                'Addon added',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Remove addon from policy.
     * DELETE /v1/medical/policies/{id}/addons/{addonId}
     */
    public function removeAddon(string $id, string $addonId): JsonResponse
    {
        try {
            DB::transaction(function () use ($id, $addonId) {
                $policy = Policy::findOrFail($id);
                
                $policyAddon = $policy->policyAddons()->findOrFail($addonId);
                $policyAddon->delete();

                // Recalculate premiums
                $this->premiumCalculator->calculate($policy);
            });

            return $this->success(null, 'Addon removed');
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy or addon not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to remove addon', 500);
        }
    }

    // =========================================================================
    // DOCUMENTS
    // =========================================================================

    /**
     * List policy documents.
     * GET /v1/medical/policies/{id}/documents
     */
    public function documents(string $id): JsonResponse
    {
        try {
            $documents = PolicyDocument::where('policy_id', $id)
                ->active()
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->success(
                PolicyDocumentResource::collection($documents),
                'Documents retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve documents', 500);
        }
    }

    /**
     * Upload policy document.
     * POST /v1/medical/policies/{id}/documents
     */
    public function uploadDocument(string $id): JsonResponse
    {
        try {
            $document = DB::transaction(function () use ($id) {
                $policy = Policy::findOrFail($id);

                request()->validate([
                    'document_type' => 'required|string',
                    'title' => 'required|string|max:255',
                    'file' => 'required|file|max:10240', // 10MB
                ]);

                $file = request()->file('file');
                $path = $file->store("policies/{$policy->id}/documents", 'private');

                return $policy->documents()->create([
                    'document_type' => request('document_type'),
                    'title' => request('title'),
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    // 'uploaded_by' => auth()->id(),
                ]);
            });

            return $this->success(
                new PolicyDocumentResource($document),
                'Document uploaded',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error('File upload failed: ' . $e->getMessage(), $code);
        }
    }

    // =========================================================================
    // UTILITIES
    // =========================================================================

    /**
     * Calculate premium for policy.
     * POST /v1/medical/policies/{id}/calculate-premium
     */
    public function calculatePremium(string $id): JsonResponse
    {
        try {
            $policy = Policy::with(['members', 'policyAddons.addon'])->findOrFail($id);

            $breakdown = $this->premiumCalculator->calculateWithBreakdown($policy);

            return $this->success($breakdown, 'Premium calculated');
        } catch (ModelNotFoundException $e) {
            return $this->error('Policy not found', 404);
        } catch (Throwable $e) {
            return $this->error('Calculation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get policy statistics.
     * GET /v1/medical/policies/stats
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total' => Policy::count(),
                'draft' => Policy::draft()->count(),
                'active' => Policy::active()->count(),
                'suspended' => Policy::where('status', MedicalConstants::POLICY_STATUS_SUSPENDED)->count(),
                'expiring_soon' => Policy::expiringWithin(30)->count(),
                'pending_underwriting' => Policy::where('underwriting_status', MedicalConstants::UW_STATUS_PENDING)->count(),
                'total_premium' => Policy::active()->sum('gross_premium'),
                'total_members' => Policy::active()->sum('member_count'),
            ];

            return $this->success($stats, 'Statistics retrieved');
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve statistics', 500);
        }
    }
}