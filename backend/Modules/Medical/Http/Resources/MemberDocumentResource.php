<?php

namespace Modules\Medical\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            
            'document_type' => $this->document_type,
            'document_type_label' => $this->document_type_label,
            'title' => $this->title,
            
            'file_path' => $this->file_path,
            'file_name' => $this->file_name,
            'file_extension' => $this->file_extension,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->file_size_formatted,
            
            'issue_date' => $this->issue_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'is_expired' => $this->is_expired,
            'is_expiring_soon' => $this->is_expiring_soon,
            'days_to_expiry' => $this->days_to_expiry,
            
            'is_verified' => $this->is_verified,
            'verified_by' => $this->verified_by,
            'verified_at' => $this->verified_at,
            
            'uploaded_by' => $this->uploaded_by,
            'is_active' => $this->is_active,
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}