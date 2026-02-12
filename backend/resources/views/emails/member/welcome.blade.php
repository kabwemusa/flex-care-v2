<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Welcome to FlexCare</title>
    <style type="text/css">
        body { margin: 0; padding: 0; background-color: #f3f4f6; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; color: #1f2937; }
        table { border-collapse: collapse !important; width: 100%; }
        a { text-decoration: none; }
        .wrapper { background-color: #f3f4f6; padding: 40px 0; }
        .container { background-color: #ffffff; max-width: 600px; margin: 0 auto; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        @media only screen and (max-width: 600px) {
            .wrapper { padding: 10px; }
            .content { padding: 20px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6;">

    <div class="wrapper">
        <table align="center" border="0" cellpadding="0" cellspacing="0" class="container" width="600" style="background-color: #ffffff;">

            {{-- Header with gradient-like banner --}}
            <tr>
                <td style="background-color: #0891b2; padding: 40px; text-align: center;">
                    <p style="margin: 0 0 8px 0; font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">
                        FLEXCARE
                    </p>
                    <p style="margin: 0; font-size: 14px; color: #cffafe; font-weight: 500;">
                        Member Portal
                    </p>
                </td>
            </tr>

            {{-- Body --}}
            <tr>
                <td class="content" style="padding: 40px;">

                    <p style="margin: 0 0 15px 0; font-size: 16px; color: #374151;">
                        Hi {{ $memberName }},
                    </p>

                    <p style="margin: 0 0 25px 0; font-size: 15px; color: #6b7280; line-height: 1.6;">
                        Welcome to the FlexCare Member Portal! Your online access has been activated and you can now manage your health insurance from anywhere.
                    </p>

                    {{-- Account Details --}}
                    <table border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 30px; background-color: #f0fdfa; border-radius: 10px; overflow: hidden;">
                        <tr>
                            <td style="padding: 20px;">
                                <p style="margin: 0 0 12px 0; font-size: 13px; font-weight: 700; color: #0891b2; text-transform: uppercase; letter-spacing: 0.5px;">
                                    Your Account Details
                                </p>
                                <table border="0" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 13px; color: #6b7280; width: 120px;">Member Number</td>
                                        <td style="padding: 6px 0; font-size: 14px; font-weight: 600; color: #111827; font-family: 'Courier New', monospace;">{{ $memberNumber }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 0; font-size: 13px; color: #6b7280;">Login Email</td>
                                        <td style="padding: 6px 0; font-size: 14px; font-weight: 600; color: #111827;">{{ $email }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    {{-- What You Can Do --}}
                    <p style="margin: 0 0 15px 0; font-size: 15px; font-weight: 600; color: #374151;">
                        What you can do on the portal:
                    </p>
                    <table border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 30px;">
                        <tr>
                            <td style="padding: 8px 0; font-size: 14px; color: #4b5563; line-height: 1.5;">
                                &#10003;&nbsp;&nbsp; View your policy details and benefits
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-size: 14px; color: #4b5563; line-height: 1.5;">
                                &#10003;&nbsp;&nbsp; Submit and track claims online
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-size: 14px; color: #4b5563; line-height: 1.5;">
                                &#10003;&nbsp;&nbsp; Download your digital ID card
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-size: 14px; color: #4b5563; line-height: 1.5;">
                                &#10003;&nbsp;&nbsp; Check your benefit usage in real-time
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; font-size: 14px; color: #4b5563; line-height: 1.5;">
                                &#10003;&nbsp;&nbsp; Manage your profile and dependents
                            </td>
                        </tr>
                    </table>

                    {{-- CTA Button --}}
                    <table border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                        <tr>
                            <td align="center">
                                <a href="{{ $loginUrl }}" style="background-color: #0891b2; color: #ffffff; padding: 14px 40px; border-radius: 99px; font-size: 14px; font-weight: 600; display: inline-block; box-shadow: 0 4px 6px -1px rgba(8, 145, 178, 0.4);">
                                    Sign In to Your Portal
                                </a>
                            </td>
                        </tr>
                    </table>

                    <p style="margin: 0; font-size: 13px; color: #9ca3af; text-align: center;">
                        No password needed &mdash; we&rsquo;ll send you a one-time code to your email.
                    </p>

                </td>
            </tr>

            {{-- Footer --}}
            <tr>
                <td style="background-color: #f9fafb; padding: 20px 40px; border-top: 1px solid #f3f4f6;">
                    <p style="margin: 0 0 8px 0; font-size: 12px; color: #6b7280; line-height: 1.5;">
                        Need help? Contact us at <a href="mailto:support@flexcare.co.zm" style="color: #0891b2;">support@flexcare.co.zm</a>
                        or call <a href="tel:+260970000000" style="color: #0891b2;">+260 97 000 0000</a>
                    </p>
                    <p style="margin: 0; font-size: 11px; color: #9ca3af;">
                        You received this email because a portal account was created for you at FlexCare.
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
