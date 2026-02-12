<?php

namespace Modules\Medical\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Medical\Models\Plan;
use Modules\Medical\Models\PlanAddon;
use Modules\Medical\Services\ApplicationService;
use Throwable;

class PublicApplicationController
{
    use ApiResponse;

    public function __construct(
        private ApplicationService $applicationService
    ) {}

    /**
     * Submit a new application from the public website.
     * POST /v1/medical/public/applications
     *
     * Accepts a simplified payload: plan_id, members with personal details,
     * addon IDs, and contact information. The scheme and rate card are
     * resolved automatically from the selected plan.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id'                       => 'required|uuid|exists:med_plans,id',
            'members'                       => 'required|array|min:1',
            'members.*.member_type'         => 'required|in:principal,spouse,child,parent',
            'members.*.first_name'          => 'required|string|max:100',
            'members.*.last_name'           => 'required|string|max:100',
            'members.*.date_of_birth'       => 'required|date|before:today',
            'members.*.gender'              => 'nullable|in:M,F',
            'members.*.national_id'         => 'nullable|string|max:50',
            'members.*.email'               => 'nullable|email|max:255',
            'members.*.phone'               => 'nullable|string|max:50',
            'addon_ids'                     => 'nullable|array',
            'addon_ids.*'                   => 'uuid|exists:med_addons,id',
            'contact_name'                  => 'nullable|string|max:255',
            'contact_email'                 => 'required|email|max:255',
            'contact_phone'                 => 'nullable|string|max:50',
            'proposed_start_date'           => 'nullable|date|after_or_equal:today',
            'billing_frequency'             => 'nullable|in:monthly,quarterly,semi_annual,annual',
        ]);

        try {
            $plan        = Plan::findOrFail($validated['plan_id']);
            $rateCard    = $plan->activeRateCard;

            // Auto-inject mandatory addons for this plan
            $mandatoryAddonIds = PlanAddon::where('plan_id', $plan->id)
                ->where('is_active', true)
                ->mandatory()
                ->pluck('addon_id')
                ->toArray();

            $userAddonIds = $validated['addon_ids'] ?? [];
            $allAddonIds = array_values(array_unique(array_merge($userAddonIds, $mandatoryAddonIds)));

            $application = DB::transaction(function () use ($validated, $plan, $rateCard, $allAddonIds) {
                return $this->applicationService->createApplication([
                    'policy_type'         => $plan->plan_type,
                    'scheme_id'           => $plan->scheme_id,
                    'plan_id'             => $plan->id,
                    'rate_card_id'        => $rateCard?->id,
                    'contact_name'        => $validated['contact_name']
                                              ?? $validated['members'][0]['first_name'] . ' ' . $validated['members'][0]['last_name'],
                    'contact_email'       => $validated['contact_email'],
                    'contact_phone'       => $validated['contact_phone'] ?? null,
                    'proposed_start_date' => $validated['proposed_start_date'] ?? now()->addDays(30)->format('Y-m-d'),
                    'billing_frequency'   => $validated['billing_frequency'] ?? 'monthly',
                    'source'              => 'online',
                    'members'             => $validated['members'],
                    'addons'              => array_map(
                        fn (string $id) => ['addon_id' => $id],
                        $allAddonIds
                    ),
                ]);
            });

            return $this->success([
                'id'                  => $application->id,
                'application_number'  => $application->application_number,
                'status'              => $application->status,
                'plan'                => ['id' => $plan->id, 'name' => $plan->name],
                'contact_email'       => $application->contact_email,
                'proposed_start_date' => $application->proposed_start_date,
                'member_count'        => $application->member_count,
                'total_premium'       => $application->total_premium,
            ], 'Application submitted successfully', 201);

        } catch (Throwable $e) {
            return $this->error('Failed to submit application: ' . $e->getMessage(), 500);
        }
    }
}
