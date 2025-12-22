<?php
namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;

class RateCardEntry extends Model
{
    protected $table = 'med_rate_card_entries';
    public $timestamps = false; // Usually not needed for matrix entries

    protected $fillable = [
        'rate_card_id', 'min_age', 'max_age', 
        'gender', 'region_code', 'member_type', 'price'
    ];
}