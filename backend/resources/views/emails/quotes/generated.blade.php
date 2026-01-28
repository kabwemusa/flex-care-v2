<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Quote #{{ $application->application_number }}</title>
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
            
            <tr>
                <td style="padding: 30px 40px; border-bottom: 1px solid #f3f4f6;">
                    <table border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="font-size: 20px; font-weight: 800; color: #1e3a8a; letter-spacing: -0.5px;">FLEXCARE</td>
                            <td align="right" style="color: #9ca3af; font-size: 12px; font-weight: 500;">
                                REF: {{ $application->application_number }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <tr>
                <td class="content" style="padding: 40px;">
                    
                    <p style="margin: 0 0 15px 0; font-size: 16px; color: #374151;">
                        Hi {{ $application->applicant_name }},
                    </p>
                    <p style="margin: 0 0 30px 0; font-size: 15px; color: #6b7280; line-height: 1.5;">
                        Your medical insurance quote for <strong>{{ $application->members->count() }} lives</strong> is ready for review.
                    </p>

                    <table border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
                        
                        <tr>
                            <td style="padding-bottom: 12px;" class="text-muted">Base Premium</td>
                            <td align="right" style="padding-bottom: 12px;" class="text-dark">
                                {{ $application->currency }} {{ number_format($application->base_premium, 2) }}
                            </td>
                        </tr>

                        @if(($application->addon_premium ?? 0) > 0)
                        <tr>
                            <td style="padding-bottom: 12px;" class="text-muted">Selected Add-ons</td>
                            <td align="right" style="padding-bottom: 12px;" class="text-dark">
                                {{ $application->currency }} {{ number_format($application->addon_premium, 2) }}
                            </td>
                        </tr>
                        @endif

                        @if(($application->loading_amount ?? 0) > 0)
                        <tr>
                            <td style="padding-bottom: 12px; color: #ef4444; font-size: 14px;">
                                Risk Adjustments
                            </td>
                            <td align="right" style="padding-bottom: 12px; color: #ef4444; font-size: 14px; font-weight: 500;">
                                + {{ $application->currency }} {{ number_format($application->loading_amount, 2) }}
                            </td>
                        </tr>
                        @endif

                        @if(($application->discount_amount ?? 0) > 0)
                        <tr>
                            <td style="padding-bottom: 12px; color: #10b981; font-size: 14px;">
                                Discount
                            </td>
                            <td align="right" style="padding-bottom: 12px; color: #10b981; font-size: 14px; font-weight: 500;">
                                - {{ $application->currency }} {{ number_format($application->discount_amount, 2) }}
                            </td>
                        </tr>
                        @endif

                        <tr>
                            <td style="padding-bottom: 15px;" class="text-muted">Taxes / Levies</td>
                            <td align="right" style="padding-bottom: 15px;" class="text-dark">
                                {{ $application->currency }} {{ number_format($application->tax_amount, 2) }}
                            </td>
                        </tr>

                        <tr>
                            <td colspan="2" style="border-top: 1px solid #e5e7eb; height: 1px;"></td>
                        </tr>

                        <tr>
                            <td style="padding-top: 15px; font-size: 16px; font-weight: 700; color: #111827;">
                                Total Payable
                            </td>
                            <td align="right" style="padding-top: 15px; font-size: 24px; font-weight: 800; color: #1e3a8a; letter-spacing: -0.5px;">
                                <span style="font-size: 14px; vertical-align: middle;">{{ $application->currency }}</span> {{ number_format($application->gross_premium, 2) }}
                            </td>
                        </tr>
                    </table>

                    <table border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td align="center">
                                <a href="#" style="background-color: #2563eb; color: #ffffff; padding: 14px 40px; border-radius: 99px; font-size: 14px; font-weight: 600; display: inline-block; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.4);">
                                    Accept Quote
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding-top: 25px;">
                                <p style="margin: 0; font-size: 13px; color: #6b7280; background-color: #f9fafb; padding: 8px 16px; border-radius: 6px; display: inline-block;">
                                    📎 <strong>Detailed Quote Attached</strong> (PDF)
                                </p>
                            </td>
                        </tr>
                    </table>

                </td>
            </tr>
            
            <tr>
                <td style="background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #f3f4f6;">
                    <p style="margin: 0; font-size: 11px; color: #9ca3af;">
                        Quote valid until {{ \Carbon\Carbon::parse($application->valid_until)->format('d M Y') }}
                    </p>
                </td>
            </tr>

        </table>

        <table align="center" border="0" cellpadding="0" cellspacing="0" width="600">
            <tr>
                <td align="center" style="padding-top: 20px; color: #9ca3af; font-size: 11px;">
                    FlexCare Medical Insurance Ltd • Lusaka, Zambia
                </td>
            </tr>
        </table>
    </div>

</body>
</html>