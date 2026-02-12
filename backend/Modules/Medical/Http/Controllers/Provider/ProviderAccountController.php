<?php

namespace Modules\Medical\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Medical\Models\ProviderApiLog;

class ProviderAccountController extends Controller
{
    /**
     * Get provider profile
     *
     * GET /v1/provider/account/profile
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function profile(Request $request): JsonResponse
    {
        $provider = $request->attributes->get('provider');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $provider->id,
                'provider_code' => $provider->provider_code,
                'name' => $provider->name,
                'trading_name' => $provider->trading_name,
                'provider_type' => $provider->provider_type,
                'provider_type_label' => $provider->provider_type_label,

                'contact' => [
                    'address' => $provider->address,
                    'city' => $provider->city,
                    'region' => $provider->region,
                    'phone' => $provider->phone,
                    'email' => $provider->email,
                    'website' => $provider->website,
                ],

                'network_status' => [
                    'is_network_provider' => $provider->is_network_provider,
                    'network_tier' => $provider->network_tier,
                    'network_tier_label' => $provider->network_tier_label,
                    'has_active_contract' => $provider->has_active_contract,
                    'contract_start_date' => $provider->contract_start_date?->format('Y-m-d'),
                    'contract_end_date' => $provider->contract_end_date?->format('Y-m-d'),
                    'discount_percentage' => $provider->discount_percentage,
                ],

                'status' => $provider->status,
                'status_label' => $provider->status_label,

                'statistics' => [
                    'total_claims_count' => $provider->total_claims_count,
                    'total_claims_amount' => $provider->total_claims_amount,
                    'approved_claims_count' => $provider->approved_claims_count,
                    'rejected_claims_count' => $provider->rejected_claims_count,
                    'average_claim_amount' => $provider->average_claim_amount,
                    'rejection_rate' => round($provider->rejection_rate * 100, 2) . '%',
                    'stats_updated_at' => $provider->stats_updated_at?->toIso8601String(),
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get API usage statistics
     *
     * GET /v1/provider/account/statistics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:90',
        ]);

        $provider = $request->attributes->get('provider');
        $days = $validated['days'] ?? 30;

        $stats = ProviderApiLog::getStatisticsForProvider($provider->id, $days);

        // Get daily breakdown
        $dailyStats = ProviderApiLog::where('provider_id', $provider->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as requests, SUM(CASE WHEN is_error THEN 1 ELSE 0 END) as errors')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'date' => $row->date,
                'requests' => $row->requests,
                'errors' => $row->errors,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $stats,
                'daily_breakdown' => $dailyStats,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get current API credential info
     *
     * GET /v1/provider/account/credential
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function credential(Request $request): JsonResponse
    {
        $credential = $request->attributes->get('provider_credential');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $credential->id,
                'name' => $credential->name,
                'api_key' => $credential->masked_api_key,
                'scopes' => $credential->scopes,

                'rate_limits' => [
                    'per_minute' => $credential->rate_limit_per_minute,
                    'per_hour' => $credential->rate_limit_per_hour,
                    'per_day' => $credential->rate_limit_per_day,
                ],

                'allowed_ips' => $credential->allowed_ips,

                'usage' => [
                    'total_requests' => $credential->total_requests,
                    'failed_requests' => $credential->failed_requests,
                    'success_rate' => $credential->success_rate . '%',
                    'last_used_at' => $credential->last_used_at?->toIso8601String(),
                    'last_used_ip' => $credential->last_used_ip,
                ],

                'validity' => [
                    'is_active' => $credential->is_active,
                    'is_valid' => $credential->is_valid,
                    'expires_at' => $credential->expires_at?->toIso8601String(),
                    'is_expired' => $credential->is_expired,
                ],

                'created_at' => $credential->created_at->toIso8601String(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Test API connection (health check)
     *
     * GET /v1/provider/account/ping
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function ping(Request $request): JsonResponse
    {
        $provider = $request->attributes->get('provider');
        $credential = $request->attributes->get('provider_credential');
        $authElapsedMs = $request->attributes->get('auth_elapsed_ms', 0);

        return response()->json([
            'success' => true,
            'data' => [
                'status' => 'ok',
                'provider' => $provider->name,
                'provider_code' => $provider->provider_code,
                'credential_name' => $credential->name,
                'auth_time_ms' => $authElapsedMs,
                'server_time' => now()->toIso8601String(),
                'api_version' => 'v1',
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Register webhook URL
     *
     * POST /v1/provider/webhooks/register
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function registerWebhook(Request $request): JsonResponse
    {
        // Placeholder - webhook functionality to be implemented
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NOT_IMPLEMENTED',
                'message' => 'Webhook registration is not yet implemented',
            ],
            'timestamp' => now()->toIso8601String(),
        ], 501);
    }

    /**
     * List registered webhooks
     *
     * GET /v1/provider/webhooks
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listWebhooks(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'webhooks' => [],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Delete webhook
     *
     * DELETE /v1/provider/webhooks/{webhookId}
     *
     * @param Request $request
     * @param string $webhookId
     * @return JsonResponse
     */
    public function deleteWebhook(Request $request, string $webhookId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NOT_IMPLEMENTED',
                'message' => 'Webhook deletion is not yet implemented',
            ],
            'timestamp' => now()->toIso8601String(),
        ], 501);
    }

    /**
     * Test webhook (send test payload)
     *
     * POST /v1/provider/webhooks/{webhookId}/test
     *
     * @param Request $request
     * @param string $webhookId
     * @return JsonResponse
     */
    public function testWebhook(Request $request, string $webhookId): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NOT_IMPLEMENTED',
                'message' => 'Webhook testing is not yet implemented',
            ],
            'timestamp' => now()->toIso8601String(),
        ], 501);
    }
}
