<?php

namespace Modules\Medical\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Str;

abstract class BaseModel extends Model
{
    

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model) {
            // Generate UUID if not set
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }

            // Generate code if model has code attribute and it's empty
            if ($model->shouldGenerateCode() && empty($model->code)) {
                $model->code = $model->generateCode();
            }
        });
    }

    /**
     * Determine if the model should auto-generate a code.
     */
    protected function shouldGenerateCode(): bool
    {
        return in_array('code', $this->fillable) && !empty($this->getCodePrefix());
    }

    /**
     * Get the prefix for code generation.
     * Override in child classes to set custom prefix.
     */
    protected function getCodePrefix(): string
    {
        return '';
    }

    /**
     * Generate a unique code for the model.
     */
    protected function generateCode(): string
    {
        $prefix = $this->getCodePrefix();
        $year = date('Y');
        $random = strtoupper(Str::random(6));
        
        return "{$prefix}-{$year}-{$random}";
    }

    /**
     * Scope to filter active records.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter inactive records.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope to filter by effective date range.
     */
    public function scopeEffective($query, $date = null)
    {
        $date = $date ?? now()->toDateString();

        return $query->where('effective_from', '<=', $date)
                     ->where(function ($q) use ($date) {
                         $q->whereNull('effective_to')
                           ->orWhere('effective_to', '>=', $date);
                     });
    }
}