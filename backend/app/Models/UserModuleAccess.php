<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class UserModuleAccess extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasUuids;

    protected $table = 'user_module_access';

    protected $fillable = [
        'user_id',
        'module_code',
        'is_active',
        'granted_at',
        'granted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'granted_at' => 'datetime',
        ];
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForModule($query, string $moduleCode)
    {
        return $query->where('module_code', $moduleCode);
    }
}
