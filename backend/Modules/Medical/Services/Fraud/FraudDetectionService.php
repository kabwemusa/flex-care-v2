<?php

namespace Modules\Medical\Services\Fraud;

use Illuminate\Support\Facades\Log;
use Modules\Medical\Models\Claim;
use Modules\Medical\Models\PreAuthorization;
use Modules\Medical\Models\Member;

/**
 * Main Fraud Detection Orchestrator
 *
 * Coordinates fraud detection across all stages:
 * - Quick checks for real-time blocking decisions
 * - Full analysis for comprehensive scoring
 * - Alert generation and risk profile updates
 */
class FraudDetectionService
{
    public function __construct(
        private FraudRuleEngineService $ruleEngine,
        private FraudScoringService $scoringService,
        private FraudAlertService $alertService,
    ) {}

    /**
     * Quick fraud check for real-time decisions (<100ms target)
     * Used during claim/preauth submission
     */
    public function quickCheck(Claim|PreAuthorization $entity): QuickFraudResult
    {
        $startTime = microtime(true);

        try {
            $memberId = $entity->member_id;
            $providerId = $entity instanceof Claim ? $entity->provider_id_fk : $entity->provider_id;
            $amount = $entity instanceof Claim ? $entity->claimed_amount : $entity->requested_amount;

            // 1. Blacklist checks (fastest)
            $blacklistResult = $this->ruleEngine->checkBlacklist($memberId, $providerId);
            if ($blacklistResult['blocked']) {
                return new QuickFraudResult(
                    score: 100,
                    shouldBlock: true,
                    blockReason: $blacklistResult['reason'],
                    flags: ['BLACKLISTED'],
                    checkDurationMs: $this->getDurationMs($startTime),
                );
            }

            // 2. Duplicate detection
            $duplicateResult = $this->ruleEngine->checkDuplicate($entity);
            if ($duplicateResult['is_duplicate']) {
                return new QuickFraudResult(
                    score: 95,
                    shouldBlock: true,
                    blockReason: 'Duplicate submission detected',
                    flags: ['DUPLICATE_CLAIM'],
                    checkDurationMs: $this->getDurationMs($startTime),
                );
            }

            // 3. Quick rule checks
            $quickRules = $this->ruleEngine->runQuickRules($entity);

            // 4. Calculate preliminary score
            $score = $this->scoringService->calculateQuickScore($quickRules);

            // Determine if should block or flag
            $shouldBlock = $score >= 90;
            $requiresReview = $score >= 50;

            $flags = collect($quickRules)
                ->filter(fn($r) => $r['triggered'])
                ->pluck('rule_code')
                ->toArray();

            return new QuickFraudResult(
                score: $score,
                shouldBlock: $shouldBlock,
                blockReason: $shouldBlock ? 'High fraud risk score' : null,
                flags: $flags,
                requiresReview: $requiresReview,
                checkDurationMs: $this->getDurationMs($startTime),
            );

        } catch (\Exception $e) {
            Log::error('FraudDetectionService: Quick check failed', [
                'entity_type' => get_class($entity),
                'entity_id' => $entity->id,
                'error' => $e->getMessage(),
            ]);

            // On error, don't block but flag for review
            return new QuickFraudResult(
                score: 0,
                shouldBlock: false,
                blockReason: null,
                flags: ['FRAUD_CHECK_ERROR'],
                requiresReview: true,
                checkDurationMs: $this->getDurationMs($startTime),
                error: $e->getMessage(),
            );
        }
    }

    /**
     * Full comprehensive fraud analysis (async, runs in background job)
     */
    public function fullAnalysis(Claim $claim): FullFraudResult
    {
        $startTime = microtime(true);

        try {
            // 1. Member risk analysis
            $memberRisk = $this->scoringService->analyzeMemberRisk($claim);

            // 2. Provider risk analysis
            $providerRisk = $this->scoringService->analyzeProviderRisk($claim);

            // 3. Claim characteristics analysis
            $claimRisk = $this->scoringService->analyzeClaimCharacteristics($claim);

            // 4. Pattern analysis
            $patternRisk = $this->scoringService->analyzePatterns($claim);

            // 5. Temporal analysis
            $temporalRisk = $this->scoringService->analyzeTemporalFactors($claim);

            // 6. Run all applicable rules
            $ruleResults = $this->ruleEngine->runAllRules($claim);

            // 7. Calculate final score
            $finalScore = $this->scoringService->calculateFinalScore([
                'member_risk' => $memberRisk,
                'provider_risk' => $providerRisk,
                'claim_risk' => $claimRisk,
                'pattern_risk' => $patternRisk,
                'temporal_risk' => $temporalRisk,
                'rule_results' => $ruleResults,
            ]);

            // 8. Compile all flags
            $allFlags = $this->compileFlags([
                $memberRisk, $providerRisk, $claimRisk, $patternRisk, $temporalRisk
            ]);

            // 9. Determine risk level
            $riskLevel = match (true) {
                $finalScore >= 75 => 'high',
                $finalScore >= 40 => 'medium',
                default => 'low',
            };

            // 10. Create alert if needed
            if ($finalScore >= 75) {
                $this->alertService->createAlert($claim, $finalScore, [
                    'member_risk' => $memberRisk,
                    'provider_risk' => $providerRisk,
                    'claim_risk' => $claimRisk,
                    'pattern_risk' => $patternRisk,
                    'temporal_risk' => $temporalRisk,
                ], $allFlags);
            }

            // 11. Update risk profiles if score is elevated
            if ($finalScore >= 40) {
                $this->updateRiskProfiles($claim, $finalScore, $riskLevel);
            }

            // 12. Store pattern data for ML training
            if ($finalScore >= 25) {
                $this->scoringService->storePatternData($claim, [
                    'member_risk' => $memberRisk,
                    'provider_risk' => $providerRisk,
                    'claim_risk' => $claimRisk,
                    'pattern_risk' => $patternRisk,
                    'temporal_risk' => $temporalRisk,
                ]);
            }

            return new FullFraudResult(
                score: $finalScore,
                riskLevel: $riskLevel,
                flags: $allFlags,
                factors: [
                    'member_risk' => $memberRisk,
                    'provider_risk' => $providerRisk,
                    'claim_risk' => $claimRisk,
                    'pattern_risk' => $patternRisk,
                    'temporal_risk' => $temporalRisk,
                ],
                ruleResults: $ruleResults,
                analysisDurationMs: $this->getDurationMs($startTime),
            );

        } catch (\Exception $e) {
            Log::error('FraudDetectionService: Full analysis failed', [
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new FullFraudResult(
                score: 0,
                riskLevel: 'unknown',
                flags: ['ANALYSIS_ERROR'],
                factors: [],
                ruleResults: [],
                analysisDurationMs: $this->getDurationMs($startTime),
                error: $e->getMessage(),
            );
        }
    }

    /**
     * Pre-authorization specific fraud check
     */
    public function checkPreAuth(PreAuthorization $preauth): QuickFraudResult
    {
        return $this->quickCheck($preauth);
    }

    /**
     * Get fraud risk summary for a member
     */
    public function getMemberRiskSummary(string $memberId): array
    {
        return $this->scoringService->getMemberRiskSummary($memberId);
    }

    /**
     * Get fraud risk summary for a provider
     */
    public function getProviderRiskSummary(string $providerId): array
    {
        return $this->scoringService->getProviderRiskSummary($providerId);
    }

    private function compileFlags(array $riskResults): array
    {
        $flags = [];
        foreach ($riskResults as $result) {
            if (!empty($result['flags'])) {
                $flags = array_merge($flags, $result['flags']);
            }
        }
        return array_unique($flags);
    }

    private function updateRiskProfiles(Claim $claim, int $score, string $riskLevel): void
    {
        // Update member risk profile
        if ($claim->member) {
            $claim->member->update([
                'fraud_risk_score' => min(100, ($claim->member->fraud_risk_score ?? 0) + intval($score * 0.1)),
                'fraud_risk_level' => $riskLevel,
            ]);
        }

        // Update provider risk profile if applicable
        if ($claim->provider) {
            $currentScore = $claim->provider->fraud_risk_score ?? 0;
            $newScore = min(100, $currentScore + intval($score * 0.05));

            $claim->provider->update([
                'fraud_risk_score' => $newScore,
                'fraud_risk_level' => match (true) {
                    $newScore >= 60 => 'high',
                    $newScore >= 30 => 'medium',
                    default => 'low',
                },
            ]);
        }
    }

    private function getDurationMs(float $startTime): float
    {
        return round((microtime(true) - $startTime) * 1000, 2);
    }
}

/**
 * Result of quick fraud check
 */
class QuickFraudResult
{
    public function __construct(
        public int $score,
        public bool $shouldBlock,
        public ?string $blockReason,
        public array $flags = [],
        public bool $requiresReview = false,
        public float $checkDurationMs = 0,
        public ?string $error = null,
    ) {}

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'should_block' => $this->shouldBlock,
            'block_reason' => $this->blockReason,
            'flags' => $this->flags,
            'requires_review' => $this->requiresReview,
            'check_duration_ms' => $this->checkDurationMs,
            'error' => $this->error,
        ];
    }
}

/**
 * Result of full fraud analysis
 */
class FullFraudResult
{
    public function __construct(
        public int $score,
        public string $riskLevel,
        public array $flags = [],
        public array $factors = [],
        public array $ruleResults = [],
        public float $analysisDurationMs = 0,
        public ?string $error = null,
    ) {}

    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'risk_level' => $this->riskLevel,
            'flags' => $this->flags,
            'factors' => $this->factors,
            'rule_results' => $this->ruleResults,
            'analysis_duration_ms' => $this->analysisDurationMs,
            'error' => $this->error,
        ];
    }
}
