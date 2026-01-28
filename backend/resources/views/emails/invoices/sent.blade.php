<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style type="text/css">
        /* RESET */
        body { margin: 0; padding: 0; background-color: #f3f4f6; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; color: #1f2937; }
        table { border-collapse: collapse !important; width: 100%; }
        a { text-decoration: none; }

        /* UTILITIES */
        .wrapper { background-color: #f3f4f6; padding: 40px 0; }
        .container { background-color: #ffffff; max-width: 600px; margin: 0 auto; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .text-muted { color: #6b7280; font-size: 14px; }
        .text-dark { color: #111827; font-size: 14px; font-weight: 500; }
        .text-red { color: #ef4444; }
        .text-green { color: #10b981; }

        /* RESPONSIVE */
        @media only screen and (max-width: 600px) {
            .wrapper { padding: 10px; }
            .content { padding: 20px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6;">

    <div class="wrapper">
        <table align="center" border="0" cellpadding="0" cellspacing="0" class="container" width="600" style="background-color: #ffffff;">

            <!-- Header -->
            <tr>
                <td style="padding: 30px 40px; border-bottom: 1px solid #f3f4f6;">
                    <table border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="font-size: 20px; font-weight: 800; color: #1e3a8a; letter-spacing: -0.5px;">FLEXCARE</td>
                            <td align="right" style="color: #9ca3af; font-size: 12px; font-weight: 500;">
                                INVOICE
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <!-- Content -->
            <tr>
                <td class="content" style="padding: 40px;">

                    <!-- Greeting -->
                    <p style="margin: 0 0 15px 0; font-size: 16px; color: #374151;">
                        Dear {{ $invoice->bill_to_name }},
                    </p>
                    <p style="margin: 0 0 30px 0; font-size: 15px; color: #6b7280; line-height: 1.5;">
                        Please find attached your invoice for medical insurance services. Below is a summary of the charges.
                    </p>

                    <!-- Invoice Info Box -->
                    <table border="0" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; border-radius: 8px; margin-bottom: 25px;">
                        <tr>
                            <td style="padding: 20px;">
                                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                    <tr>
                                        <td style="padding-bottom: 10px;">
                                            <span style="font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: bold;">Invoice Number</span><br>
                                            <span style="font-size: 16px; font-weight: 700; color: #1e3a8a;">{{ $invoice->invoice_number }}</span>
                                        </td>
                                        <td align="right" style="padding-bottom: 10px;">
                                            <span style="font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: bold;">Due Date</span><br>
                                            <span style="font-size: 16px; font-weight: 600; color: {{ $invoice->is_overdue ? '#dc2626' : '#1e293b' }};">
                                                {{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}
                                            </span>
                                        </td>
                                    </tr>
                                    @if($invoice->policy)
                                    <tr>
                                        <td colspan="2" style="padding-top: 10px; border-top: 1px solid #e2e8f0;">
                                            <span style="font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: bold;">Policy</span><br>
                                            <span style="font-size: 14px; color: #334155;">{{ $invoice->policy->policy_number }}</span>
                                        </td>
                                    </tr>
                                    @endif
                                </table>
                            </td>
                        </tr>
                    </table>

                    @if($invoice->is_overdue)
                    <!-- Overdue Alert -->
                    <table border="0" cellpadding="0" cellspacing="0" style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; margin-bottom: 25px;">
                        <tr>
                            <td style="padding: 15px;">
                                <table border="0" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="color: #dc2626; font-size: 14px; font-weight: 600;">
                                            <span style="font-size: 16px;">!</span> This invoice is {{ $invoice->days_overdue }} days overdue
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="color: #b91c1c; font-size: 13px; padding-top: 5px;">
                                            Please settle immediately to avoid service interruption.
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                    @endif

                    <!-- Amount Summary -->
                    <table border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
                        @if($invoice->billing_period_start && $invoice->billing_period_end)
                        <tr>
                            <td style="padding-bottom: 12px;" class="text-muted">Billing Period</td>
                            <td align="right" style="padding-bottom: 12px;" class="text-dark">
                                {{ \Carbon\Carbon::parse($invoice->billing_period_start)->format('d M') }} -
                                {{ \Carbon\Carbon::parse($invoice->billing_period_end)->format('d M Y') }}
                            </td>
                        </tr>
                        @endif

                        <tr>
                            <td style="padding-bottom: 12px;" class="text-muted">Subtotal</td>
                            <td align="right" style="padding-bottom: 12px;" class="text-dark">
                                {{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}
                            </td>
                        </tr>

                        @if($invoice->tax_amount > 0)
                        <tr>
                            <td style="padding-bottom: 12px;" class="text-muted">Tax / VAT</td>
                            <td align="right" style="padding-bottom: 12px;" class="text-dark">
                                {{ $invoice->currency }} {{ number_format($invoice->tax_amount, 2) }}
                            </td>
                        </tr>
                        @endif

                        <tr>
                            <td colspan="2" style="border-top: 1px solid #e5e7eb; height: 1px;"></td>
                        </tr>

                        <tr>
                            <td style="padding-top: 15px; font-size: 16px; font-weight: 700; color: #111827;">
                                Total Amount
                            </td>
                            <td align="right" style="padding-top: 15px; font-size: 24px; font-weight: 800; color: #1e3a8a; letter-spacing: -0.5px;">
                                <span style="font-size: 14px; vertical-align: middle;">{{ $invoice->currency }}</span> {{ number_format($invoice->total_amount, 2) }}
                            </td>
                        </tr>

                        @if($invoice->paid_amount > 0)
                        <tr>
                            <td style="padding-top: 10px;" class="text-muted">Amount Paid</td>
                            <td align="right" style="padding-top: 10px; color: #10b981; font-weight: 600;">
                                - {{ $invoice->currency }} {{ number_format($invoice->paid_amount, 2) }}
                            </td>
                        </tr>
                        <tr>
                            <td style="padding-top: 10px; font-weight: 700; color: #dc2626;">Balance Due</td>
                            <td align="right" style="padding-top: 10px; font-size: 18px; font-weight: 800; color: #dc2626;">
                                {{ $invoice->currency }} {{ number_format($invoice->balance, 2) }}
                            </td>
                        </tr>
                        @endif
                    </table>

                    <!-- Payment Details -->
                    <table border="0" cellpadding="0" cellspacing="0" style="background-color: #f0f9ff; border-radius: 8px; margin-bottom: 25px;">
                        <tr>
                            <td style="padding: 20px;">
                                <p style="margin: 0 0 10px 0; font-size: 12px; color: #0369a1; text-transform: uppercase; font-weight: bold;">Payment Details</p>
                                <table border="0" cellpadding="0" cellspacing="0" style="font-size: 13px; color: #334155;">
                                    <tr>
                                        <td style="padding: 3px 0;"><strong>Bank:</strong> {{ config('medical.payment.bank_name', 'First National Bank') }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 3px 0;"><strong>Account:</strong> {{ config('medical.payment.account_number', '1234567890') }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 3px 0;"><strong>Reference:</strong> {{ $invoice->invoice_number }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <!-- Attachment Note -->
                    <table border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td align="center" style="padding-top: 10px;">
                                <p style="margin: 0; font-size: 13px; color: #6b7280; background-color: #f9fafb; padding: 10px 20px; border-radius: 6px; display: inline-block;">
                                    <span style="font-size: 16px;">&#128206;</span> <strong>Full Invoice Attached</strong> (PDF)
                                </p>
                            </td>
                        </tr>
                    </table>

                </td>
            </tr>

            <!-- Footer -->
            <tr>
                <td style="background-color: #f9fafb; padding: 20px 40px; text-align: center; border-top: 1px solid #f3f4f6;">
                    <p style="margin: 0 0 5px 0; font-size: 12px; color: #6b7280;">
                        Questions about this invoice? Contact us at <a href="mailto:billing@flexcare.co.zm" style="color: #2563eb;">billing@flexcare.co.zm</a>
                    </p>
                    <p style="margin: 0; font-size: 11px; color: #9ca3af;">
                        Payment due by {{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}
                    </p>
                </td>
            </tr>

        </table>

        <table align="center" border="0" cellpadding="0" cellspacing="0" width="600">
            <tr>
                <td align="center" style="padding-top: 20px; color: #9ca3af; font-size: 11px;">
                    FlexCare Medical Insurance Ltd &bull; Lusaka, Zambia
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
