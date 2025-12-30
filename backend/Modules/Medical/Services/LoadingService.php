<?php

namespace Modules\Medical\Services;

use Modules\Medical\Models\LoadingRule;
use Modules\Medical\Constants\MedicalConstants;
use Carbon\Carbon;

class LoadingService
{
    /**
     * Find loading rule by ICD code or condition name.
     */
    public function findLoadingRule(string $identifier): ?LoadingRule
    {
        return LoadingRule::active()
            ->where(function ($q) use ($identifier) {
                $q->where('icd10_code', $identifier)
                  ->orWhere('condition_name', 'LIKE', "%{$identifier}%")
                  ->orWhereJsonContains('related_icd_codes', $identifier);
            })
            ->first();
    }

    /**
     * Calculate loading for a condition.
     */
    public function calculateLoading(LoadingRule $rule, float $premium): array
    {
        if ($rule->is_exclusion_type) {
            return [
                'type' => 'exclusion',
                'loading_amount' => 0,
                'exclusion_terms' => $rule->exclusion_terms,
            ];
        }

        $loading = $rule->calculateLoading($premium);

        return [
            'type' => 'loading',
            'loading_amount' => $loading,
            'loading_value' => $rule->loading_value,
            'loading_type' => $rule->loading_type,
            'duration' => $rule->duration_type,
            'duration_months' => $rule->duration_months,
        ];
    }

    /**
     * Calculate total loadings for multiple conditions.
     */
    public function calculateLoadings(float $premium, array $conditions, ?Carbon $coverStartDate = null): array
    {
        $appliedLoadings = [];
        $totalLoading = 0;

        foreach ($conditions as $condition) {
            $identifier = is_array($condition) 
                ? ($condition['icd_code'] ?? $condition['name'] ?? null)
                : $condition;

            if (!$identifier) {
                continue;
            }

            $rule = $this->findLoadingRule($identifier);

            if (!$rule) {
                continue;
            }

            // Check if time-limited loading has expired
            if ($coverStartDate && $rule->hasLoadingExpired($coverStartDate)) {
                continue;
            }

            $result = $this->calculateLoading($rule, $premium);

            if ($result['type'] === 'loading' && $result['loading_amount'] > 0) {
                $totalLoading += $result['loading_amount'];
                $appliedLoadings[] = [
                    'rule_id' => $rule->id,
                    'condition' => $rule->condition_name,
                    'loading_amount' => $result['loading_amount'],
                    'loading_type' => $result['loading_type'],
                    'duration' => $result['duration'],
                ];
            }

            if ($result['type'] === 'exclusion') {
                $appliedLoadings[] = [
                    'rule_id' => $rule->id,
                    'condition' => $rule->condition_name,
                    'type' => 'exclusion',
                    'exclusion_terms' => $result['exclusion_terms'],
                ];
            }
        }

        return [
            'original_premium' => $premium,
            'loadings' => $appliedLoadings,
            'total_loading' => round($totalLoading, 2),
            'final_premium' => round($premium + $totalLoading, 2),
        ];
    }

    /**
     * Get available loading options for underwriting.
     */
    public function getLoadingOptions(string $identifier): array
    {
        $rule = $this->findLoadingRule($identifier);

        if (!$rule) {
            return [
                'found' => false,
                'message' => 'No standard loading rule found',
            ];
        }

        $options = [];

        // Loading option
        if (!$rule->is_exclusion_type) {
            $options[] = [
                'type' => 'loading',
                'value' => $rule->loading_value,
                'value_type' => $rule->loading_type,
                'duration' => $rule->duration_type,
            ];
        }

        // Exclusion option
        if ($rule->exclusion_available) {
            $options[] = [
                'type' => 'exclusion',
                'terms' => $rule->exclusion_terms,
            ];
        }

        return [
            'found' => true,
            'condition' => $rule->condition_name,
            'icd_code' => $rule->icd10_code,
            'category' => $rule->condition_category,
            'options' => $options,
            'required_documents' => $rule->required_documents,
        ];
    }

    /**
     * Search loading rules.
     */
    public function searchRules(string $term, int $limit = 20): array
    {
        return LoadingRule::active()
            ->where(function ($q) use ($term) {
                $q->where('condition_name', 'LIKE', "%{$term}%")
                  ->orWhere('icd10_code', 'LIKE', "%{$term}%");
            })
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'code' => $r->code,
                'condition' => $r->condition_name,
                'icd_code' => $r->icd10_code,
                'loading_value' => $r->formatted_loading_value,
            ])
            ->all();
    }
}