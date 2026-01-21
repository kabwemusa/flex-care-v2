<!DOCTYPE html>
<html>
<head>
    <title>Quote - {{ $application->application_number }}</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* RESET */
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 0; background: #f8fafc; color: #334155; font-size: 14px; line-height: 1.6; -webkit-font-smoothing: antialiased; }

        /* CONTAINER */
        .wrapper { width: 100%; background-color: #f8fafc; padding: 40px 0; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }

        /* HEADER */
        .header { background: #1e3a8a; padding: 30px 40px; }
        .header h1 { color: #ffffff; font-size: 24px; font-weight: 800; margin: 0; letter-spacing: 1px; }
        .header p { color: rgba(255,255,255,0.8); font-size: 12px; margin: 5px 0 0 0; }
        .accent-line { height: 4px; background: #10b981; }

        /* CONTENT */
        .content-padding { padding: 40px; }

        /* TYPOGRAPHY */
        h1, h2, h3 { margin: 0; color: #1e3a8a; }
        .text-sm { font-size: 12px; color: #64748b; }
        .font-bold { font-weight: 700; }
        .text-right { text-align: right; }

        /* GREETING */
        .greeting { margin-bottom: 25px; }
        .greeting h2 { font-size: 18px; margin-bottom: 10px; }
        .greeting p { color: #64748b; margin: 0; }

        /* CUSTOM MESSAGE */
        .custom-message { background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 15px 20px; margin-bottom: 25px; border-radius: 0 6px 6px 0; }
        .custom-message p { margin: 0; color: #334155; font-style: italic; }

        /* QUOTE SUMMARY BOX */
        .quote-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; margin-bottom: 25px; }
        .quote-ref { display: inline-block; background: #1e3a8a; color: #ffffff; padding: 8px 16px; border-radius: 4px; font-size: 14px; font-weight: 700; margin-bottom: 15px; }
        .quote-details { margin-top: 15px; }
        .quote-details table { width: 100%; }
        .quote-details td { padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
        .quote-details tr:last-child td { border-bottom: none; }
        .quote-details .label { color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 600; }
        .quote-details .value { color: #1e3a8a; font-weight: 600; text-align: right; }

        /* TOTAL BOX */
        .total-box { background: #1e3a8a; color: #ffffff; padding: 20px 25px; border-radius: 6px; margin-bottom: 25px; }
        .total-box table { width: 100%; }
        .total-box .label { font-size: 14px; opacity: 0.9; }
        .total-box .amount { font-size: 24px; font-weight: 700; text-align: right; }

        /* CTA BUTTON */
        .cta-section { text-align: center; margin: 30px 0; }
        .cta-button { display: inline-block; background: #10b981; color: #ffffff; padding: 14px 35px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 14px; }
        .cta-button:hover { background: #059669; }

        /* ATTACHMENT NOTE */
        .attachment-note { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 6px; padding: 15px 20px; margin-bottom: 25px; }
        .attachment-note p { margin: 0; color: #92400e; font-size: 13px; }
        .attachment-note strong { color: #78350f; }

        /* FOOTER */
        .footer { background: #f8fafc; padding: 25px 40px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer p { margin: 5px 0; color: #94a3b8; font-size: 11px; }
        .footer a { color: #3b82f6; text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="email-container">
            <!-- Header -->
            <div class="header">
                <h1>FLEXCARE</h1>
                <p>Medical Insurance Solutions</p>
            </div>
            <div class="accent-line"></div>

            <!-- Content -->
            <div class="content-padding">
                <!-- Greeting -->
                <div class="greeting">
                    <h2>Dear {{ $application->applicant_name ?? 'Valued Customer' }},</h2>
                    <p>Thank you for your interest in FlexCare Medical Insurance. Please find your personalized quote below.</p>
                </div>

                @if($customMessage)
                <!-- Custom Message -->
                <div class="custom-message">
                    <p>"{{ $customMessage }}"</p>
                </div>
                @endif

                <!-- Attachment Note -->
                <div class="attachment-note">
                    <p><strong>📎 PDF Attached:</strong> A detailed quote document is attached to this email for your records.</p>
                </div>

                <!-- Quote Summary Box -->
                <div class="quote-box">
                    <div class="quote-ref">{{ $application->application_number }}</div>

                    <div class="quote-details">
                        <table>
                            <tr>
                                <td class="label">Plan</td>
                                <td class="value">{{ $application->scheme->name ?? '' }} - {{ $application->plan->name ?? '' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Covered Members</td>
                                <td class="value">{{ $application->members->count() }} {{ $application->members->count() === 1 ? 'Member' : 'Members' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Policy Term</td>
                                <td class="value">{{ $application->policy_term_months ?? 12 }} Months</td>
                            </tr>
                            <tr>
                                <td class="label">Start Date</td>
                                <td class="value">{{ \Carbon\Carbon::parse($application->proposed_start_date)->format('d M Y') }}</td>
                            </tr>
                            <tr>
                                <td class="label">Quote Valid Until</td>
                                <td class="value" style="color: #ef4444;">{{ \Carbon\Carbon::parse($application->valid_until ?? now()->addDays(30))->format('d M Y') }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Total Box -->
                <div class="total-box">
                    <table>
                        <tr>
                            <td class="label">Total {{ ucfirst($application->billing_frequency ?? 'Annual') }} Premium</td>
                            <td class="amount">{{ $application->currency ?? 'ZMW' }} {{ number_format($application->gross_premium ?? 0, 2) }}</td>
                        </tr>
                    </table>
                </div>

                <!-- Next Steps -->
                <div style="margin-bottom: 25px;">
                    <h3 style="font-size: 14px; margin-bottom: 10px; color: #1e3a8a;">Next Steps</h3>
                    <ol style="margin: 0; padding-left: 20px; color: #64748b; font-size: 13px;">
                        <li style="margin-bottom: 8px;">Review the attached PDF quote for complete details</li>
                        <li style="margin-bottom: 8px;">Contact us if you have any questions</li>
                        <li style="margin-bottom: 8px;">Accept the quote to proceed with your policy</li>
                    </ol>
                </div>

                <!-- Help Section -->
                <div style="text-align: center; padding: 20px; background: #f8fafc; border-radius: 6px;">
                    <p style="margin: 0 0 5px 0; color: #64748b; font-size: 13px;">Have questions? We're here to help.</p>
                    <p style="margin: 0; color: #1e3a8a; font-weight: 600;">Contact our support team</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                <p><strong>FlexCare Medical Insurance</strong></p>
                <p>This quote is subject to standard terms and conditions. Final acceptance is subject to underwriting approval.</p>
                <p style="margin-top: 15px;">Generated on {{ now()->format('d M Y, g:i A') }}</p>
            </div>
        </div>
    </div>
</body>
</html>