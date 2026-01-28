<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice - {{ $invoice->invoice_number }}</title>
    <style>
        /* MODERN PDF STYLING (DomPDF Optimized) */
        @page {
            margin: 1cm 1.5cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 9pt;
            color: #334155;
            line-height: 1.5;
        }

        /* UTILITIES */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; letter-spacing: 0.5px; }
        .text-blue { color: #1e3a8a; }
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
            color: #cbd5e1;
            text-align: right;
            text-transform: uppercase;
        }

        /* STATUS BADGE */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-draft { background-color: #f1f5f9; color: #64748b; }
        .status-sent { background-color: #dbeafe; color: #1e40af; }
        .status-overdue { background-color: #fee2e2; color: #dc2626; }
        .status-paid { background-color: #d1fae5; color: #047857; }

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

        /* DATA TABLES */
        .modern-table {
            width: 100%;
            margin-bottom: 20px;
        }

        .modern-table th {
            text-align: left;
            padding: 10px 8px;
            color: #64748b;
            font-size: 8pt;
            text-transform: uppercase;
            border-bottom: 2px solid #e2e8f0;
            font-weight: bold;
            background-color: #f8fafc;
        }

        .modern-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 9pt;
            color: #334155;
        }

        .modern-table tr:last-child td {
            border-bottom: none;
        }

        .modern-table .amount-col {
            text-align: right;
            font-weight: 600;
        }

        /* FINANCIAL SUMMARY */
        .summary-box {
            width: 45%;
            float: right;
            margin-top: 20px;
        }

        .summary-row {
            padding: 6px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            color: #64748b;
            font-size: 9pt;
        }

        .summary-value {
            text-align: right;
            color: #1e293b;
            font-weight: 600;
            font-size: 9pt;
        }

        .total-row {
            background-color: #f8fafc;
            padding: 12px 8px;
            border-radius: 6px;
            margin-top: 10px;
        }

        .total-label {
            font-size: 10pt;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
        }

        .total-value {
            font-size: 16pt;
            font-weight: 800;
            color: #1e3a8a;
            text-align: right;
        }

        /* PAYMENT INFO BOX */
        .payment-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .payment-box h4 {
            font-size: 9pt;
            color: #1e3a8a;
            text-transform: uppercase;
            margin: 0 0 10px 0;
            font-weight: bold;
        }

        .payment-detail {
            font-size: 9pt;
            color: #334155;
            margin-bottom: 5px;
        }

        .payment-detail strong {
            color: #1e293b;
        }

        /* NOTES */
        .notes-section {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .notes-section h4 {
            font-size: 9pt;
            color: #64748b;
            text-transform: uppercase;
            margin: 0 0 8px 0;
        }

        .notes-content {
            font-size: 9pt;
            color: #64748b;
            font-style: italic;
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

        /* OVERDUE ALERT */
        .overdue-alert {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 10px 15px;
            margin-bottom: 20px;
            color: #dc2626;
            font-size: 9pt;
            font-weight: 600;
        }
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
                    <div class="document-label">Invoice</div>
                    <div class="text-right" style="font-size: 10pt; font-weight: bold; color: #1e3a8a; margin-top: 5px;">
                        {{ $invoice->invoice_number }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    @if($invoice->is_overdue)
    <div class="overdue-alert">
        <span style="font-size: 11pt;">!</span> This invoice is {{ $invoice->days_overdue }} days overdue. Please settle immediately to avoid service interruption.
    </div>
    @endif

    <table class="w-100 mb-4">
        <tr>
            <td class="w-50">
                <div class="info-label">Bill To</div>
                <div class="info-value">{{ $invoice->bill_to_name }}</div>

                @if($invoice->bill_to_email)
                <div class="info-label">Email</div>
                <div class="info-value" style="font-weight: normal; font-size: 9pt;">
                    {{ $invoice->bill_to_email }}
                </div>
                @endif

                @if($invoice->bill_to_address)
                <div class="info-label">Address</div>
                <div class="info-value" style="font-weight: normal; font-size: 9pt;">
                    {{ $invoice->bill_to_address }}
                </div>
                @endif
            </td>
            <td class="w-50">
                <div class="info-label">Invoice Date</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</div>

                <div class="info-label">Due Date</div>
                <div class="info-value" @if($invoice->is_overdue) style="color: #dc2626;" @endif>
                    {{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}
                </div>

                @if($invoice->billing_period_start && $invoice->billing_period_end)
                <div class="info-label">Billing Period</div>
                <div class="info-value" style="font-weight: normal; font-size: 9pt;">
                    {{ \Carbon\Carbon::parse($invoice->billing_period_start)->format('d M Y') }} -
                    {{ \Carbon\Carbon::parse($invoice->billing_period_end)->format('d M Y') }}
                </div>
                @endif
            </td>
        </tr>
    </table>

    @if($invoice->policy)
    <table class="w-100 mb-4" style="background-color: #f8fafc; border-radius: 6px; padding: 10px;">
        <tr>
            <td style="padding: 10px;">
                <span class="info-label">Policy Number</span>
                <div style="font-size: 10pt; font-weight: 600; color: #1e293b;">{{ $invoice->policy->policy_number }}</div>
            </td>
            <td style="padding: 10px;">
                <span class="info-label">Plan</span>
                <div style="font-size: 10pt; font-weight: 600; color: #1e293b;">{{ $invoice->policy->plan?->name ?? '-' }}</div>
            </td>
            <td style="padding: 10px;">
                <span class="info-label">Members</span>
                <div style="font-size: 10pt; font-weight: 600; color: #1e293b;">{{ $invoice->policy->active_members_count ?? '-' }}</div>
            </td>
        </tr>
    </table>
    @endif

    <div class="mt-4">
        <h3 class="uppercase text-blue" style="font-size: 10pt; border-bottom: 2px solid #f1f5f9; padding-bottom: 5px; margin-bottom: 10px;">
            Invoice Details
        </h3>

        <table class="modern-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Description</th>
                    <th class="text-center" style="width: 15%;">Qty</th>
                    <th class="text-right" style="width: 17%;">Unit Price</th>
                    <th class="text-right" style="width: 18%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                <tr>
                    <td>
                        <span class="text-bold">{{ $item->description }}</span>
                        @if($item->item_type === 'discount')
                        <span class="text-green" style="font-size: 8pt;"> (Discount)</span>
                        @endif
                    </td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="amount-col @if($item->amount < 0) text-green @endif">
                        {{ $item->amount < 0 ? '-' : '' }}{{ $invoice->currency }} {{ number_format(abs($item->amount), 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="clear: both; overflow: hidden;">
        <div class="summary-box">
            <table style="width: 100%;">
                <tr class="summary-row">
                    <td class="summary-label">Subtotal</td>
                    <td class="summary-value">{{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}</td>
                </tr>

                @if($invoice->tax_amount > 0)
                <tr class="summary-row">
                    <td class="summary-label">Tax / VAT</td>
                    <td class="summary-value">{{ $invoice->currency }} {{ number_format($invoice->tax_amount, 2) }}</td>
                </tr>
                @endif

                <tr>
                    <td colspan="2">
                        <div class="total-row">
                            <table style="width: 100%;">
                                <tr>
                                    <td class="total-label">Total Due</td>
                                    <td class="total-value">
                                        <span style="font-size: 10pt; font-weight: 600;">{{ $invoice->currency }}</span>
                                        {{ number_format($invoice->total_amount, 2) }}
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>

                @if($invoice->paid_amount > 0)
                <tr class="summary-row" style="margin-top: 10px;">
                    <td class="summary-label text-green">Amount Paid</td>
                    <td class="summary-value text-green">- {{ $invoice->currency }} {{ number_format($invoice->paid_amount, 2) }}</td>
                </tr>
                <tr class="summary-row">
                    <td class="summary-label text-bold" style="color: #dc2626;">Balance Due</td>
                    <td class="summary-value text-bold" style="color: #dc2626; font-size: 12pt;">
                        {{ $invoice->currency }} {{ number_format($invoice->balance, 2) }}
                    </td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    <div style="clear: both;"></div>

    <div class="payment-box no-break">
        <h4>Payment Information</h4>
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%; padding-right: 20px;">
                    <div class="payment-detail"><strong>Bank:</strong> {{ config('medical.payment.bank_name', 'First National Bank') }}</div>
                    <div class="payment-detail"><strong>Account Name:</strong> {{ config('medical.payment.account_name', 'FlexCare Insurance Ltd') }}</div>
                    <div class="payment-detail"><strong>Account Number:</strong> {{ config('medical.payment.account_number', '1234567890') }}</div>
                </td>
                <td style="width: 50%;">
                    <div class="payment-detail"><strong>Branch:</strong> {{ config('medical.payment.branch', 'Main Branch') }}</div>
                    <div class="payment-detail"><strong>Swift Code:</strong> {{ config('medical.payment.swift_code', 'FNBZZALX') }}</div>
                    <div class="payment-detail"><strong>Reference:</strong> {{ $invoice->invoice_number }}</div>
                </td>
            </tr>
        </table>
        <div style="margin-top: 10px; font-size: 8pt; color: #64748b;">
            Please use your invoice number as payment reference to ensure correct allocation.
        </div>
    </div>

    @if($invoice->notes)
    <div class="notes-section">
        <h4>Notes</h4>
        <p class="notes-content">{{ $invoice->notes }}</p>
    </div>
    @endif

    <div class="footer">
        <table width="100%">
            <tr>
                <td width="60%">
                    <strong>FlexCare Insurance Ltd</strong><br>
                    Payment Terms: {{ config('medical.payment.terms', 'Net 30 days') }}. Late payments may result in service suspension.
                </td>
                <td width="40%" class="text-right">
                    Generated: {{ now()->format('d M Y H:i') }}
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
