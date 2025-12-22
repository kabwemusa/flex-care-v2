<?php
namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Addon extends Model
{
    protected $table = 'med_addons';
    protected $fillable = ['name',  'description', 'price', 'is_mandatory'];

    // protected static function boot()
    // {
    //     parent::boot();
    //     static::creating(function ($addon) {
    //         $addon->code = self::generateAddonCode($addon);
    //     });
    // }

    // private static function generateAddonCode($addon): string
    // {
    //     $prefix = Str::upper(Str::limit(preg_replace('/[^A-Z0-9]/i', '', $addon->name), 4, ''));
    //     $base = "ADD-{$prefix}";
        
    //     $latest = self::where('code', 'LIKE', "{$base}%")->orderBy('code', 'desc')->first();
    //     $sequence = $latest ? ((int)Str::afterLast($latest->code, '-') + 1) : 1;

    //     return "{$base}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    // }
}