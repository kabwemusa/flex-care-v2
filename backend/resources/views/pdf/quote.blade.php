<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Quote - {{ $application->application_number }}</title>
    <style>
        /* * MODERN PDF STYLING (DomPDF Optimized)
         * Philosophy: Whitespace > Borders. Clean Typography.
         */
        
        @page {
            margin: 1cm 1.5cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9pt;
            color: #334155; /* Slate 700 - Softer than black */
            line-height: 1.5;
        }

        /* UTILITIES */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; letter-spacing: 0.5px; }
        .text-blue { color: #1e3a8a; } /* Brand Blue */
        .text-red { color: #ef4444; }
        .text-green { color: #10b981; }
        .text-muted { color: #94a3b8; }
        
        .w-100 { width: 100%; }
        .w-50 { width: 50%; }
        .mb-4 { margin-bottom: 1.5rem; }
        .mt-4 { margin-top: 1.5rem; }

        /* LAYOUT TABLES */
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }

        /* HEADER */
        .header-divider {
            border-bottom: 2px solid #1e3a8a;
            margin-bottom: 30px;
            padding-bottom: 15px;
        }
        
        .brand-name {
            font-size: 20pt;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: -0.5px;
        }

        .document-label {
            font-size: 16pt;
            font-weight: 300;
            color: #cbd5e1; /* Light gray */
            text-align: right;
            text-transform: uppercase;
        }

        /* CLIENT INFO SECTION */
        .info-label {
            font-size: 7pt;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 10pt;
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 15px;
        }

        /* DATA TABLES (The Modern Look) */
        .modern-table {
            width: 100%;
            margin-bottom: 20px;
        }

        .modern-table th {
            text-align: left;
            padding: 8px 0;
            color: #64748b;
            font-size: 8pt;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e1;
            font-weight: bold;
        }

        .modern-table td {
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9; /* Very subtle divider */
            font-size: 9pt;
            color: #334155;
        }

        .modern-table tr:last-child td {
            border-bottom: none;
        }

        /* FINANCIAL SUMMARY BOX */
        .financial-container {
            width: 100%;
            margin-top: 20px;
            page-break-inside: avoid;
        }

        /* We use a table for the summary to keep alignment perfect */
        .summary-table {
            width: 45%; /* Keep it compact on the right */
            float: right; 
        }

        .summary-row td {
            padding: 5px 0;
            font-size: 9pt;
        }

        .summary-label {
            color: #64748b;
        }

        .summary-value {
            text-align: right;
            color: #1e293b;
            font-weight: bold;
        }

        .total-row td {
            padding-top: 15px;
            padding-bottom: 15px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }

        .total-label {
            font-size: 10pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
        }

        .total-value {
            font-size: 14pt;
            font-weight: 800;
            color: #1e3a8a;
            text-align: right;
        }

        /* FOOTER */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 7pt;
            color: #94a3b8;
        }

        /* PAGE BREAK UTILS */
        .page-break { page-break-after: always; }
        .no-break { page-break-inside: avoid; }

    </style>
</head>
<body>

    <div class="header-divider">
        <table style="width: 100%;">
            <tr>
                <td style="vertical-align: bottom;">
                    <div class="brand-name">FLEXCARE</div>
                    <div style="font-size: 8pt; color: #64748b; margin-top: -5px;">Medical Insurance Solutions</div>
                </td>
                <td style="vertical-align: bottom;">
                    <div class="document-label">Quotation</div>
                    <div class="text-right" style="font-size: 9pt; font-weight: bold; color: #1e3a8a; margin-top: 5px;">
                        #{{ $application->application_number }}
                    </div>
                    <div class="text-right" style="font-size: 8pt; color: #94a3b8;">
                        Date: {{ now()->format('d M Y') }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="w-100 mb-4">
        <tr>
            <td class="w-50">
                <div class="info-label">Prepared For</div>
                <div class="info-value">{{ $application->applicant_name }}</div>
                
                <div class="info-label">Contact Details</div>
                <div class="info-value" style="font-weight: normal; font-size: 9pt;">
                    {{ $application->contact_email }}<br>
                    {{ $application->contact_phone }}
                </div>
            </td>
            <td class="w-50">
                <div class="info-label">Scheme & Plan</div>
                <div class="info-value">{{ $application->scheme->name ?? '' }} — {{ $application->plan->name ?? '' }}</div>

                <div class="info-label">Coverage Period</div>
                <div class="info-value">
                    {{ \Carbon\Carbon::parse($application->proposed_start_date)->format('d M Y') }} to 
                    {{ \Carbon\Carbon::parse($application->proposed_end_date)->format('d M Y') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="mt-4">
        <h3 class="uppercase text-blue" style="font-size: 10pt; border-bottom: 2px solid #f1f5f9; padding-bottom: 5px; margin-bottom: 10px;">
            Coverage Summary
        </h3>

        @if($application->members->count() > 50)
            <table class="modern-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Member Category</th>
                        <th class="text-center">Count</th>
                        <th class="text-right">Avg Age</th>
                        <th class="text-right">Base Premium</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($application->members->groupBy('relationship') as $rel => $group)
                    <tr>
                        <td>
                            <span class="text-bold text-blue">{{ ucfirst($rel) }}s</span>
                        </td>
                        <td class="text-center">{{ $group->count() }}</td>
                        <td class="text-right">{{ round($group->avg(fn($m) => \Carbon\Carbon::parse($m->date_of_birth)->age)) }} yrs</td>
                        <td class="text-right text-bold">
                            {{ number_format($group->sum('base_premium'), 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="font-size: 8pt; color: #94a3b8; font-style: italic; margin-top: -10px;">
                * Complete member schedule available in the attached CSV file.
            </div>
        @else
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Member Name</th>
                        <th>Relationship</th>
                        <th>Age</th>
                        <th class="text-right">Premium</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($application->members as $member)
                    <tr>
                        <td>{{ $member->first_name }} {{ $member->last_name }}</td>
                        <td>{{ ucfirst($member->relationship) }}</td>
                        <td>{{ \Carbon\Carbon::parse($member->date_of_birth)->age }}</td>
                        <td class="text-right">{{ number_format($member->base_premium, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($application->addons && $application->addons->count() > 0)
    <div class="mt-4 no-break">
        <h3 class="uppercase text-blue" style="font-size: 10pt; border-bottom: 2px solid #f1f5f9; padding-bottom: 5px; margin-bottom: 10px;">
            Selected Add-ons
        </h3>
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($application->addons as $addon)
                <tr>
                    <td>{{ $addon->addon->name }}</td>
                    <td class="text-right">{{ number_format($addon->premium, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="financial-container">
        <table class="summary-table">
            <tr class="summary-row">
                <td class="summary-label">Base Premium</td>
                <td class="summary-value">{{ number_format($application->base_premium, 2) }}</td>
            </tr>

            @if(($application->addon_premium ?? 0) > 0)
            <tr class="summary-row">
                <td class="summary-label">Total Add-ons</td>
                <td class="summary-value">{{ number_format($application->addon_premium, 2) }}</td>
            </tr>
            @endif

            @if(($application->loading_amount ?? 0) > 0)
            <tr class="summary-row">
                <td class="summary-label">
                    Risk Loading / Adjustment
                    <div style="font-size: 7pt; color: #ef4444;">(Applied based on underwriting)</div>
                </td>
                <td class="summary-value text-red">
                    + {{ number_format($application->loading_amount, 2) }}
                </td>
            </tr>
            @endif

            @if(($application->discount_amount ?? 0) > 0)
            <tr class="summary-row">
                <td class="summary-label text-green">Discount</td>
                <td class="summary-value text-green">
                    - {{ number_format($application->discount_amount, 2) }}
                </td>
            </tr>
            @endif

            <tr class="summary-row">
                <td class="summary-label">Tax / Levies</td>
                <td class="summary-value">{{ number_format($application->tax_amount, 2) }}</td>
            </tr>

            <tr><td colspan="2" style="height: 10px;"></td></tr>

            <tr class="total-row">
                <td class="total-label">Total Payable</td>
                <td class="total-value">
                    <span style="font-size: 10pt; font-weight: 600;">{{ $application->currency ?? 'ZMW' }}</span> 
                    {{ number_format($application->gross_premium, 2) }}
                </td>
            </tr>
            <tr>
                <td colspan="2" class="text-right" style="font-size: 8pt; color: #94a3b8; padding-top: 5px;">
                    {{ ucfirst($application->billing_frequency) }} Payment
                </td>
            </tr>
        </table>
        <div style="clear: both;"></div>
    </div>

    <div class="footer">
        <table width="100%">
            <tr>
                <td width="60%">
                    <strong>FlexCare Insurance Ltd</strong><br>
                    Terms & Conditions apply. This quote is valid for 30 days.
                </td>
                <td width="40%" class="text-right">
                    Page <span class="page-number"></span>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>