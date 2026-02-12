<?php

namespace Modules\Medical\Services\Fraud;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Medical\Models\Claim;
use Modules\Medical\Models\PreAuthorization;
use Modules\Medical\Models\FraudAlert;
use Modules\Medical\Events\FraudAlertCreated;

/**
 * Fraud Alert Service
 *
 * Manages fraud alerts lifecycle:
 * - Alert creation
 * - Investigation workflow
 * - Resolution tracking
 */
class FraudAlertService
{
    /**
     * Create a fraud alert for a claim
     */
    public function createAlert(
        Claim|PreAuthorization $entity,
        int $score,
        array $factors,
        array $flags
    ): FraudAlert {
        $entityType = $entity instanceof Claim ? 'claim' : 'preauth';
        $entityNumber = $entity instanceof Claim ? $entity->claim_number : $entity->preauth_number;

        $alertNumber = $this->generateAlertNumber();

        $alert = FraudAlert::create([
            'alert_number' => $alertNumber,
            'entity_type' => $entityType,
            'entity_id' => $entity->id,
            'claim_id' => $entity instanceof Claim ? $entity->id : null,
            'preauth_id' => $entity instanceof PreAuthorization ? $entity->id : null,
            'member_id' => $entity->member_id,
            'provider_id' => $entity instanceof Claim ? $entity->provider_id_fk : $entity->provider_id,
            'fraud_score' => $score,
            'severity' => $this->determineSeverity($score),
            'triggered_rules' => $flags,
            'flags' => $factors,
            'flagged_amount' => $entity instanceof Claim ? $entity->claimed_amount : $entity->requested_amount,
            'status' => 'open',
        ]);

        // Create initial note
        $alert->notes()->create([
            'note_type' => 'system',
            'content' => "Alert created automatically. Score: {$score}. Triggered rules: " . implode(', ', $flags),
            'is_internal' => true,
            'created_by_type' => 'system',
        ]);

        Log::info('FraudAlertService: Alert created', [
            'alert_id' => $alert->id,
            'alert_number' => $alertNumber,
            'entity_type' => $entityType,
            'entity_id' => $entity->id,
            'score' => $score,
        ]);

        // Broadcast fraud alert event (will only broadcast high/critical severity)
        try {
            event(new FraudAlertCreated(
                alertId: $alert->id,
                alertNumber: $alert->alert_number,
                entityType: $alert->entity_type,
                entityId: $alert->entity_id,
                fraudScore: $alert->fraud_score,
                severity: $alert->severity,
                flags: $alert->triggered_rules ?? [],
                memberId: $alert->member_id,
                providerId: $alert->provider_id,
                flaggedAmount: (float) $alert->flagged_amount,
            ));
        } catch (\Exception $e) {
            Log::warning('FraudAlertService: Failed to broadcast alert event', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $alert;
    }

    /**
     * Start investigation on an alert
     */
    public function startInvestigation(string $alertId, string $investigatorId, ?string $notes = null): FraudAlert
    {
        $alert = FraudAlert::findOrFail($alertId);

        $alert->update([
            'status' => 'investigating',
            'assigned_to' => $investigatorId,
            'investigation_started_at' => now(),
        ]);

        $alert->notes()->create([
            'note_type' => 'investigation',
            'content' => $notes ?? 'Investigation started',
            'is_internal' => true,
            'created_by_id' => $investigatorId,
            'created_by_type' => 'user',
        ]);

        Log::info('FraudAlertService: Investigation started', [
            'alert_id' => $alertId,
            'investigator_id' => $investigatorId,
        ]);

        return $alert->fresh();
    }

    /**
     * Mark alert as confirmed fraud
     */
    public function confirmFraud(
        string $alertId,
        string $userId,
        string $notes,
        ?string $actionTaken = null
    ): FraudAlert {
        $alert = FraudAlert::findOrFail($alertId);

        $alert->update([
            'status' => 'confirmed_fraud',
            'resolution' => 'fraud_confirmed',
            'resolution_notes' => $notes,
            'action_taken' => $actionTaken,
            'resolved_at' => now(),
            'resolved_by' => $userId,
        ]);

        $alert->notes()->create([
            'note_type' => 'resolution',
            'content' => "FRAUD CONFIRMED: {$notes}",
            'is_internal' => true,
            'created_by_id' => $userId,
            'created_by_type' => 'user',
        ]);

        // Update pattern data for ML training
        $this->markPatternAsVerified($alert, true);

        // Update risk profiles
        $this->escalateRiskProfiles($alert);

        Log::warning('FraudAlertService: Fraud confirmed', [
            'alert_id' => $alertId,
            'member_id' => $alert->member_id,
            'provider_id' => $alert->provider_id,
        ]);

        return $alert->fresh();
    }

    /**
     * Mark alert as false positive
     */
    public function markFalsePositive(
        string $alertId,
        string $userId,
        string $notes
    ): FraudAlert {
        $alert = FraudAlert::findOrFail($alertId);

        $alert->update([
            'status' => 'false_positive',
            'resolution' => 'false_positive',
            'resolution_notes' => $notes,
            'resolved_at' => now(),
            'resolved_by' => $userId,
        ]);

        $alert->notes()->create([
            'note_type' => 'resolution',
            'content' => "FALSE POSITIVE: {$notes}",
            'is_internal' => true,
            'created_by_id' => $userId,
            'created_by_type' => 'user',
        ]);

        // Update pattern data for ML training
        $this->markPatternAsVerified($alert, false);

        Log::info('FraudAlertService: Marked as false positive', [
            'alert_id' => $alertId,
        ]);

        return $alert->fresh();
    }

    /**
     * Add note to alert
     */
    public function addNote(
        string $alertId,
        string $userId,
        string $content,
        string $noteType = 'investigation'
    ): void {
        $alert = FraudAlert::findOrFail($alertId);

        $alert->notes()->create([
            'note_type' => $noteType,
            'content' => $content,
            'is_internal' => true,
            'created_by_id' => $userId,
            'created_by_type' => 'user',
        ]);
    }

    /**
     * Get open alerts for dashboard
     */
    public function getOpenAlerts(array $filters = []): array
    {
        $query = FraudAlert::whereIn('status', ['open', 'investigating'])
            ->with(['claim', 'member', 'provider'])
            ->orderBy('fraud_score', 'desc')
            ->orderBy('created_at', 'desc');

        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['min_score'])) {
            $query->where('fraud_score', '>=', $filters['min_score']);
        }

        $alerts = $query->paginate($filters['per_page'] ?? 20);

        return [
            'alerts' => $alerts->items(),
            'total' => $alerts->total(),
            'current_page' => $alerts->currentPage(),
            'last_page' => $alerts->lastPage(),
        ];
    }

    /**
     * Get alert statistics
     */
    public function getStatistics(?string $period = '30d'): array
    {
        $startDate = match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            'ytd' => now()->startOfYear(),
            default => now()->subDays(30),
        };

        $total = FraudAlert::where('created_at', '>=', $startDate)->count();
        $open = FraudAlert::where('created_at', '>=', $startDate)
            ->whereIn('status', ['open', 'investigating'])
            ->count();
        $confirmed = FraudAlert::where('created_at', '>=', $startDate)
            ->where('status', 'confirmed_fraud')
            ->count();
        $falsePositive = FraudAlert::where('created_at', '>=', $startDate)
            ->where('status', 'false_positive')
            ->count();

        $confirmedAmount = FraudAlert::where('created_at', '>=', $startDate)
            ->where('status', 'confirmed_fraud')
            ->sum('flagged_amount');

        $bySeverity = FraudAlert::where('created_at', '>=', $startDate)
            ->selectRaw('severity, count(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $avgResolutionTime = FraudAlert::where('created_at', '>=', $startDate)
            ->whereNotNull('resolved_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - created_at)) / 3600) as avg_hours')
            ->value('avg_hours');

        return [
            'period' => $period,
            'total_alerts' => $total,
            'open_alerts' => $open,
            'confirmed_fraud' => $confirmed,
            'false_positives' => $falsePositive,
            'confirmed_amount' => round($confirmedAmount, 2),
            'detection_accuracy' => $confirmed + $falsePositive > 0
                ? round($confirmed / ($confirmed + $falsePositive) * 100, 1)
                : 0,
            'by_severity' => $bySeverity,
            'avg_resolution_hours' => round($avgResolutionTime ?? 0, 1),
        ];
    }

    /**
     * Add member to blacklist
     */
    public function blacklistMember(
        string $memberId,
        string $reason,
        string $userId,
        ?string $alertId = null
    ): void {
        DB::table('med_fraud_blacklist')->insert([
            'id' => Str::uuid(),
            'entity_type' => 'member',
            'entity_id' => $memberId,
            'reason' => $reason,
            'added_by' => $userId,
            'related_alert_id' => $alertId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Clear blacklist cache
        app(FraudRuleEngineService::class)->clearCache();

        Log::warning('FraudAlertService: Member blacklisted', [
            'member_id' => $memberId,
            'reason' => $reason,
            'added_by' => $userId,
        ]);
    }

    /**
     * Add provider to blacklist
     */
    public function blacklistProvider(
        string $providerId,
        string $reason,
        string $userId,
        ?string $alertId = null
    ): void {
        DB::table('med_fraud_blacklist')->insert([
            'id' => Str::uuid(),
            'entity_type' => 'provider',
            'entity_id' => $providerId,
            'reason' => $reason,
            'added_by' => $userId,
            'related_alert_id' => $alertId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Clear blacklist cache
        app(FraudRuleEngineService::class)->clearCache();

        Log::warning('FraudAlertService: Provider blacklisted', [
            'provider_id' => $providerId,
            'reason' => $reason,
            'added_by' => $userId,
        ]);
    }

    private function generateAlertNumber(): string
    {
        $year = now()->format('Y');
        $count = FraudAlert::whereYear('created_at', $year)->count() + 1;
        return 'FRA-' . $year . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
    }

    private function determineSeverity(int $score): string
    {
        return match (true) {
            $score >= 90 => 'critical',
            $score >= 75 => 'high',
            $score >= 50 => 'medium',
            default => 'low',
        };
    }

    private function markPatternAsVerified(FraudAlert $alert, bool $isFraud): void
    {
        if ($alert->claim_id) {
            DB::table('med_fraud_patterns')
                ->where('related_claims', 'like', "%{$alert->claim_id}%")
                ->update([
                    'is_verified' => true,
                    'verified_as' => $isFraud ? 'fraud' : 'legitimate',
                    'verified_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    private function escalateRiskProfiles(FraudAlert $alert): void
    {
        // Increase member risk score significantly
        if ($alert->member_id) {
            DB::table('med_members')
                ->where('id', $alert->member_id)
                ->update([
                    'fraud_risk_score' => DB::raw('LEAST(fraud_risk_score + 30, 100)'),
                    'fraud_risk_level' => 'high',
                    'updated_at' => now(),
                ]);
        }

        // Increase provider risk score
        if ($alert->provider_id) {
            DB::table('med_providers')
                ->where('id', $alert->provider_id)
                ->update([
                    'fraud_risk_score' => DB::raw('LEAST(fraud_risk_score + 20, 100)'),
                    'updated_at' => now(),
                ]);

            // Check if should escalate to high risk
            $provider = DB::table('med_providers')->find($alert->provider_id);
            if ($provider && $provider->fraud_risk_score >= 60) {
                DB::table('med_providers')
                    ->where('id', $alert->provider_id)
                    ->update([
                        'fraud_risk_level' => 'high',
                        'updated_at' => now(),
                    ]);
            }
        }
    }
}
