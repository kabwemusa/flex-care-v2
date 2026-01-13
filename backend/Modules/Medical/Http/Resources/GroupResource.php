<?php
namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // =====================================================
            // CORE
            // =====================================================
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'trading_name' => $this->trading_name,
            'status' => $this->status,
            'status_label' => $this->status_label,

            // =====================================================
            // COMPLIANCE / BUSINESS INFO
            // =====================================================
            'compliance' => [
                'registration_number' => $this->registration_number,
                'tax_number' => $this->tax_number,
                'industry' => $this->industry,
                'company_size' => $this->company_size,
                'employee_count' => $this->employee_count,
            ],

            // =====================================================
            // PRIMARY CONTACT (ALIGNED)
            // =====================================================
            'primary_contact' =>  GroupContactResource::make(
                $this->whenLoaded('primaryContact')
            ),

            // =====================================================
            // LOCATION
            // =====================================================
            'location' => [
                'physical_address' => $this->physical_address,
                'city' => $this->city,
                'province' => $this->province,
            ],

            // =====================================================
            // COMMUNICATION
            // =====================================================
            'communication' => [
                'email' => $this->email,
                'phone' => $this->phone,
                'website' => $this->website,
            ],

            // =====================================================
            // POLICIES (Drawer / Detail View)
            // =====================================================
            'policies' => PolicyResource::collection(
                $this->whenLoaded('policies')
            ),

            // =====================================================
            // APPLICATIONS (Prospect Members)
            // =====================================================
            'applications' => ApplicationResource::collection(
                $this->whenLoaded('applications')
            ),

            // =====================================================
            // STATS
            // =====================================================
            'stats' => [
                'total_policies' => $this->policies_count ?? 0,
                'active_policies' => $this->active_policies_count ?? 0,
                'total_applications' => $this->applications_count ?? 0,
                'policies_by_plan' => $this->getPoliciesByPlanStats(),
                'applications_by_plan' => $this->getApplicationsByPlanStats(),
            ],

            // =====================================================
            // META
            // =====================================================
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get policy count grouped by plan
     */
    private function getPoliciesByPlanStats(): array
    {
        if (!$this->relationLoaded('policies')) {
            return [];
        }

        $stats = [];
        foreach ($this->policies as $policy) {
            if ($policy->relationLoaded('plan') && $policy->plan) {
                $planKey = $policy->plan->id;

                if (!isset($stats[$planKey])) {
                    $stats[$planKey] = [
                        'plan_id' => $policy->plan->id,
                        'plan_code' => $policy->plan->code,
                        'plan_name' => $policy->plan->name,
                        'count' => 0,
                    ];
                }

                $stats[$planKey]['count']++;
            }
        }

        return array_values($stats);
    }

    /**
     * Get application count grouped by plan
     */
    private function getApplicationsByPlanStats(): array
    {
        if (!$this->relationLoaded('applications')) {
            return [];
        }

        $stats = [];
        foreach ($this->applications as $application) {
            if ($application->relationLoaded('plan') && $application->plan) {
                $planKey = $application->plan->id;

                if (!isset($stats[$planKey])) {
                    $stats[$planKey] = [
                        'plan_id' => $application->plan->id,
                        'plan_code' => $application->plan->code,
                        'plan_name' => $application->plan->name,
                        'count' => 0,
                    ];
                }

                $stats[$planKey]['count']++;
            }
        }

        return array_values($stats);
    }
}
