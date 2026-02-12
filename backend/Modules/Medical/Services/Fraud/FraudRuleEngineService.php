<?php

namespace Modules\Medical\Services\Fraud;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Medical\Models\Claim;
use Modules\Medical\Models\PreAuthorization;
use Modules\Medical\Models\FraudRule;
use Modules\Medical\Constants\MedicalConstants;

/**
 * Fraud Rule Engine Service
 *
 * Executes configurable fraud detection rules against claims and pre-authorizations.
 * Rules are stored in database and cached for performance.
 */
class FraudRuleEngineService
{
    private const CACHE_TTL = 300; // 5 minutes
    private const CACHE_KEY_RULES = 'fraud_rules:active';
    private const CACHE_KEY_BLACKLIST = 'fraud_blacklist';

    /**
     * Check if member or provider is blacklisted
     */
    public function checkBlacklist(?string $memberId, ?string $providerId): array
    {
        $blacklist = $this->getBlacklist();

        if ($memberId && in_array($memberId, $blacklist['members'])) {
            return [
                'blocked' => true,
                'reason' => 'Member is on fraud blacklist',
                'entity_type' => 'member',
            ];
        }

        if ($providerId && in_array($providerId, $blacklist['providers'])) {
            return [
                'blocked' => true,
                'reason' => 'Provider is on fraud blacklist',
                'entity_type' => 'provider',
            ];
        }

        return ['blocked' => false];
    }

    /**
     * Check for duplicate claim/preauth submission
     */
    public function checkDuplicate(Claim|PreAuthorization $entity): array
    {
        if ($entity instanceof Claim) {
            return $this->checkDuplicateClaim($entity);
        }

        return $this->checkDuplicatePreAuth($entity);
    }

    private function checkDuplicateClaim(Claim $claim): array
    {
        // Check for same member, provider, service date, and similar amount within 24 hours
        $duplicate = Claim::where('member_id', $claim->member_id)
            ->where('id', '!=', $claim->id)
            ->where('service_date', $claim->service_date)
            ->where('created_at', '>=', now()->subHours(24))
            ->where(function ($q) use ($claim) {
                $q->where('provider_id_fk', $claim->provider_id_fk)
                    ->orWhere('primary_icd_code', $claim->primary_icd_code);
            })
            ->whereBetween('claimed_amount', [
                $claim->claimed_amount * 0.95,
                $claim->claimed_amount * 1.05,
            ])
            ->first();

        if ($duplicate) {
            return [
                'is_duplicate' => true,
                'duplicate_id' => $duplicate->id,
                'duplicate_number' => $duplicate->claim_number,
            ];
        }

        return ['is_duplicate' => false];
    }

    private function checkDuplicatePreAuth(PreAuthorization $preauth): array
    {
        $duplicate = PreAuthorization::where('member_id', $preauth->member_id)
            ->where('id', '!=', $preauth->id)
            ->where('requested_service_date', $preauth->requested_service_date)
            ->where('created_at', '>=', now()->subHours(24))
            ->whereIn('status', ['pending', 'approved', 'auto_approved'])
            ->whereBetween('requested_amount', [
                $preauth->requested_amount * 0.95,
                $preauth->requested_amount * 1.05,
            ])
            ->first();

        if ($duplicate) {
            return [
                'is_duplicate' => true,
                'duplicate_id' => $duplicate->id,
                'duplicate_number' => $duplicate->preauth_number,
            ];
        }

        return ['is_duplicate' => false];
    }

    /**
     * Run quick rules for real-time decision (subset of all rules)
     */
    public function runQuickRules(Claim|PreAuthorization $entity): array
    {
        $rules = $this->getActiveRules()->filter(fn($r) => $r->is_quick_check);
        $results = [];

        foreach ($rules as $rule) {
            $results[] = $this->evaluateRule($rule, $entity);
        }

        return $results;
    }

    /**
     * Run all applicable rules
     */
    public function runAllRules(Claim|PreAuthorization $entity): array
    {
        $rules = $this->getActiveRules();
        $results = [];

        foreach ($rules as $rule) {
            $results[] = $this->evaluateRule($rule, $entity);
        }

        return $results;
    }

    /**
     * Evaluate a single rule against an entity
     */
    private function evaluateRule(FraudRule $rule, Claim|PreAuthorization $entity): array
    {
        $triggered = false;
        $details = [];

        try {
            $logic = $rule->rule_logic;
            $memberId = $entity->member_id;
            $amount = $entity instanceof Claim ? $entity->claimed_amount : $entity->requested_amount;

            switch ($rule->rule_code) {
                case 'FREQ_HIGH_CLAIMS_24H':
                    $count = $this->getClaimCount($memberId, 24);
                    $threshold = $logic['threshold'] ?? 5;
                    $triggered = $count >= $threshold;
                    $details = ['count' => $count, 'threshold' => $threshold];
                    break;

                case 'FREQ_HIGH_CLAIMS_7D':
                    $count = $this->getClaimCount($memberId, 168); // 7 days in hours
                    $threshold = $logic['threshold'] ?? 10;
                    $triggered = $count >= $threshold;
                    $details = ['count' => $count, 'threshold' => $threshold];
                    break;

                case 'FREQ_HIGH_CLAIMS_30D':
                    $count = $this->getClaimCount($memberId, 720); // 30 days in hours
                    $threshold = $logic['threshold'] ?? 20;
                    $triggered = $count >= $threshold;
                    $details = ['count' => $count, 'threshold' => $threshold];
                    break;

                case 'AMT_EXCEEDS_AVG_2X':
                    $avg = $this->getMemberAverageClaimAmount($memberId);
                    $multiplier = $logic['multiplier'] ?? 2;
                    $triggered = $avg > 0 && $amount > ($avg * $multiplier);
                    $details = ['average' => $avg, 'multiplier' => $multiplier, 'amount' => $amount];
                    break;

                case 'AMT_EXCEEDS_AVG_3X':
                    $avg = $this->getMemberAverageClaimAmount($memberId);
                    $multiplier = $logic['multiplier'] ?? 3;
                    $triggered = $avg > 0 && $amount > ($avg * $multiplier);
                    $details = ['average' => $avg, 'multiplier' => $multiplier, 'amount' => $amount];
                    break;

                case 'AMT_HIGH_VALUE':
                    $threshold = $logic['threshold'] ?? 50000;
                    $triggered = $amount >= $threshold;
                    $details = ['amount' => $amount, 'threshold' => $threshold];
                    break;

                case 'AMT_ROUND_NUMBER':
                    if ($amount >= 1000) {
                        $triggered = $amount == floor($amount / 1000) * 1000;
                        $details = ['amount' => $amount];
                    }
                    break;

                case 'PATTERN_RAPID_EXHAUSTION':
                    $exhaustionRate = $this->getBenefitExhaustionRate($memberId);
                    $threshold = $logic['threshold'] ?? 90;
                    $days = $logic['days'] ?? 30;
                    $triggered = $exhaustionRate >= $threshold;
                    $details = ['exhaustion_rate' => $exhaustionRate, 'threshold' => $threshold];
                    break;

                case 'PATTERN_SAME_DIAGNOSIS':
                    if ($entity instanceof Claim && $entity->primary_icd_code) {
                        $count = $this->getSameDiagnosisCount($memberId, $entity->primary_icd_code, 90);
                        $threshold = $logic['threshold'] ?? 5;
                        $triggered = $count >= $threshold;
                        $details = ['count' => $count, 'threshold' => $threshold, 'icd_code' => $entity->primary_icd_code];
                    }
                    break;

                case 'PATTERN_CONSECUTIVE_DAYS':
                    $consecutiveDays = $this->getConsecutiveClaimDays($memberId);
                    $threshold = $logic['threshold'] ?? 3;
                    $triggered = $consecutiveDays >= $threshold;
                    $details = ['consecutive_days' => $consecutiveDays, 'threshold' => $threshold];
                    break;

                case 'PROVIDER_HIGH_REJECTION':
                    if ($entity instanceof Claim && $entity->provider_id_fk) {
                        $rejectionRate = $this->getProviderRejectionRate($entity->provider_id_fk);
                        $threshold = $logic['threshold'] ?? 0.3;
                        $triggered = $rejectionRate >= $threshold;
                        $details = ['rejection_rate' => $rejectionRate, 'threshold' => $threshold];
                    }
                    break;

                case 'PROVIDER_NEW_RELATIONSHIP':
                    if ($entity instanceof Claim && $entity->provider_id_fk) {
                        $isNew = $this->isNewProviderRelationship($memberId, $entity->provider_id_fk);
                        $amountThreshold = $logic['amount_threshold'] ?? 5000;
                        $triggered = $isNew && $amount >= $amountThreshold;
                        $details = ['is_new_provider' => $isNew, 'amount' => $amount];
                    }
                    break;

                case 'MEMBER_NEW_HIGH_CLAIM':
                    $tenureDays = $this->getMemberTenureDays($memberId);
                    $maxDays = $logic['max_days'] ?? 60;
                    $amountThreshold = $logic['amount_threshold'] ?? 10000;
                    $triggered = $tenureDays <= $maxDays && $amount >= $amountThreshold;
                    $details = ['tenure_days' => $tenureDays, 'amount' => $amount];
                    break;

                case 'MEMBER_HIGH_REJECTION_RATE':
                    $rejectionRate = $this->getMemberRejectionRate($memberId);
                    $threshold = $logic['threshold'] ?? 0.3;
                    $minClaims = $logic['min_claims'] ?? 5;
                    $totalClaims = $this->getTotalMemberClaims($memberId);
                    $triggered = $totalClaims >= $minClaims && $rejectionRate >= $threshold;
                    $details = ['rejection_rate' => $rejectionRate, 'total_claims' => $totalClaims];
                    break;

                case 'TEMPORAL_WEEKEND_HOSPITAL':
                    if ($entity instanceof Claim && $entity->claim_type === 'in_patient' && $entity->service_date) {
                        $dayOfWeek = $entity->service_date->dayOfWeek;
                        $triggered = $dayOfWeek === 0 || $dayOfWeek === 6;
                        $details = ['day_of_week' => $dayOfWeek];
                    }
                    break;

                case 'TEMPORAL_MONTH_END':
                    $serviceDate = $entity instanceof Claim ? $entity->service_date : $entity->requested_service_date;
                    if ($serviceDate) {
                        $dayOfMonth = $serviceDate->day;
                        $threshold = $logic['day_threshold'] ?? 28;
                        $triggered = $dayOfMonth >= $threshold;
                        $details = ['day_of_month' => $dayOfMonth];
                    }
                    break;

                case 'LINES_MANY_SERVICES':
                    if ($entity instanceof Claim) {
                        $lineCount = $entity->lines()->count();
                        $threshold = $logic['threshold'] ?? 10;
                        $triggered = $lineCount >= $threshold;
                        $details = ['line_count' => $lineCount, 'threshold' => $threshold];
                    }
                    break;

                case 'LINES_IDENTICAL_AMOUNTS':
                    if ($entity instanceof Claim) {
                        $lines = $entity->lines;
                        if ($lines->count() >= 3) {
                            $uniqueAmounts = $lines->pluck('claimed_amount')->unique()->count();
                            $triggered = $uniqueAmounts === 1;
                            $details = ['line_count' => $lines->count(), 'unique_amounts' => $uniqueAmounts];
                        }
                    }
                    break;

                default:
                    // Unknown rule - skip
                    break;
            }

        } catch (\Exception $e) {
            // Log error but don't fail the entire check
            $details = ['error' => $e->getMessage()];
        }

        return [
            'rule_code' => $rule->rule_code,
            'rule_name' => $rule->name,
            'category' => $rule->category,
            'triggered' => $triggered,
            'points' => $triggered ? $rule->points : 0,
            'severity' => $rule->severity,
            'details' => $details,
        ];
    }

    /**
     * Get active fraud rules from cache or database
     */
    private function getActiveRules()
    {
        return Cache::remember(self::CACHE_KEY_RULES, self::CACHE_TTL, function () {
            return FraudRule::where('is_active', true)
                ->orderBy('priority', 'desc')
                ->get();
        });
    }

    /**
     * Get blacklist from cache or database
     */
    private function getBlacklist(): array
    {
        return Cache::remember(self::CACHE_KEY_BLACKLIST, self::CACHE_TTL, function () {
            $members = DB::table('med_fraud_blacklist')
                ->where('entity_type', 'member')
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->pluck('entity_id')
                ->toArray();

            $providers = DB::table('med_fraud_blacklist')
                ->where('entity_type', 'provider')
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->pluck('entity_id')
                ->toArray();

            return [
                'members' => $members,
                'providers' => $providers,
            ];
        });
    }

    // Helper methods for rule evaluation

    private function getClaimCount(string $memberId, int $hours): int
    {
        return Claim::where('member_id', $memberId)
            ->where('created_at', '>=', now()->subHours($hours))
            ->count();
    }

    private function getMemberAverageClaimAmount(string $memberId): float
    {
        return (float) Claim::where('member_id', $memberId)
            ->where('status', MedicalConstants::CLAIM_STATUS_APPROVED)
            ->avg('claimed_amount') ?? 0;
    }

    private function getBenefitExhaustionRate(string $memberId): float
    {
        $utilization = DB::table('med_member_benefit_utilization')
            ->where('member_id', $memberId)
            ->where('utilization_percentage', '>=', 80)
            ->count();

        $total = DB::table('med_member_benefit_utilization')
            ->where('member_id', $memberId)
            ->count();

        return $total > 0 ? ($utilization / $total) * 100 : 0;
    }

    private function getSameDiagnosisCount(string $memberId, string $icdCode, int $days): int
    {
        return Claim::where('member_id', $memberId)
            ->where('primary_icd_code', $icdCode)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();
    }

    private function getConsecutiveClaimDays(string $memberId): int
    {
        $recentClaims = Claim::where('member_id', $memberId)
            ->where('service_date', '>=', now()->subDays(10))
            ->orderBy('service_date', 'desc')
            ->pluck('service_date')
            ->unique()
            ->values();

        if ($recentClaims->isEmpty()) {
            return 0;
        }

        $consecutive = 1;
        for ($i = 0; $i < $recentClaims->count() - 1; $i++) {
            if ($recentClaims[$i]->diffInDays($recentClaims[$i + 1]) === 1) {
                $consecutive++;
            } else {
                break;
            }
        }

        return $consecutive;
    }

    private function getProviderRejectionRate(string $providerId): float
    {
        $total = Claim::where('provider_id_fk', $providerId)->count();
        if ($total < 5) {
            return 0;
        }

        $rejected = Claim::where('provider_id_fk', $providerId)
            ->where('status', MedicalConstants::CLAIM_STATUS_REJECTED)
            ->count();

        return $rejected / $total;
    }

    private function isNewProviderRelationship(string $memberId, string $providerId): bool
    {
        return Claim::where('member_id', $memberId)
            ->where('provider_id_fk', $providerId)
            ->where('created_at', '<', now()->subDays(1))
            ->count() === 0;
    }

    private function getMemberTenureDays(string $memberId): int
    {
        $member = DB::table('med_members')->find($memberId);
        if (!$member || !$member->cover_start_date) {
            return 365; // Assume long tenure if unknown
        }

        return now()->diffInDays($member->cover_start_date);
    }

    private function getMemberRejectionRate(string $memberId): float
    {
        $total = Claim::where('member_id', $memberId)->count();
        if ($total === 0) {
            return 0;
        }

        $rejected = Claim::where('member_id', $memberId)
            ->where('status', MedicalConstants::CLAIM_STATUS_REJECTED)
            ->count();

        return $rejected / $total;
    }

    private function getTotalMemberClaims(string $memberId): int
    {
        return Claim::where('member_id', $memberId)->count();
    }

    /**
     * Clear the rules cache (call when rules are updated)
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_RULES);
        Cache::forget(self::CACHE_KEY_BLACKLIST);
    }
}
