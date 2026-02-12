<?php

namespace Modules\Medical\Jobs\Fraud;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Medical\Models\Claim;
use Modules\Medical\Models\Member;
use Modules\Medical\Constants\MedicalConstants;

/**
 * Comprehensive fraud analysis job (runs asynchronously after claim processing)
 *
 * This job performs deeper analysis including:
 * - Historical pattern analysis
 * - Provider relationship analysis
 * - Geographic analysis
 * - Time-based patterns
 * - ML model scoring (when integrated)
 */
class FullFraudAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 180;

    public function __construct(
        public string $claimId
    ) {
        $this->onQueue('medical-fraud-analysis');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("fraud_analysis:{$this->claimId}"))
                ->expireAfter(300),
        ];
    }

    public function handle(): void
    {
        $claim = Claim::with(['member', 'policy', 'lines', 'provider'])->find($this->claimId);

        if (!$claim) {
            Log::warning("FullFraudAnalysisJob: Claim not found", ['claim_id' => $this->claimId]);
            return;
        }

        Log::info("FullFraudAnalysisJob: Starting analysis", [
            'claim_id' => $this->claimId,
            'claim_number' => $claim->claim_number,
        ]);

        try {
            $factors = [];
            $totalScore = 0;

            // 1. Member Risk Analysis (0-25 points)
            $memberRisk = $this->analyzeMemberRisk($claim);
            $factors['member_risk'] = $memberRisk;
            $totalScore += $memberRisk['score'];

            // 2. Provider Risk Analysis (0-20 points)
            $providerRisk = $this->analyzeProviderRisk($claim);
            $factors['provider_risk'] = $providerRisk;
            $totalScore += $providerRisk['score'];

            // 3. Claim Characteristics (0-25 points)
            $claimRisk = $this->analyzeClaimCharacteristics($claim);
            $factors['claim_characteristics'] = $claimRisk;
            $totalScore += $claimRisk['score'];

            // 4. Pattern Analysis (0-20 points)
            $patternRisk = $this->analyzePatterns($claim);
            $factors['pattern_analysis'] = $patternRisk;
            $totalScore += $patternRisk['score'];

            // 5. Temporal Analysis (0-10 points)
            $temporalRisk = $this->analyzeTemporalFactors($claim);
            $factors['temporal_analysis'] = $temporalRisk;
            $totalScore += $temporalRisk['score'];

            // Cap at 100
            $totalScore = min(100, $totalScore);

            // Determine risk level
            $riskLevel = match (true) {
                $totalScore >= 75 => 'high',
                $totalScore >= 40 => 'medium',
                default => 'low',
            };

            // Compile all flags
            $allFlags = [];
            foreach ($factors as $factorData) {
                if (!empty($factorData['flags'])) {
                    $allFlags = array_merge($allFlags, $factorData['flags']);
                }
            }

            // Update claim with analysis results
            $claim->update([
                'fraud_score' => $totalScore,
                'fraud_flags' => $allFlags,
                'fraud_check_version' => 'v1.0-full',
            ]);

            // Update member risk profile if score is elevated
            if ($totalScore >= 40) {
                $this->updateMemberRiskProfile($claim->member, $factors['member_risk']);
            }

            // Create fraud alert if score is high
            if ($totalScore >= 75) {
                $this->createFraudAlert($claim, $totalScore, $factors, $allFlags);
            }

            // Store pattern data for ML training
            if ($totalScore >= 25) {
                $this->storePatternData($claim, $factors);
            }

            Log::info("FullFraudAnalysisJob: Completed", [
                'claim_id' => $this->claimId,
                'final_score' => $totalScore,
                'risk_level' => $riskLevel,
                'flags_count' => count($allFlags),
            ]);

        } catch (\Exception $e) {
            Log::error("FullFraudAnalysisJob: Failed", [
                'claim_id' => $this->claimId,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - this is a background analysis, don't retry excessively
        }
    }

    private function analyzeMemberRisk(Claim $claim): array
    {
        $member = $claim->member;
        $score = 0;
        $flags = [];
        $details = [];

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
            $details[] = "Claim frequency increased significantly";
        }

        // Check rejection rate
        $totalClaims = Claim::where('member_id', $member->id)->count();
        $rejectedClaims = Claim::where('member_id', $member->id)
            ->where('status', MedicalConstants::CLAIM_STATUS_REJECTED)
            ->count();

        if ($totalClaims >= 5 && ($rejectedClaims / $totalClaims) > 0.3) {
            $score += 5;
            $flags[] = 'HIGH_REJECTION_RATE';
            $details[] = "High rejection rate: " . round(($rejectedClaims / $totalClaims) * 100) . "%";
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

    private function analyzeProviderRisk(Claim $claim): array
    {
        $score = 0;
        $flags = [];
        $details = [];

        if (!$claim->provider_id_fk) {
            return ['score' => 0, 'flags' => [], 'details' => []];
        }

        $provider = $claim->provider;

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
        if ($provider->rejection_rate > 0.25) {
            $score += 5;
            $flags[] = 'PROVIDER_HIGH_REJECTIONS';
            $details[] = "Provider rejection rate: " . round($provider->rejection_rate * 100) . "%";
        }

        // Check if this is first claim from this provider for this member
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

    private function analyzeClaimCharacteristics(Claim $claim): array
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
            $details[] = "Amount is 3x+ member average";
        } elseif ($avgClaim && $claim->claimed_amount > ($avgClaim * 2)) {
            $score += 5;
            $flags[] = 'AMOUNT_2X_AVERAGE';
        }

        // Round amount check (exactly round thousands often suspicious)
        if ($claim->claimed_amount >= 1000 && $claim->claimed_amount == floor($claim->claimed_amount / 1000) * 1000) {
            $score += 3;
            $flags[] = 'ROUND_AMOUNT';
            $details[] = "Suspiciously round amount";
        }

        // Multiple services on same day
        if ($claim->lines->count() > 5) {
            $score += 5;
            $flags[] = 'MANY_SERVICES';
            $details[] = "Many services ({$claim->lines->count()}) on single claim";
        }

        // All lines same amount (potential duplication)
        $uniqueAmounts = $claim->lines->pluck('claimed_amount')->unique()->count();
        if ($claim->lines->count() >= 3 && $uniqueAmounts === 1) {
            $score += 7;
            $flags[] = 'IDENTICAL_LINE_AMOUNTS';
            $details[] = "All lines have identical amounts";
        }

        return [
            'score' => min(25, $score),
            'flags' => $flags,
            'details' => $details,
        ];
    }

    private function analyzePatterns(Claim $claim): array
    {
        $score = 0;
        $flags = [];
        $details = [];

        $member = $claim->member;

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
        $sameDiagnosis = Claim::where('member_id', $member->id)
            ->where('primary_icd_code', $claim->primary_icd_code)
            ->where('created_at', '>=', now()->subDays(90))
            ->where('id', '!=', $claim->id)
            ->count();

        if ($sameDiagnosis >= 5) {
            $score += 5;
            $flags[] = 'REPEATED_DIAGNOSIS';
            $details[] = "Same diagnosis claimed {$sameDiagnosis}+ times in 90 days";
        }

        // Geographic anomaly (provider far from usual)
        // This would require geocoding - placeholder for now

        return [
            'score' => min(20, $score),
            'flags' => $flags,
            'details' => $details,
        ];
    }

    private function analyzeTemporalFactors(Claim $claim): array
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

        // Month-end claims (sometimes correlated with fraud)
        if ($claim->service_date && $claim->service_date->day >= 28) {
            $score += 2;
            $flags[] = 'MONTH_END_CLAIM';
        }

        // Time since last claim
        $lastClaim = Claim::where('member_id', $claim->member_id)
            ->where('id', '!=', $claim->id)
            ->latest('service_date')
            ->first();

        if ($lastClaim && $lastClaim->service_date->diffInDays($claim->service_date) <= 1) {
            $score += 5;
            $flags[] = 'CONSECUTIVE_DAY_CLAIMS';
            $details[] = "Claims on consecutive days";
        }

        return [
            'score' => min(10, $score),
            'flags' => $flags,
            'details' => $details,
        ];
    }

    private function updateMemberRiskProfile(Member $member, array $riskData): void
    {
        $member->update([
            'fraud_risk_score' => min(100, ($member->fraud_risk_score ?? 0) + ($riskData['score'] ?? 0)),
            'fraud_risk_level' => match (true) {
                ($member->fraud_risk_score ?? 0) >= 60 => 'high',
                ($member->fraud_risk_score ?? 0) >= 30 => 'medium',
                default => 'low',
            },
        ]);
    }

    private function createFraudAlert(Claim $claim, int $score, array $factors, array $flags): void
    {
        DB::table('med_fraud_alerts')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'alert_number' => 'FRA-' . now()->format('Y') . '-' . str_pad(
                DB::table('med_fraud_alerts')->whereYear('created_at', now()->year)->count() + 1,
                6, '0', STR_PAD_LEFT
            ),
            'entity_type' => 'claim',
            'entity_id' => $claim->id,
            'claim_id' => $claim->id,
            'member_id' => $claim->member_id,
            'provider_id' => $claim->provider_id_fk,
            'fraud_score' => $score,
            'severity' => $score >= 90 ? 'critical' : 'high',
            'triggered_rules' => json_encode($flags),
            'flags' => json_encode($factors),
            'flagged_amount' => $claim->claimed_amount,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::warning("FullFraudAnalysisJob: Created fraud alert", [
            'claim_id' => $claim->id,
            'score' => $score,
        ]);
    }

    private function storePatternData(Claim $claim, array $factors): void
    {
        DB::table('med_fraud_patterns')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'pattern_type' => 'claim_analysis',
            'member_id' => $claim->member_id,
            'provider_id' => $claim->provider_id_fk,
            'related_claims' => json_encode([$claim->id]),
            'pattern_data' => json_encode([
                'factors' => $factors,
                'claim_amount' => $claim->claimed_amount,
                'claim_type' => $claim->claim_type,
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

    public function failed(\Throwable $exception): void
    {
        Log::error("FullFraudAnalysisJob: Job failed", [
            'claim_id' => $this->claimId,
            'error' => $exception->getMessage(),
        ]);
    }
}
