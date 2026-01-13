<?php

namespace Modules\Medical\Services;

use Carbon\Carbon;

class ProRatingService
{
    /**
     * Calculate pro-rated premium for partial period
     *
     * @param float $annualPremium The full annual premium
     * @param Carbon $effectiveDate When coverage starts
     * @param Carbon $periodEnd When coverage ends (policy expiry)
     * @return float Pro-rated premium amount
     */
    public function calculateProRata(
        float $annualPremium,
        Carbon $effectiveDate,
        Carbon $periodEnd
    ): float {
        $totalDays = 365; // Standard year for insurance calculations
        $remainingDays = $this->calculateDaysRemaining($effectiveDate, $periodEnd);

        // Pro-rata formula: (Annual Premium / 365) × Days Remaining
        return round(($annualPremium / $totalDays) * $remainingDays, 2);
    }

    /**
     * Calculate days remaining in period
     *
     * @param Carbon $effectiveDate Start date
     * @param Carbon $periodEnd End date
     * @return int Number of days remaining
     */
    public function calculateDaysRemaining(
        Carbon $effectiveDate,
        Carbon $periodEnd
    ): int {
        // Add 1 to include both start and end dates
        return $effectiveDate->diffInDays($periodEnd) + 1;
    }

    /**
     * Calculate refund amount for member removal
     *
     * @param float $annualPremium The full annual premium paid
     * @param Carbon $removalDate When member is removed
     * @param Carbon $periodEnd Original policy end date
     * @return float Refund amount for unused period
     */
    public function calculateRefund(
        float $annualPremium,
        Carbon $removalDate,
        Carbon $periodEnd
    ): float {
        $totalDays = 365;
        $unusedDays = $this->calculateDaysRemaining($removalDate, $periodEnd);

        // Refund formula: (Annual Premium / 365) × Unused Days
        return round(($annualPremium / $totalDays) * $unusedDays, 2);
    }

    /**
     * Calculate premium adjustment for plan change
     *
     * @param float $oldAnnualPremium Current plan annual premium
     * @param float $newAnnualPremium New plan annual premium
     * @param Carbon $changeDate When plan changes
     * @param Carbon $periodEnd Policy end date
     * @return float Positive for additional charge, negative for refund
     */
    public function calculatePlanChangeAdjustment(
        float $oldAnnualPremium,
        float $newAnnualPremium,
        Carbon $changeDate,
        Carbon $periodEnd
    ): float {
        $remainingDays = $this->calculateDaysRemaining($changeDate, $periodEnd);
        $totalDays = 365;

        // Calculate pro-rata for remaining period on old and new plans
        $oldProRata = ($oldAnnualPremium / $totalDays) * $remainingDays;
        $newProRata = ($newAnnualPremium / $totalDays) * $remainingDays;

        // Difference = additional charge (positive) or refund (negative)
        return round($newProRata - $oldProRata, 2);
    }

    /**
     * Calculate monthly equivalent from annual premium
     *
     * @param float $annualPremium Annual premium amount
     * @return float Monthly premium amount
     */
    public function annualizeToMonthly(float $annualPremium): float
    {
        return round($annualPremium / 12, 2);
    }

    /**
     * Calculate annual from monthly premium
     *
     * @param float $monthlyPremium Monthly premium amount
     * @return float Annual premium amount
     */
    public function monthlyToAnnual(float $monthlyPremium): float
    {
        return round($monthlyPremium * 12, 2);
    }

    /**
     * Validate that effective date is within valid range
     *
     * @param Carbon $effectiveDate Proposed effective date
     * @param Carbon $policyStart Policy inception date
     * @param Carbon $policyEnd Policy expiry date
     * @param int $minimumDays Minimum days of coverage required
     * @return array ['valid' => bool, 'message' => string]
     */
    public function validateEffectiveDate(
        Carbon $effectiveDate,
        Carbon $policyStart,
        Carbon $policyEnd,
        int $minimumDays = 30
    ): array {
        $today = Carbon::today();

        // Check if effective date is in the past
        if ($effectiveDate->lt($today)) {
            return [
                'valid' => false,
                'message' => 'Effective date cannot be in the past'
            ];
        }

        // Check if effective date is after policy end
        if ($effectiveDate->gte($policyEnd)) {
            return [
                'valid' => false,
                'message' => 'Effective date must be before policy expiry date'
            ];
        }

        // Check if there are enough days remaining for minimum coverage
        $remainingDays = $this->calculateDaysRemaining($effectiveDate, $policyEnd);
        if ($remainingDays < $minimumDays) {
            return [
                'valid' => false,
                'message' => "Effective date must allow at least {$minimumDays} days of coverage"
            ];
        }

        return [
            'valid' => true,
            'message' => 'Effective date is valid'
        ];
    }
}
