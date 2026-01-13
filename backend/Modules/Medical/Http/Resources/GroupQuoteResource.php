<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupQuoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'group_id' => $this->resource['group_id'],
            'group_name' => $this->resource['group_name'],

            'plan_info' => [
                'id' => $this->resource['plan_info']['id'],
                'name' => $this->resource['plan_info']['name'],
                'scheme_name' => $this->resource['plan_info']['scheme_name'],
                'rate_card_id' => $this->resource['plan_info']['rate_card_id'],
                'rate_card_name' => $this->resource['plan_info']['rate_card_name'],
                'premium_basis' => $this->resource['plan_info']['premium_basis'],
            ],

            'member_summary' => [
                'total' => $this->resource['member_summary']['total'],
                'principals' => $this->resource['member_summary']['principals'],
                'spouses' => $this->resource['member_summary']['spouses'],
                'children' => $this->resource['member_summary']['children'],
                'parents' => $this->resource['member_summary']['parents'],
                'other' => $this->resource['member_summary']['other'],
            ],

            'tier_info' => $this->when(
                !empty($this->resource['tier_info']),
                $this->resource['tier_info']
            ),

            'volume_discounts' => $this->resource['volume_discounts'] ?? [],

            'premium_breakdown' => [
                'base_premium' => $this->resource['premium_breakdown']['base_premium'],
                'addon_premium' => $this->resource['premium_breakdown']['addon_premium'],
                'subtotal' => $this->resource['premium_breakdown']['subtotal'],
                'tier_savings' => $this->resource['premium_breakdown']['tier_savings'],
                'volume_discounts' => $this->resource['premium_breakdown']['volume_discounts'],
                'other_discounts' => $this->resource['premium_breakdown']['other_discounts'],
                'total_discounts' => $this->resource['premium_breakdown']['total_discounts'],
                'total_premium' => $this->resource['premium_breakdown']['total_premium'],
                'per_member_average' => $this->resource['premium_breakdown']['per_member_average'],
            ],

            'member_breakdown' => $this->when(
                $request->boolean('include_member_breakdown'),
                $this->resource['member_breakdown']
            ),

            'addon_breakdown' => $this->when(
                !empty($this->resource['addon_breakdown']),
                $this->resource['addon_breakdown']
            ),

            'billing_frequency' => $this->resource['billing_frequency'],
            'currency' => $this->resource['currency'],
            'generated_at' => $this->resource['generated_at'],
            'valid_until' => $this->resource['valid_until'],
        ];
    }
}
