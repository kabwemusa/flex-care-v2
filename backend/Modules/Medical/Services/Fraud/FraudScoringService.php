<?php

namespace Modules\Medical\Services\Fraud;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Medical\Models\Claim;
use Modules\Medical\Models\Member;
use Modules\Medical\Constants\MedicalConstants;

/**
 * Fraud Scoring Service
 *
 * Calculates fraud risk scores based on multiple factors:
 * - Member behavior patterns
 * - Provider relationships
 * - Claim characteristics
 * - Temporal patterns
 */
class FraudScoringService
{
    /**
     * Score weights for different risk categories
     */
    private const CATEGORY_WEIGHTS = [
        'member_risk' => 0.25,      // Max 25 points
        'provider_risk' => 0.20,    // Max 20 points
        'claim_risk' => 0.25,       // Max 25 points
        'pattern_risk' => 0.20,     // Max 20 points
        'temporal_risk' => 0.10,    // Max 10 points
    ];

    /**
     * Calculate quick score for real-time checks
     */
    public function calculateQuickScore(array $ruleResults): int
    {
        $score = 0;

        foreach ($ruleResults as $result) {
            if ($result['triggered']) {
                $score += $result['points'];
            }
        }

        return min(100, $score);
    }

    /**
     * Calculate final comprehensive score
     */
    public function calculateFinalScore(array $factors): int
    {
        $totalScore = 0;

        // Sum up category scores
        foreach (['member_risk', 'provider_risk', 'claim_risk', 'pattern_risk', 'temporal_risk'] as $category) {
            if (isset($factors[$category]['score'])) {
                $totalScore += $factors[$category]['score'];
            }
        }

        // Add rule-based points
        if (!empty($factors['rule_results'])) {
            foreach ($factors['rule_results'] as $result) {
                if ($result['triggered']) {
                    $totalScore += $result['points'];
                }
            }
        }

        return min(100, $totalScore);
    }

    /**
     * Analyze member risk factors
     */
    public function analyzeMemberRisk(Claim $claim): array
    {
        $member = $claim->member;
        $score = 0;
        $flags = [];
        $details = [];

        if (!$member) {
            return ['score' => 0, 'flags' => [], 'details' => []];
        }

        // Check previous fraud flags
        $previousFlags = Claim::where('member_id', $member->id)
            ->where('id', '!=', $claim->id)
            ->where('is_flagged', true)
            ->count();

        if ($previousFlags > 0) {
            $score += min(10, $previousFlags * 3);
            $flags[] = 'PREVIOUS_FLAGS';
            $details[] = "Previous flagged claims: {$previousFlags}";
        }

        // Check claim frequency trend
        $last30Days = Claim::where('member_id', $member->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $previous30Days = Claim::where('member_id', $member->id)
            ->where('created_at', '>=', now()->subDays(60))
            ->where('created_at', '<', now()->subDays(30))
            ->count();

        if ($previous30Days > 0 && $last30Days > ($previous30Days * 2)) {
            $score += 5;
            $flags[] = 'FREQUENCY_SPIKE';
            $details[] = "Claim frequency increased from {$previous30Days} to {$last30Days}";
        }

        // Check rejection rate
        $totalClaims = Claim::where('member_id', $member->id)->count();
        $rejectedClaims = Claim::where('member_id', $member->id)
            ->where('status', MedicalConstants::CLAIM_STATUS_REJECTED)
            ->count();

        if ($totalClaims >= 5 && ($rejectedClaims / $totalClaims) > 0.3) {
            $score += 5;
            $flags[] = 'HIGH_REJECTION_RATE';
            $details[] = "Rejection rate: " . round(($rejectedClaims / $totalClaims) * 100) . "%";
        }

        // New member with high claims
        $memberTenureDays = $member->cover_start_date
            ? $member->cover_start_date->diffInDays(now())
            : 365;

        if ($memberTenureDays < 60 && $claim->claimed_amount > 5000) {
            $score += 5;
            $flags[] = 'NEW_MEMBER_HIGH_CLAIM';
            $details[] = "New member ({$memberTenureDays} days) with high claim";
        }

        return [
            'score' => min(25, $score),
            'flags' => $flags,
            'details' => $details,
        ];
    }

    /**
     * Analyze provider risk factors
     */
    public function analyzeProviderRisk(Claim $claim): array
    {
        $score = 0;
        $flags = [];
        $details = [];

        if (!$claim->provider_id_fk) {
            return ['score' => 0, 'flags' => [], 'details' => []];
        }

        $provider = $claim->provider;
        if (!$provider) {
            return ['score' => 0, 'flags' => [], 'details' => []];
        }

        // Check provider fraud level
        if ($provider->fraud_risk_level === 'high') {
            $score += 10;
            $flags[] = 'HIGH_RISK_PROVIDER';
            $details[] = "Provider is flagged as high risk";
        } elseif ($provider->fraud_risk_level === 'medium') {
            $score += 5;
            $flags[] = 'MEDIUM_RISK_PROVIDER';
        }

        // Check provider rejection rate
        if (($provider->rejection_rate ?? 0) > 0.25) {
            $score += 5;
            $flags[] = 'PROVIDER_HIGH_REJECTIONS';
            $details[] = "Provider rejection rate: " . round($provider->rejection_rate * 100) . "%";
        }

        // Check if first claim from this provider for this member
        $previousFromProvider = Claim::where('member_id', $claim->member_id)
            ->where('provider_id_fk', $provider->id)
            ->where('id', '!=', $claim->id)
            ->count();

        if ($previousFromProvider === 0 && $claim->claimed_amount > 3000) {
            $score += 5;
            $flags[] = 'NEW_PROVIDER_HIGH_CLAIM';
            $details[] = "First claim from this provider is high value";
        }

        return [
            'score' => min(20, $score),
            'flags' => $flags,
            'details' => $details,
        ];
    }

    /**
     * Analyze claim characteristics
     */
    public function analyzeClaimCharacteristics(Claim $claim): array
    {
        $score = 0;
        $flags = [];
        $details = [];

        // Amount vs member average
        $avgClaim = Claim::where('member_id', $claim->member_id)
            ->where('status', MedicalConstants::CLAIM_STATUS_APPROVED)
            ->where('id', '!=', $claim->id)
            ->avg('claimed_amount');

        if ($avgClaim && $claim->claimed_amount > ($avgClaim * 3)) {
            $score += 10;
            $flags[] = 'AMOUNT_3X_AVERAGE';
            $details[] = "Amount is 3x+ member average (avg: " . number_format($avgClaim, 2) . ")";
        } elseif ($avgClaim && $claim->claimed_amount > ($avgClaim * 2)) {
            $score += 5;
            $flags[] = 'AMOUNT_2X_AVERAGE';
        }

        // Round amount check
        if ($claim->claimed_amount >= 1000 && $claim->claimed_amount == floor($claim->claimed_amount / 1000) * 1000) {
            $score += 3;
            $flags[] = 'ROUND_AMOUNT';
            $details[] = "Suspiciously round amount: " . number_format($claim->claimed_amount, 2);
        }

        // Multiple services on same day
        $lines = $claim->lines;
        if ($lines && $lines->count() > 5) {
            $score += 5;
            $flags[] = 'MANY_SERVICES';
            $details[] = "Many services ({$lines->count()}) on single claim";
        }

        // All lines same amount (potential duplication)
        if ($lines && $lines->count() >= 3) {
            $uniqueAmounts = $lines->pluck('claimed_amount')->unique()->count();
            if ($uniqueAmounts === 1) {
                $score += 7;
                $flags[] = 'IDENTICAL_LINE_AMOUNTS';
                $details[] = "All {$lines->count()} lines have identical amounts";
            }
        }

        return [
            'score' => min(25, $score),
            'flags' => $flags,
            'details' => $details,
        ];
    }

    /**
     * Analyze usage patterns
     */
    public function analyzePatterns(Claim $claim): array
    {
        $score = 0;
        $flags = [];
        $details = [];

        $member = $claim->member;
        if (!$member) {
            return ['score' => 0, 'flags' => [], 'details' => []];
        }

        // Check for benefit exhaustion pattern
        $recentBenefitUsage = DB::table('med_member_benefit_utilization')
            ->where('member_id', $member->id)
            ->where('utilization_percentage', '>=', 80)
            ->count();

        if ($recentBenefitUsage >= 3) {
            $score += 10;
            $flags[] = 'RAPID_BENEFIT_EXHAUSTION';
            $details[] = "{$recentBenefitUsage} benefits near exhaustion";
        }

        // Check same diagnosis pattern
        if ($claim->primary_icd_code) {
            $sameDiagnosis = Claim::where('member_id', $member->id)
                ->where('primary_icd_code', $claim->primary_icd_code)
                ->where('created_at', '>=', now()->subDays(90))
                ->where('id', '!=', $claim->id)
                ->count();

            if ($sameDiagnosis >= 5) {
                $score += 5;
                $flags[] = 'REPEATED_DIAGNOSIS';
                $details[] = "Same diagnosis ({$claim->primary_icd_code}) claimed {$sameDiagnosis}+ times in 90 days";
            }
        }

        // Check for provider hopping
        $uniqueProviders = Claim::where('member_id', $member->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->distinct('provider_id_fk')
            ->count('provider_id_fk');

        if ($uniqueProviders >= 5) {
            $score += 5;
            $flags[] = 'PROVIDER_HOPPING';
            $details[] = "{$uniqueProviders} different providers in 30 days";
        }

        return [
            'score' => min(20, $score),
            'flags' => $flags,
            'details' => $details,
        ];
    }

    /**
     * Analyze temporal factors
     */
    public function analyzeTemporalFactors(Claim $claim): array
    {
        $score = 0;
        $flags = [];
        $details = [];

        // Weekend claim for hospital services
        if ($claim->claim_type === 'in_patient' && $claim->service_date) {
            $dayOfWeek = $claim->service_date->dayOfWeek;
            if ($dayOfWeek === 0 || $dayOfWeek === 6) {
                $score += 3;
                $flags[] = 'WEEKEND_HOSPITAL_CLAIM';
            }
        }

        // Month-end claims
        if ($claim->service_date && $claim->service_date->day >= 28) {
            $score += 2;
            $flags[] = 'MONTH_END_CLAIM';
        }

        // Time since last claim
        $lastClaim = Claim::where('member_id', $claim->member_id)
            ->where('id', '!=', $claim->id)
            ->latest('service_date')
            ->first();

        if ($lastClaim && $lastClaim->service_date && $claim->service_date) {
            $daysDiff = $lastClaim->service_date->diffInDays($claim->service_date);
            if ($daysDiff <= 1) {
                $score += 5;
                $flags[] = 'CONSECUTIVE_DAY_CLAIMS';
                $details[] = "Claims on consecutive days";
            }
        }

        return [
            'score' => min(10, $score),
            'flags' => $flags,
            'details' => $details,
        ];
    }

    /**
     * Get member risk summary
     */
    public function getMemberRiskSummary(string $memberId): array
    {
        $member = DB::table('med_members')->find($memberId);
        if (!$member) {
            return ['found' => false];
        }

        $totalClaims = Claim::where('member_id', $memberId)->count();
        $flaggedClaims = Claim::where('member_id', $memberId)->where('is_flagged', true)->count();
        $rejectedClaims = Claim::where('member_id', $memberId)
            ->where('status', MedicalConstants::CLAIM_STATUS_REJECTED)
            ->count();

        $recentClaims = Claim::where('member_id', $memberId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $avgClaimAmount = Claim::where('member_id', $memberId)->avg('claimed_amount') ?? 0;

        return [
            'found' => true,
            'member_id' => $memberId,
            'fraud_risk_score' => $member->fraud_risk_score ?? 0,
            'fraud_risk_level' => $member->fraud_risk_level ?? 'low',
            'total_claims' => $totalClaims,
            'flagged_claims' => $flaggedClaims,
            'rejected_claims' => $rejectedClaims,
            'rejection_rate' => $totalClaims > 0 ? round($rejectedClaims / $totalClaims, 2) : 0,
            'recent_claims_30d' => $recentClaims,
            'avg_claim_amount' => round($avgClaimAmount, 2),
        ];
    }

    /**
     * Get provider risk summary
     */
    public function getProviderRiskSummary(string $providerId): array
    {
        $provider = DB::table('med_providers')->find($providerId);
        if (!$provider) {
            return ['found' => false];
        }

        $totalClaims = Claim::where('provider_id_fk', $providerId)->count();
        $rejectedClaims = Claim::where('provider_id_fk', $providerId)
            ->where('status', MedicalConstants::CLAIM_STATUS_REJECTED)
            ->count();

        $uniqueMembers = Claim::where('provider_id_fk', $providerId)
            ->distinct('member_id')
            ->count('member_id');

        $avgClaimAmount = Claim::where('provider_id_fk', $providerId)->avg('claimed_amount') ?? 0;

        $fraudAlerts = DB::table('med_fraud_alerts')
            ->where('provider_id', $providerId)
            ->where('status', 'open')
            ->count();

        return [
            'found' => true,
            'provider_id' => $providerId,
            'provider_name' => $provider->name,
            'fraud_risk_score' => $provider->fraud_risk_score ?? 0,
            'fraud_risk_level' => $provider->fraud_risk_level ?? 'low',
            'total_claims' => $totalClaims,
            'rejected_claims' => $rejectedClaims,
            'rejection_rate' => $totalClaims > 0 ? round($rejectedClaims / $totalClaims, 2) : 0,
            'unique_members' => $uniqueMembers,
            'avg_claim_amount' => round($avgClaimAmount, 2),
            'open_fraud_alerts' => $fraudAlerts,
        ];
    }

    /**
     * Store pattern data for ML training
     */
    public function storePatternData(Claim $claim, array $factors): void
    {
        DB::table('med_fraud_patterns')->insert([
            'id' => Str::uuid(),
            'pattern_type' => 'claim_analysis',
            'member_id' => $claim->member_id,
            'provider_id' => $claim->provider_id_fk,
            'related_claims' => json_encode([$claim->id]),
            'pattern_data' => json_encode([
                'factors' => $factors,
                'claim_amount' => $claim->claimed_amount,
                'claim_type' => $claim->claim_type,
                'service_date' => $claim->service_date?->toDateString(),
                'primary_icd_code' => $claim->primary_icd_code,
            ]),
            'period_start' => $claim->service_date,
            'period_end' => $claim->service_date,
            'claim_count' => 1,
            'total_amount' => $claim->claimed_amount,
            'anomaly_score' => collect($factors)->sum('score'),
            'model_version' => 'v1.0',
            'is_verified' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
