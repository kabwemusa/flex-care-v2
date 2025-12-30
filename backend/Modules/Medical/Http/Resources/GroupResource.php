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
            'primary_contact' => new GroupContactResource(
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
            // STATS
            // =====================================================
            'stats' => [
                'total_policies' => $this->policies_count ?? 0,
                'active_policies' => $this->active_policies_count ?? 0,
            ],

            // =====================================================
            // META
            // =====================================================
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
