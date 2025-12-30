<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PolicyDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'policy_id' => $this->policy_id,
            
            'document_type' => $this->document_type,
            'document_type_label' => $this->document_type_label,
            'title' => $this->title,
            
            'file_path' => $this->file_path,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->file_size_formatted,
            
            'version' => $this->version,
            'issue_date' => $this->issue_date?->toDateString(),
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'is_valid' => $this->is_valid,
            
            'is_system_generated' => $this->is_system_generated,
            'uploaded_by' => $this->uploaded_by,
            'generated_by' => $this->generated_by,
            'is_active' => $this->is_active,
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}