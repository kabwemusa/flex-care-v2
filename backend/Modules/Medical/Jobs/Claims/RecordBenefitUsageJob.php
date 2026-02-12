<?php

namespace Modules\Medical\Jobs\Claims;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Medical\Models\Claim;
use Modules\Medical\Models\MemberBenefitUtilization;
use Modules\Medical\Constants\MedicalConstants;

class RecordBenefitUsageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public string $claimId
    ) {
        $this->onQueue('medical-claims-standard');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("benefit_usage:{$this->claimId}"))
                ->dontRelease()
                ->expireAfter(120),
        ];
    }

    public function handle(): void
    {
        $claim = Claim::with(['lines.benefit', 'member', 'policy.plan'])->find($this->claimId);

        if (!$claim) {
            Log::warning("RecordBenefitUsageJob: Claim not found", ['claim_id' => $this->claimId]);
            return;
        }

        // Only record usage for approved claims
        if (!in_array($claim->status, [
            MedicalConstants::CLAIM_STATUS_APPROVED,
            MedicalConstants::CLAIM_STATUS_PARTIALLY_APPROVED,
        ])) {
            Log::info("RecordBenefitUsageJob: Claim not approved, skipping", [
                'claim_id' => $this->claimId,
                'status' => $claim->status,
            ]);
            return;
        }

        Log::info("RecordBenefitUsageJob: Recording benefit usage", [
            'claim_id' => $this->claimId,
            'claim_number' => $claim->claim_number,
        ]);

        try {
            DB::transaction(function () use ($claim) {
                foreach ($claim->lines as $line) {
                    if (!$line->benefit_id || $line->status === 'rejected') {
                        continue;
                    }

                    $approvedAmount = $line->approved_amount ?? 0;
                    if ($approvedAmount <= 0) {
                        continue;
                    }

                    $this->recordLineUsage($claim, $line, $approvedAmount);
                }
            }, 5); // 5 deadlock retries

            Log::info("RecordBenefitUsageJob: Completed", [
                'claim_id' => $this->claimId,
            ]);

        } catch (\Exception $e) {
            Log::error("RecordBenefitUsageJob: Failed", [
                'claim_id' => $this->claimId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function recordLineUsage(Claim $claim, $line, float $approvedAmount): void
    {
        // Find or create utilization record for current period
        $utilization = MemberBenefitUtilization::lockForUpdate()
            ->where('member_id', $claim->member_id)
            ->where('benefit_id', $line->benefit_id)
            ->where('period_start', '<=', $claim->service_date)
            ->where('period_end', '>=', $claim->service_date)
            ->first();

        if (!$utilization) {
            // Create new utilization record
            $utilization = $this->createUtilizationRecord($claim, $line);
        }

        // Update usage
        $utilization->used_amount += $approvedAmount;
        $utilization->used_count += 1;
        $utilization->claims_count += 1;
        $utilization->last_claim_at = now();

        // Recalculate remaining
        if ($utilization->limit_amount) {
            $utilization->remaining_amount = max(0, $utilization->limit_amount - $utilization->used_amount);
            $utilization->utilization_percentage = round(
                ($utilization->used_amount / $utilization->limit_amount) * 100,
                2
            );

            // Check exhaustion
            if ($utilization->remaining_amount <= 0) {
                $utilization->is_exhausted = true;
                $utilization->exhausted_at = now();
            }
        }

        $utilization->save();

        // Create history record
        $utilization->history()->create([
            'claim_id' => $claim->id,
            'claim_line_id' => $line->id,
            'transaction_type' => 'claim_approved',
            'amount_change' => $approvedAmount,
            'count_change' => 1,
            'balance_amount' => $utilization->remaining_amount,
            'notes' => "Claim {$claim->claim_number} approved",
        ]);

        Log::debug("RecordBenefitUsageJob: Recorded usage", [
            'claim_id' => $claim->id,
            'benefit_id' => $line->benefit_id,
            'amount' => $approvedAmount,
            'remaining' => $utilization->remaining_amount,
        ]);
    }

    private function createUtilizationRecord(Claim $claim, $line): MemberBenefitUtilization
    {
        $policy = $claim->policy;
        $plan = $policy->plan;

        // Get plan benefit configuration
        $planBenefit = DB::table('med_plan_benefits')
            ->where('plan_id', $plan->id)
            ->where('benefit_id', $line->benefit_id)
            ->first();

        // Determine period (usually policy year)
        $periodStart = $policy->inception_date;
        $periodEnd = $policy->expiry_date;

        return MemberBenefitUtilization::create([
            'member_id' => $claim->member_id,
            'policy_id' => $policy->id,
            'benefit_id' => $line->benefit_id,
            'period_type' => 'annual',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'limit_type' => $planBenefit->limit_type ?? 'monetary',
            'limit_amount' => $planBenefit->limit_amount ?? null,
            'limit_count' => $planBenefit->limit_count ?? null,
            'limit_days' => $planBenefit->limit_days ?? null,
            'used_amount' => 0,
            'used_count' => 0,
            'used_days' => 0,
            'reserved_amount' => 0,
            'reserved_count' => 0,
            'reserved_days' => 0,
            'remaining_amount' => $planBenefit->limit_amount ?? 0,
            'remaining_count' => $planBenefit->limit_count ?? 0,
            'remaining_days' => $planBenefit->limit_days ?? 0,
            'claims_count' => 0,
            'is_exhausted' => false,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("RecordBenefitUsageJob: Job failed permanently", [
            'claim_id' => $this->claimId,
            'error' => $exception->getMessage(),
        ]);
    }
}
