<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CensusImportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => $this->resource['success'],
            'summary' => [
                'total_rows' => $this->resource['summary']['total_rows'],
                'valid_rows' => $this->resource['summary']['valid_rows'],
                'invalid_rows' => $this->resource['summary']['invalid_rows'],
                'member_type_breakdown' => $this->resource['summary']['member_type_breakdown'],
                'has_errors' => $this->resource['summary']['has_errors'],
            ],
            'data' => $this->when(
                $request->boolean('include_data'),
                $this->resource['data']
            ),
            'errors' => $this->when(
                !empty($this->resource['errors']),
                $this->resource['errors']
            ),
        ];
    }
}
