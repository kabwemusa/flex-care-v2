<?php
namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    protected $table = 'med_features';
    protected $fillable = ['name', 'category', 'code'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($feature) {
            $feature->code = self::generateUniqueFeatureCode($feature);
        });
    }

    private static function generateUniqueFeatureCode($feature): string
    {
        // 1. Get Category Prefix (e.g., "Clinical" -> "CLIN")
        $prefix = Str::upper(Str::limit(preg_replace('/[^A-Z0-9]/i', '', $feature->category), 4, ''));
        
        // 2. Short name identifier (e.g., "Dental Care" -> "DENT")
        $shortName = Str::upper(Str::limit(preg_replace('/[^A-Z0-9]/i', '', $feature->name), 4, ''));

        $base = "F-{$prefix}-{$shortName}";

        // 3. Sequence check
        $latest = self::where('code', 'LIKE', "{$base}%")->orderBy('code', 'desc')->first();
        $sequence = 1;

        if ($latest) {
            $parts = explode('-', $latest->code);
            $lastPart = end($parts);
            $sequence = is_numeric($lastPart) ? (int)$lastPart + 1 : 1;
        }

        return "{$base}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    // Modules/Medical/Models/Feature.php

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(
            Plan::class, 
            'med_feature_plan', 
            'feature_id', 
            'plan_id'
        )->withPivot('limit_amount', 'limit_description');
    }
}