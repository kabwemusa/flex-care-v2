<?php
namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Plan extends Model
{
    protected $table = 'med_plans';

    protected $fillable = [
        'scheme_id',
        'name',
        'code',
        'type'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($plan) {
            // Force override any value sent by the frontend
            $plan->code = self::generateUniqueCode($plan);
        });
    }

    private static function generateUniqueCode($plan): string
    {
        // Important: Use the full namespace or ensure it's imported correctly
        $scheme = \Modules\Medical\Models\Scheme::find($plan->scheme_id);
        
        // Clean prefix: Take first 3 letters of scheme, fallback to 'PLN'
        $prefix = $scheme ? Str::upper(Str::limit(preg_replace('/[^A-Z0-9]/i', '', $scheme->name), 3, '')) : 'PLN';

        // Clean tier: Take first 3 letters of plan name
        $tier = Str::upper(Str::limit(preg_replace('/[^A-Z0-9]/i', '', $plan->name), 3, ''));

        $baseCode = "{$prefix}-{$tier}";

        // Look for the highest sequence for this specific base code
        $latestCode = self::where('code', 'LIKE', "{$baseCode}-%")
            ->orderBy('code', 'desc')
            ->first();

        $sequence = 1;
        if ($latestCode) {
            $pieces = explode('-', $latestCode->code);
            $lastPiece = end($pieces);
            if (is_numeric($lastPiece)) {
                $sequence = (int)$lastPiece + 1;
            }
        }

        return "{$baseCode}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }
    /**
     * The parent Umbrella Scheme
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class, 'scheme_id');
    }

    /**
     * Features assigned to this plan (Gold, Silver, etc.)
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'med_feature_plan')
                    ->withPivot('limit_amount', 'limit_description');
    }
    public function addons(): BelongsToMany {
        return $this->belongsToMany(Addon::class, 'med_plan_addon')->withTimestamps();
    }
}