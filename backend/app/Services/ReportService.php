<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\ApprovalHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Modules\Medical\Models\Policy;
use Modules\Medical\Models\Invoice;
use Modules\Medical\Models\Payment;
use Modules\Medical\Models\Member;

class ReportService
{
    /**
     * Get dashboard summary with key metrics from all domains
     */
    public function getDashboardSummary(array $filters): array
    {
        $fromDate = $filters['from_date'] ?? Carbon::now()->subDays(90)->toDateString();
        $toDate = $filters['to_date'] ?? Carbon::now()->toDateString();

        return [
            'approvals' => $this->getApprovalSummary($fromDate, $toDate),
            'policies' => $this->getPolicySummary($fromDate, $toDate),
            'billing' => $this->getBillingSummary($fromDate, $toDate),
        ];
    }

    /**
     * Get approval metrics
     */
    public function getApprovalMetrics(array $filters): array
    {
        $fromDate = $filters['from_date'] ?? Carbon::now()->subDays(90)->toDateString();
        $toDate = $filters['to_date'] ?? Carbon::now()->toDateString();

        return [
            'summary' => $this->getApprovalSummaryDetailed($fromDate, $toDate, $filters),
            'status_distribution' => $this->getApprovalStatusDistribution($fromDate, $toDate, $filters),
            'trend' => $this->getApprovalTrend($fromDate, $toDate, $filters),
            'by_entity_type' => $this->getApprovalsByEntityType($fromDate, $toDate, $filters),
            'approver_performance' => $this->getApproverPerformance($fromDate, $toDate, $filters),
            'bottlenecks' => $this->getWorkflowBottlenecks($fromDate, $toDate, $filters),
        ];
    }

    /**
     * Get policy analytics
     */
    public function getPolicyAnalytics(array $filters): array
    {
        $fromDate = $filters['from_date'] ?? Carbon::now()->subDays(90)->toDateString();
        $toDate = $filters['to_date'] ?? Carbon::now()->toDateString();

        return [
            'summary' => $this->getPolicySummaryDetailed($filters),
            'status_distribution' => $this->getPolicyStatusDistribution($filters),
            'type_distribution' => $this->getPolicyTypeDistribution($filters),
            'premium_breakdown' => $this->getPremiumBreakdown($filters),
            'monthly_trend' => $this->getPolicyMonthlyTrend($fromDate, $toDate, $filters),
            'renewal_pipeline' => $this->getRenewalPipeline(),
            'top_plans' => $this->getTopPlans($filters),
            'member_distribution' => $this->getMemberDistribution($filters),
        ];
    }

    /**
     * Get billing reports
     */
    public function getBillingReports(array $filters): array
    {
        $fromDate = $filters['from_date'] ?? Carbon::now()->subDays(90)->toDateString();
        $toDate = $filters['to_date'] ?? Carbon::now()->toDateString();

        return [
            'summary' => $this->getBillingSummaryDetailed($fromDate, $toDate, $filters),
            'invoice_status_distribution' => $this->getInvoiceStatusDistribution($filters),
            'collection_trend' => $this->getCollectionTrend($fromDate, $toDate, $filters),
            'aging_analysis' => $this->getAgingAnalysis($filters),
            'payment_methods' => $this->getPaymentMethodDistribution($fromDate, $toDate, $filters),
            'top_outstanding' => $this->getTopOutstanding($filters),
            'daily_collections' => $this->getDailyCollections($filters),
        ];
    }

    // =========================================================================
    // APPROVAL HELPERS
    // =========================================================================

    private function getApprovalSummary(string $fromDate, string $toDate): array
    {
        $stats = ApprovalRequest::query()
            ->selectRaw("
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'approved' AND DATE(completed_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as approved_this_week,
                AVG(CASE WHEN completed_at IS NOT NULL THEN DATEDIFF(completed_at, created_at) END) as avg_processing_days,
                ROUND(COUNT(CASE WHEN status = 'approved' THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN status IN ('approved', 'rejected') THEN 1 END), 0), 1) as approval_rate
            ")
            ->first();

        return [
            'pending_count' => (int) ($stats->pending_count ?? 0),
            'approved_this_week' => (int) ($stats->approved_this_week ?? 0),
            'avg_processing_days' => round($stats->avg_processing_days ?? 0, 1),
            'approval_rate' => round($stats->approval_rate ?? 0, 1),
        ];
    }

    private function getApprovalSummaryDetailed(string $fromDate, string $toDate, array $filters): array
    {
        $query = ApprovalRequest::query()
            ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59']);

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        $stats = $query->selectRaw("
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
            COUNT(CASE WHEN status = 'returned' THEN 1 END) as returned,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
            AVG(CASE WHEN completed_at IS NOT NULL THEN DATEDIFF(completed_at, created_at) END) as avg_processing_days,
            ROUND(COUNT(CASE WHEN status = 'approved' THEN 1 END) * 100.0 / NULLIF(COUNT(CASE WHEN status IN ('approved', 'rejected') THEN 1 END), 0), 1) as approval_rate
        ")->first();

        return [
            'pending' => (int) ($stats->pending ?? 0),
            'approved' => (int) ($stats->approved ?? 0),
            'rejected' => (int) ($stats->rejected ?? 0),
            'returned' => (int) ($stats->returned ?? 0),
            'cancelled' => (int) ($stats->cancelled ?? 0),
            'avg_processing_days' => round($stats->avg_processing_days ?? 0, 1),
            'approval_rate' => round($stats->approval_rate ?? 0, 1),
        ];
    }

    private function getApprovalStatusDistribution(string $fromDate, string $toDate, array $filters): array
    {
        $query = ApprovalRequest::query()
            ->selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59'])
            ->groupBy('status');

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        return $query->get()->map(fn($row) => [
            'status' => $row->status,
            'count' => (int) $row->count,
        ])->toArray();
    }

    private function getApprovalTrend(string $fromDate, string $toDate, array $filters): array
    {
        $query = ApprovalHistory::query()
            ->selectRaw("
                DATE(actioned_at) as date,
                COUNT(CASE WHEN action = 'approved' THEN 1 END) as approved,
                COUNT(CASE WHEN action = 'rejected' THEN 1 END) as rejected,
                COUNT(CASE WHEN action = 'returned' THEN 1 END) as returned
            ")
            ->whereBetween('actioned_at', [$fromDate, $toDate . ' 23:59:59'])
            ->groupBy(DB::raw('DATE(actioned_at)'))
            ->orderBy('date');

        return $query->get()->map(fn($row) => [
            'date' => $row->date,
            'approved' => (int) $row->approved,
            'rejected' => (int) $row->rejected,
            'returned' => (int) $row->returned,
        ])->toArray();
    }

    private function getApprovalsByEntityType(string $fromDate, string $toDate, array $filters): array
    {
        return ApprovalRequest::query()
            ->selectRaw("
                entity_type,
                COUNT(*) as count,
                AVG(CASE WHEN completed_at IS NOT NULL THEN DATEDIFF(completed_at, created_at) END) as avg_days
            ")
            ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59'])
            ->groupBy('entity_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn($row) => [
                'entity_type' => $row->entity_type,
                'count' => (int) $row->count,
                'avg_days' => round($row->avg_days ?? 0, 1),
            ])->toArray();
    }

    private function getApproverPerformance(string $fromDate, string $toDate, array $filters): array
    {
        return ApprovalHistory::query()
            ->selectRaw("
                actioned_by as user_id,
                users.email as user_name,
                COUNT(*) as action_count,
                COUNT(CASE WHEN action = 'approved' THEN 1 END) as approved_count,
                COUNT(CASE WHEN action = 'rejected' THEN 1 END) as rejected_count,
                AVG(DATEDIFF(actioned_at, approval_histories.created_at)) as avg_response_days
            ")
            ->join('users', 'users.id', '=', 'approval_histories.actioned_by')
            ->whereBetween('actioned_at', [$fromDate, $toDate . ' 23:59:59'])
            ->whereNotNull('actioned_by')
            ->groupBy('actioned_by', 'users.email')
            ->orderByDesc('action_count')
            ->limit(10)
            ->get()
            ->map(fn($row) => [
                'user_id' => $row->user_id,
                'user_name' => $row->user_name,
                'action_count' => (int) $row->action_count,
                'approved_count' => (int) $row->approved_count,
                'rejected_count' => (int) $row->rejected_count,
                'avg_response_days' => round($row->avg_response_days ?? 0, 1),
            ])->toArray();
    }

    private function getWorkflowBottlenecks(string $fromDate, string $toDate, array $filters): array
    {
        return ApprovalRequest::query()
            ->selectRaw("
                approval_workflows.name as workflow_name,
                approval_steps.name as step_name,
                AVG(DATEDIFF(COALESCE(approval_requests.completed_at, NOW()), approval_requests.created_at)) as avg_days_at_step,
                COUNT(CASE WHEN approval_requests.status = 'pending' THEN 1 END) as pending_count
            ")
            ->join('approval_workflows', 'approval_workflows.id', '=', 'approval_requests.workflow_id')
            ->join('approval_steps', 'approval_steps.id', '=', 'approval_requests.current_step_id')
            ->whereBetween('approval_requests.created_at', [$fromDate, $toDate . ' 23:59:59'])
            ->groupBy('approval_workflows.name', 'approval_steps.name')
            ->orderByDesc('avg_days_at_step')
            ->limit(10)
            ->get()
            ->map(fn($row) => [
                'workflow_name' => $row->workflow_name,
                'step_name' => $row->step_name,
                'avg_days_at_step' => round($row->avg_days_at_step ?? 0, 1),
                'pending_count' => (int) $row->pending_count,
            ])->toArray();
    }

    // =========================================================================
    // POLICY HELPERS
    // =========================================================================

    private function getPolicySummary(string $fromDate, string $toDate): array
    {
        $stats = Policy::query()
            ->selectRaw("
                COUNT(*) as total_policies,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
                COALESCE(SUM(gross_premium), 0) as total_premium,
                COALESCE(SUM(member_count), 0) as total_members,
                COUNT(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) AND status = 'active' THEN 1 END) as for_renewal_count
            ")
            ->first();

        return [
            'total_policies' => (int) ($stats->total_policies ?? 0),
            'active_count' => (int) ($stats->active_count ?? 0),
            'total_premium' => round($stats->total_premium ?? 0, 2),
            'total_members' => (int) ($stats->total_members ?? 0),
            'for_renewal_count' => (int) ($stats->for_renewal_count ?? 0),
        ];
    }

    private function getPolicySummaryDetailed(array $filters): array
    {
        $query = Policy::query();
        $this->applyPolicyFilters($query, $filters);

        $stats = $query->selectRaw("
            COUNT(*) as total_policies,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_policies,
            COALESCE(SUM(gross_premium), 0) as total_premium,
            COALESCE(SUM(member_count), 0) as total_members,
            COALESCE(AVG(gross_premium), 0) as avg_premium
        ")->first();

        return [
            'total_policies' => (int) ($stats->total_policies ?? 0),
            'active_policies' => (int) ($stats->active_policies ?? 0),
            'total_premium' => round($stats->total_premium ?? 0, 2),
            'total_members' => (int) ($stats->total_members ?? 0),
            'avg_premium' => round($stats->avg_premium ?? 0, 2),
        ];
    }

    private function getPolicyStatusDistribution(array $filters): array
    {
        $query = Policy::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status');

        $this->applyPolicyFilters($query, $filters);

        return $query->get()->map(fn($row) => [
            'status' => $row->status,
            'count' => (int) $row->count,
        ])->toArray();
    }

    private function getPolicyTypeDistribution(array $filters): array
    {
        $query = Policy::query()
            ->selectRaw('policy_type, COUNT(*) as count, COALESCE(SUM(gross_premium), 0) as premium')
            ->groupBy('policy_type');

        $this->applyPolicyFilters($query, $filters);

        return $query->get()->map(fn($row) => [
            'policy_type' => $row->policy_type,
            'count' => (int) $row->count,
            'premium' => round($row->premium ?? 0, 2),
        ])->toArray();
    }

    private function getPremiumBreakdown(array $filters): array
    {
        $query = Policy::query()
            ->selectRaw("
                policy_type,
                COALESCE(SUM(base_premium), 0) as base_premium,
                COALESCE(SUM(addon_premium), 0) as addon_premium,
                COALESCE(SUM(loading_amount), 0) as loading_amount,
                COALESCE(SUM(discount_amount), 0) as discount_amount
            ")
            ->groupBy('policy_type');

        $this->applyPolicyFilters($query, $filters);

        return $query->get()->map(fn($row) => [
            'policy_type' => $row->policy_type,
            'base_premium' => round($row->base_premium ?? 0, 2),
            'addon_premium' => round($row->addon_premium ?? 0, 2),
            'loading_amount' => round($row->loading_amount ?? 0, 2),
            'discount_amount' => round($row->discount_amount ?? 0, 2),
        ])->toArray();
    }

    private function getPolicyMonthlyTrend(string $fromDate, string $toDate, array $filters): array
    {
        $query = Policy::query()
            ->selectRaw("
                DATE_FORMAT(inception_date, '%Y-%m') as month,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as new_policies,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_policies
            ")
            ->whereBetween('inception_date', [$fromDate, $toDate])
            ->groupBy(DB::raw("DATE_FORMAT(inception_date, '%Y-%m')"))
            ->orderBy('month');

        $this->applyPolicyFilters($query, $filters);

        return $query->get()->map(fn($row) => [
            'month' => $row->month,
            'new_policies' => (int) $row->new_policies,
            'cancelled_policies' => (int) $row->cancelled_policies,
            'net_growth' => (int) $row->new_policies - (int) $row->cancelled_policies,
        ])->toArray();
    }

    private function getRenewalPipeline(): array
    {
        $stats = Policy::query()
            ->where('status', 'active')
            ->selectRaw("
                COUNT(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY) THEN 1 END) as expiring_90_days,
                COUNT(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 1 END) as expiring_60_days,
                COUNT(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as expiring_30_days
            ")
            ->first();

        $renewed = Policy::query()
            ->where('status', 'renewed')
            ->whereDate('renewal_date', '>=', Carbon::now()->subDays(90))
            ->count();

        return [
            'expiring_90_days' => (int) ($stats->expiring_90_days ?? 0),
            'expiring_60_days' => (int) ($stats->expiring_60_days ?? 0),
            'expiring_30_days' => (int) ($stats->expiring_30_days ?? 0),
            'renewed' => $renewed,
        ];
    }

    private function getTopPlans(array $filters): array
    {
        $query = Policy::query()
            ->selectRaw("
                plan_id,
                med_plans.name as plan_name,
                COUNT(*) as policy_count,
                COALESCE(SUM(med_policies.member_count), 0) as member_count,
                COALESCE(SUM(med_policies.gross_premium), 0) as total_premium
            ")
            ->join('med_plans', 'med_plans.id', '=', 'med_policies.plan_id')
            ->groupBy('plan_id', 'med_plans.name')
            ->orderByDesc('policy_count')
            ->limit(10);

        $this->applyPolicyFilters($query, $filters);

        return $query->get()->map(fn($row) => [
            'plan_id' => $row->plan_id,
            'plan_name' => $row->plan_name,
            'policy_count' => (int) $row->policy_count,
            'member_count' => (int) $row->member_count,
            'total_premium' => round($row->total_premium ?? 0, 2),
        ])->toArray();
    }

    private function getMemberDistribution(array $filters): array
    {
        $query = Member::query()
            ->selectRaw("
                med_policies.policy_type,
                COUNT(CASE WHEN med_members.member_type = 'principal' THEN 1 END) as principal,
                COUNT(CASE WHEN med_members.member_type = 'spouse' THEN 1 END) as spouse,
                COUNT(CASE WHEN med_members.member_type = 'child' THEN 1 END) as child,
                COUNT(CASE WHEN med_members.member_type = 'parent' THEN 1 END) as parent
            ")
            ->join('med_policies', 'med_policies.id', '=', 'med_members.policy_id')
            ->where('med_members.status', 'active')
            ->groupBy('med_policies.policy_type');

        return $query->get()->map(fn($row) => [
            'policy_type' => $row->policy_type,
            'principal' => (int) $row->principal,
            'spouse' => (int) $row->spouse,
            'child' => (int) $row->child,
            'parent' => (int) $row->parent,
        ])->toArray();
    }

    // =========================================================================
    // BILLING HELPERS
    // =========================================================================

    private function getBillingSummary(string $fromDate, string $toDate): array
    {
        $invoiceStats = Invoice::query()
            ->selectRaw("
                COALESCE(SUM(balance), 0) as total_outstanding,
                COALESCE(SUM(CASE WHEN status = 'overdue' THEN balance ELSE 0 END), 0) as overdue_amount,
                COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count,
                COALESCE(SUM(total_amount), 0) as total_invoiced,
                COALESCE(SUM(paid_amount), 0) as total_collected
            ")
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->first();

        $unallocated = Payment::query()
            ->where('status', 'confirmed')
            ->sum('unallocated_amount');

        $collectionRate = $invoiceStats->total_invoiced > 0
            ? round(($invoiceStats->total_collected / $invoiceStats->total_invoiced) * 100, 1)
            : 0;

        return [
            'total_outstanding' => round($invoiceStats->total_outstanding ?? 0, 2),
            'collection_rate' => $collectionRate,
            'overdue_amount' => round($invoiceStats->overdue_amount ?? 0, 2),
            'overdue_count' => (int) ($invoiceStats->overdue_count ?? 0),
            'unallocated_payments' => round($unallocated ?? 0, 2),
        ];
    }

    private function getBillingSummaryDetailed(string $fromDate, string $toDate, array $filters): array
    {
        $query = Invoice::query()
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->whereNotIn('status', ['draft', 'cancelled']);

        $this->applyBillingFilters($query, $filters);

        $stats = $query->selectRaw("
            COALESCE(SUM(total_amount), 0) as total_invoiced,
            COALESCE(SUM(paid_amount), 0) as total_collected,
            COALESCE(SUM(balance), 0) as total_outstanding,
            COALESCE(SUM(CASE WHEN status = 'overdue' THEN balance ELSE 0 END), 0) as overdue_amount,
            COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count
        ")->first();

        $unallocated = Payment::query()
            ->where('status', 'confirmed')
            ->whereBetween('payment_date', [$fromDate, $toDate])
            ->sum('unallocated_amount');

        $collectionRate = $stats->total_invoiced > 0
            ? round(($stats->total_collected / $stats->total_invoiced) * 100, 1)
            : 0;

        return [
            'total_invoiced' => round($stats->total_invoiced ?? 0, 2),
            'total_collected' => round($stats->total_collected ?? 0, 2),
            'total_outstanding' => round($stats->total_outstanding ?? 0, 2),
            'collection_rate' => $collectionRate,
            'overdue_amount' => round($stats->overdue_amount ?? 0, 2),
            'overdue_count' => (int) ($stats->overdue_count ?? 0),
            'unallocated_payments' => round($unallocated ?? 0, 2),
        ];
    }

    private function getInvoiceStatusDistribution(array $filters): array
    {
        $query = Invoice::query()
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as amount')
            ->groupBy('status');

        $this->applyBillingFilters($query, $filters);

        return $query->get()->map(fn($row) => [
            'status' => $row->status,
            'count' => (int) $row->count,
            'amount' => round($row->amount ?? 0, 2),
        ])->toArray();
    }

    private function getCollectionTrend(string $fromDate, string $toDate, array $filters): array
    {
        $invoices = Invoice::query()
            ->selectRaw("
                DATE_FORMAT(invoice_date, '%Y-%m') as month,
                COALESCE(SUM(total_amount), 0) as invoiced,
                COALESCE(SUM(paid_amount), 0) as collected
            ")
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->groupBy(DB::raw("DATE_FORMAT(invoice_date, '%Y-%m')"))
            ->orderBy('month')
            ->get();

        return $invoices->map(fn($row) => [
            'month' => $row->month,
            'invoiced' => round($row->invoiced ?? 0, 2),
            'collected' => round($row->collected ?? 0, 2),
            'collection_rate' => $row->invoiced > 0 ? round(($row->collected / $row->invoiced) * 100, 1) : 0,
        ])->toArray();
    }

    private function getAgingAnalysis(array $filters): array
    {
        $query = Invoice::query()
            ->selectRaw("
                CASE
                    WHEN days_overdue <= 0 THEN 'current'
                    WHEN days_overdue BETWEEN 1 AND 30 THEN '1-30'
                    WHEN days_overdue BETWEEN 31 AND 60 THEN '31-60'
                    WHEN days_overdue BETWEEN 61 AND 90 THEN '61-90'
                    ELSE '90+'
                END as bucket,
                COUNT(*) as count,
                COALESCE(SUM(balance), 0) as amount
            ")
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->where('balance', '>', 0)
            ->groupBy('bucket')
            ->orderByRaw("FIELD(bucket, 'current', '1-30', '31-60', '61-90', '90+')");

        $this->applyBillingFilters($query, $filters);

        return $query->get()->map(fn($row) => [
            'bucket' => $row->bucket,
            'count' => (int) $row->count,
            'amount' => round($row->amount ?? 0, 2),
        ])->toArray();
    }

    private function getPaymentMethodDistribution(string $fromDate, string $toDate, array $filters): array
    {
        $query = Payment::query()
            ->selectRaw('payment_method as method, COUNT(*) as count, COALESCE(SUM(amount), 0) as amount')
            ->where('status', 'confirmed')
            ->whereBetween('payment_date', [$fromDate, $toDate])
            ->groupBy('payment_method');

        $total = $query->get()->sum('amount');

        return $query->get()->map(fn($row) => [
            'method' => $row->method,
            'count' => (int) $row->count,
            'amount' => round($row->amount ?? 0, 2),
            'percentage' => $total > 0 ? round(($row->amount / $total) * 100, 1) : 0,
        ])->toArray();
    }

    private function getTopOutstanding(array $filters): array
    {
        $query = Invoice::query()
            ->selectRaw("
                med_invoices.policy_id,
                med_policies.policy_number,
                COALESCE(med_policies.holder_name, med_corporate_groups.name) as holder_name,
                SUM(med_invoices.balance) as outstanding,
                MAX(med_invoices.days_overdue) as days_overdue
            ")
            ->leftJoin('med_policies', 'med_policies.id', '=', 'med_invoices.policy_id')
            ->leftJoin('med_corporate_groups', 'med_corporate_groups.id', '=', 'med_invoices.group_id')
            ->whereIn('med_invoices.status', ['sent', 'partially_paid', 'overdue'])
            ->where('med_invoices.balance', '>', 0)
            ->groupBy('med_invoices.policy_id', 'med_policies.policy_number', DB::raw('COALESCE(med_policies.holder_name, med_corporate_groups.name)'))
            ->orderByDesc('outstanding')
            ->limit(10);

        return $query->get()->map(fn($row) => [
            'policy_id' => $row->policy_id,
            'policy_number' => $row->policy_number ?? 'N/A',
            'holder_name' => $row->holder_name ?? 'Unknown',
            'outstanding' => round($row->outstanding ?? 0, 2),
            'days_overdue' => (int) ($row->days_overdue ?? 0),
        ])->toArray();
    }

    private function getDailyCollections(array $filters): array
    {
        $fromDate = Carbon::now()->subDays(30)->toDateString();
        $toDate = Carbon::now()->toDateString();

        return Payment::query()
            ->selectRaw("
                DATE(payment_date) as date,
                COALESCE(SUM(amount), 0) as amount,
                COUNT(*) as count
            ")
            ->where('status', 'confirmed')
            ->whereBetween('payment_date', [$fromDate, $toDate])
            ->groupBy(DB::raw('DATE(payment_date)'))
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'date' => $row->date,
                'amount' => round($row->amount ?? 0, 2),
                'count' => (int) $row->count,
            ])->toArray();
    }

    // =========================================================================
    // FILTER HELPERS
    // =========================================================================

    private function applyPolicyFilters($query, array $filters): void
    {
        if (!empty($filters['scheme_id'])) {
            $query->where('scheme_id', $filters['scheme_id']);
        }
        if (!empty($filters['plan_id'])) {
            $query->where('plan_id', $filters['plan_id']);
        }
        if (!empty($filters['policy_type'])) {
            $query->where('policy_type', $filters['policy_type']);
        }
        if (!empty($filters['group_id'])) {
            $query->where('group_id', $filters['group_id']);
        }
    }

    private function applyBillingFilters($query, array $filters): void
    {
        if (!empty($filters['policy_id'])) {
            $query->where('policy_id', $filters['policy_id']);
        }
        if (!empty($filters['group_id'])) {
            $query->where('group_id', $filters['group_id']);
        }
    }
}
