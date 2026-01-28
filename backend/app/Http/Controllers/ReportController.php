<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService)
    {
    }

    /**
     * Get dashboard summary with KPIs from all domains
     */
    public function summary(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $data = $this->reportService->getDashboardSummary($filters);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Get approval metrics
     */
    public function approvalMetrics(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'entity_type' => 'nullable|string',
            'workflow_id' => 'nullable|uuid',
        ]);

        $data = $this->reportService->getApprovalMetrics($filters);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Get policy analytics
     */
    public function policyAnalytics(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'scheme_id' => 'nullable|uuid',
            'plan_id' => 'nullable|uuid',
            'policy_type' => 'nullable|string',
            'group_id' => 'nullable|uuid',
        ]);

        $data = $this->reportService->getPolicyAnalytics($filters);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Get billing reports
     */
    public function billingReports(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'policy_id' => 'nullable|uuid',
            'group_id' => 'nullable|uuid',
        ]);

        $data = $this->reportService->getBillingReports($filters);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Export approval data
     */
    public function exportApprovals(Request $request)
    {
        $filters = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'format' => 'nullable|in:csv,pdf',
        ]);

        $data = $this->reportService->getApprovalMetrics($filters);
        $format = $filters['format'] ?? 'csv';

        if ($format === 'csv') {
            return $this->exportToCsv($data, 'approval-metrics');
        }

        return response()->json([
            'status' => 'error',
            'message' => 'PDF export not implemented yet',
        ], 501);
    }

    /**
     * Export policy data
     */
    public function exportPolicies(Request $request)
    {
        $filters = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'format' => 'nullable|in:csv,pdf',
        ]);

        $data = $this->reportService->getPolicyAnalytics($filters);
        $format = $filters['format'] ?? 'csv';

        if ($format === 'csv') {
            return $this->exportToCsv($data, 'policy-analytics');
        }

        return response()->json([
            'status' => 'error',
            'message' => 'PDF export not implemented yet',
        ], 501);
    }

    /**
     * Export billing data
     */
    public function exportBilling(Request $request)
    {
        $filters = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'format' => 'nullable|in:csv,pdf',
        ]);

        $data = $this->reportService->getBillingReports($filters);
        $format = $filters['format'] ?? 'csv';

        if ($format === 'csv') {
            return $this->exportToCsv($data, 'billing-reports');
        }

        return response()->json([
            'status' => 'error',
            'message' => 'PDF export not implemented yet',
        ], 501);
    }

    /**
     * Helper to export data as CSV
     */
    private function exportToCsv(array $data, string $filename)
    {
        $csv = '';
        $timestamp = now()->format('Y-m-d_H-i-s');

        // Export summary section
        if (isset($data['summary'])) {
            $csv .= "Summary\n";
            foreach ($data['summary'] as $key => $value) {
                $csv .= ucwords(str_replace('_', ' ', $key)) . ',' . $value . "\n";
            }
            $csv .= "\n";
        }

        // Export each array section
        foreach ($data as $section => $items) {
            if ($section === 'summary' || !is_array($items) || empty($items)) {
                continue;
            }

            $csv .= ucwords(str_replace('_', ' ', $section)) . "\n";

            // Get headers from first item
            if (isset($items[0]) && is_array($items[0])) {
                $headers = array_keys($items[0]);
                $csv .= implode(',', array_map(fn($h) => ucwords(str_replace('_', ' ', $h)), $headers)) . "\n";

                foreach ($items as $item) {
                    $csv .= implode(',', array_values($item)) . "\n";
                }
            }

            $csv .= "\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}_{$timestamp}.csv\"");
    }
}
