<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Scheme extends Model
{
    protected $table = 'med_schemes';
    protected $fillable = ['name', 'slug', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    // Auto-generate slug when saving
    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($scheme) => $scheme->slug = Str::slug($scheme->name));
        static::updating(fn ($scheme) => $scheme->slug = Str::slug($scheme->name));
    }
}