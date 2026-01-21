<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Quote - {{ $application->application_number }}</title>
    <style>
        /* DomPDF Compatible Styles */
        @page {
            margin: 0;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .header {
            background-color: #1e3a8a;
            color: #ffffff;
            padding: 20px 30px;
        }

        .header-table {
            width: 100%;
        }

        .brand-name {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 1px;
            color: #ffffff;
        }

        .brand-tagline {
            font-size: 9px;
            color: #cbd5e1;
            margin-top: 2px;
        }

        .quote-ref-box {
            background-color: #ffffff;
            padding: 8px 12px;
            text-align: center;
        }

        .quote-ref-label {
            font-size: 7px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .quote-ref-value {
            font-size: 11px;
            font-weight: bold;
            color: #1e3a8a;
        }

        .accent-bar {
            height: 4px;
            background-color: #10b981;
        }

        .content {
            padding: 25px 30px;
        }

        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }

        .info-cell {
            width: 48%;
            vertical-align: top;
            padding: 12px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .info-label {
            font-size: 8px;
            color: #666;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 11px;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 8px;
        }

        .info-value-normal {
            font-size: 9px;
            color: #333;
            margin-bottom: 6px;
        }

        .info-value-red {
            color: #dc2626;
        }

        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
            padding-bottom: 5px;
            border-bottom: 2px solid #1e3a8a;
            margin-bottom: 10px;
            margin-top: 15px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .data-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            text-align: left;
            padding: 8px 10px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .data-table th.text-right {
            text-align: right;
        }

        .data-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9px;
        }

        .data-table tr.even td {
            background-color: #f8fafc;
        }

        .text-right {
            text-align: right;
        }

        .text-bold {
            font-weight: bold;
        }

        .text-green {
            color: #16a34a;
        }

        .premium-section {
            width: 100%;
            margin-top: 20px;
        }

        .premium-box {
            width: 250px;
            float: right;
            border: 1px solid #e2e8f0;
        }

        .premium-row {
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }

        .premium-row:last-child {
            border-bottom: none;
        }

        .premium-label {
            font-size: 8px;
            color: #666;
        }

        .premium-value {
            font-size: 9px;
            font-weight: bold;
            color: #333;
            float: right;
        }

        .premium-total-row {
            padding: 12px;
            background-color: #1e3a8a;
        }

        .premium-total-label {
            font-size: 10px;
            font-weight: bold;
            color: #ffffff;
        }

        .premium-total-value {
            font-size: 12px;
            font-weight: bold;
            color: #ffffff;
            float: right;
        }

        .discount-text {
            color: #16a34a;
        }

        .clearfix:after {
            content: "";
            display: table;
            clear: both;
        }

        .terms {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            font-size: 7px;
            color: #666;
            font-style: italic;
            line-height: 1.5;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px 30px;
            border-top: 1px solid #e2e8f0;
            font-size: 7px;
            color: #999;
            background-color: #fff;
        }

        .footer-left {
            float: left;
        }

        .footer-right {
            float: right;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <table class="header-table" cellpadding="0" cellspacing="0">
            <tr>
                <td style="vertical-align: middle;">
                    <div class="brand-name">FLEXCARE</div>
                    <div class="brand-tagline">Medical Insurance Solutions</div>
                </td>
                <td style="text-align: right; vertical-align: middle;">
                    <div class="quote-ref-box">
                        <div class="quote-ref-label">Quote Reference</div>
                        <div class="quote-ref-value">{{ $application->application_number }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Accent Bar -->
    <div class="accent-bar"></div>

    <!-- Content -->
    <div class="content">
        <!-- Info Section -->
        <table class="info-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="info-cell">
                    <div class="info-label">Prepared For</div>
                    <div class="info-value">{{ $application->applicant_name ?? 'Valued Customer' }}</div>

                    <div class="info-label">Email</div>
                    <div class="info-value-normal">{{ $application->contact_email ?? '-' }}</div>

                    <div class="info-label">Phone</div>
                    <div class="info-value-normal">{{ $application->contact_phone ?? '-' }}</div>

                    <div class="info-label">Plan</div>
                    <div class="info-value-normal">{{ $application->scheme->name ?? '' }} - {{ $application->plan->name ?? '' }}</div>
                </td>
                <td style="width: 4%;"></td>
                <td class="info-cell">
                    <div class="info-label">Date Issued</div>
                    <div class="info-value">{{ \Carbon\Carbon::parse($application->quote_date ?? now())->format('F j, Y') }}</div>

                    <div class="info-label">Valid Until</div>
                    <div class="info-value info-value-red">{{ \Carbon\Carbon::parse($application->quote_valid_until ?? now()->addDays(30))->format('F j, Y') }}</div>

                    <div class="info-label">Policy Term</div>
                    <div class="info-value-normal">{{ $application->policy_term_months ?? 12 }} Months</div>

                    <div class="info-label">Coverage Period</div>
                    <div class="info-value-normal">
                        {{ \Carbon\Carbon::parse($application->proposed_start_date)->format('M j, Y') }} -
                        {{ \Carbon\Carbon::parse($application->proposed_end_date ?? $application->proposed_start_date)->addMonths($application->policy_term_months ?? 12)->format('M j, Y') }}
                    </div>
                </td>
            </tr>
        </table>

        <!-- Covered Members -->
        <div class="section-title">Covered Members</div>
        <table class="data-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 30%;">Member Name</th>
                    <th style="width: 20%;">Relationship</th>
                    <th style="width: 20%;">Age / Gender</th>
                    <th style="width: 15%;">Status</th>
                    <th style="width: 15%;" class="text-right">Premium</th>
                </tr>
            </thead>
            <tbody>
                @forelse($application->members as $index => $member)
                <tr class="{{ $index % 2 == 1 ? 'even' : '' }}">
                    <td class="text-bold">{{ $member->first_name }} {{ $member->last_name }}</td>
                    <td>{{ ucfirst($member->relationship ?? 'Principal') }}</td>
                    <td>{{ \Carbon\Carbon::parse($member->date_of_birth)->age }} Yrs / {{ ucfirst(substr($member->gender ?? 'U', 0, 1)) }}</td>
                    <td class="text-green text-bold">Covered</td>
                    <td class="text-right text-bold">{{ $application->currency ?? 'ZMW' }} {{ number_format($member->base_premium ?? 0, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #999;">No members defined</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if($application->addons && $application->addons->count() > 0)
        <!-- Add-ons -->
        <div class="section-title">Selected Add-ons</div>
        <table class="data-table" cellpadding="0" cellspacing="0">
            <thead>
                <tr>
                    <th>Add-on Description</th>
                    <th style="width: 20%;" class="text-right">Premium</th>
                </tr>
            </thead>
            <tbody>
                @foreach($application->addons as $index => $appAddon)
                <tr class="{{ $index % 2 == 1 ? 'even' : '' }}">
                    <td>{{ $appAddon->addon->name ?? 'Add-on' }}</td>
                    <td class="text-right text-bold">{{ $application->currency ?? 'ZMW' }} {{ number_format($appAddon->premium ?? 0, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <!-- Premium Summary -->
        <div class="premium-section clearfix">
            <div class="premium-box">
                <div class="premium-row">
                    <span class="premium-label">Billing Frequency</span>
                    <span class="premium-value">{{ ucfirst($application->billing_frequency ?? 'Annual') }}</span>
                    <div style="clear: both;"></div>
                </div>
                <div class="premium-row">
                    <span class="premium-label">Base Premium</span>
                    <span class="premium-value">{{ $application->currency ?? 'ZMW' }} {{ number_format($application->base_premium ?? 0, 2) }}</span>
                    <div style="clear: both;"></div>
                </div>

                @if(($application->addon_premium ?? 0) > 0)
                <div class="premium-row">
                    <span class="premium-label">Add-ons</span>
                    <span class="premium-value">{{ $application->currency ?? 'ZMW' }} {{ number_format($application->addon_premium, 2) }}</span>
                    <div style="clear: both;"></div>
                </div>
                @endif

                @if(($application->loading_amount ?? 0) > 0)
                <div class="premium-row">
                    <span class="premium-label">Risk Loadings</span>
                    <span class="premium-value">{{ $application->currency ?? 'ZMW' }} {{ number_format($application->loading_amount, 2) }}</span>
                    <div style="clear: both;"></div>
                </div>
                @endif

                @if(($application->discount_amount ?? 0) > 0)
                <div class="premium-row">
                    <span class="premium-label discount-text">Discounts</span>
                    <span class="premium-value discount-text">-{{ $application->currency ?? 'ZMW' }} {{ number_format($application->discount_amount, 2) }}</span>
                    <div style="clear: both;"></div>
                </div>
                @endif

                @if(($application->tax_amount ?? 0) > 0)
                <div class="premium-row">
                    <span class="premium-label">Tax / VAT</span>
                    <span class="premium-value">{{ $application->currency ?? 'ZMW' }} {{ number_format($application->tax_amount, 2) }}</span>
                    <div style="clear: both;"></div>
                </div>
                @endif

                <div class="premium-total-row">
                    <span class="premium-total-label">TOTAL PAYABLE</span>
                    <span class="premium-total-value">{{ $application->currency ?? 'ZMW' }} {{ number_format($application->gross_premium ?? 0, 2) }}</span>
                    <div style="clear: both;"></div>
                </div>
            </div>
        </div>

        <!-- Terms -->
        <div class="terms" style="clear: both; margin-top: 80px;">
            <strong>Terms & Conditions:</strong> This quote is subject to the standard terms and conditions of the Medical Insurance Policy.
            Final acceptance is subject to underwriting approval. Premium rates are subject to change based on underwriting assessment.
            This quote does not constitute a contract of insurance until accepted and payment is received.
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <span class="footer-left">Generated on {{ now()->format('M j, Y g:i A') }}</span>
        <span class="footer-right">FlexCare Medical Insurance</span>
    </div>
</body>
</html>
