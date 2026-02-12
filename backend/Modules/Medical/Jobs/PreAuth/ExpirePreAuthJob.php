<?php

namespace Modules\Medical\Jobs\PreAuth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Medical\Models\PreAuthorization;
use Modules\Medical\Models\BenefitReservation;

/**
 * Scheduled job to expire old pre-authorizations and release reservations
 *
 * Schedule this job to run every hour:
 * $schedule->job(new ExpirePreAuthJob)->hourly();
 */
class ExpirePreAuthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes

    public function __construct()
    {
        $this->onQueue('medical-preauth');
    }

    public function handle(): void
    {
        Log::info("ExpirePreAuthJob: Starting");

        $expiredCount = 0;
        $reservationsReleased = 0;

        try {
            // Find all pre-auths that should be expired
            $expiredPreAuths = PreAuthorization::whereIn('status', ['approved', 'auto_approved'])
                ->where('is_expired', false)
                ->where('expires_at', '<=', now())
                ->whereNull('claim_id') // Not yet claimed
                ->get();

            Log::info("ExpirePreAuthJob: Found {$expiredPreAuths->count()} pre-auths to expire");

            foreach ($expiredPreAuths as $preauth) {
                DB::transaction(function () use ($preauth, &$expiredCount, &$reservationsReleased) {
                    // Expire the pre-auth
                    $preauth->update([
                        'status' => 'expired',
                        'is_expired' => true,
                    ]);

                    // Release all active reservations
                    $reservations = BenefitReservation::where('preauthorization_id', $preauth->id)
                        ->where('status', 'active')
                        ->get();

                    foreach ($reservations as $reservation) {
                        $reservation->markAsExpired();
                        $reservationsReleased++;
                    }

                    // Add note
                    $preauth->notes()->create([
                        'note_type' => 'system',
                        'content' => 'Pre-authorization expired. Benefit reservations released.',
                        'is_internal' => true,
                        'visible_to_provider' => true,
                        'created_by_type' => 'system',
                    ]);

                    $expiredCount++;

                    Log::debug("ExpirePreAuthJob: Expired pre-auth", [
                        'preauth_id' => $preauth->id,
                        'preauth_number' => $preauth->preauth_number,
                    ]);
                });
            }

            Log::info("ExpirePreAuthJob: Completed", [
                'expired_count' => $expiredCount,
                'reservations_released' => $reservationsReleased,
            ]);

        } catch (\Exception $e) {
            Log::error("ExpirePreAuthJob: Failed", [
                'error' => $e->getMessage(),
                'expired_so_far' => $expiredCount,
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ExpirePreAuthJob: Job failed permanently", [
            'error' => $exception->getMessage(),
        ]);
    }
}
