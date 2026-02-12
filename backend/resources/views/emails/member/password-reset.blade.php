<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Password Reset</title>
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

            {{-- Header --}}
            <tr>
                <td style="padding: 30px 40px; border-bottom: 1px solid #f3f4f6;">
                    <table border="0" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="font-size: 20px; font-weight: 800; color: #0891b2; letter-spacing: -0.5px;">FLEXCARE</td>
                            <td align="right" style="color: #9ca3af; font-size: 12px; font-weight: 500;">
                                Password Reset
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            {{-- Body --}}
            <tr>
                <td class="content" style="padding: 40px;">

                    <p style="margin: 0 0 15px 0; font-size: 16px; color: #374151;">
                        Hi {{ $memberName ?? 'there' }},
                    </p>

                    <p style="margin: 0 0 10px 0; font-size: 15px; color: #6b7280; line-height: 1.6;">
                        We received a request to reset the password for your FlexCare Member Portal account.
                    </p>

                    <p style="margin: 0 0 30px 0; font-size: 15px; color: #6b7280; line-height: 1.6;">
                        Use the code below to reset your password. This code expires in <strong>10 minutes</strong>.
                    </p>

                    {{-- OTP Code Box --}}
                    <table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto 30px auto;">
                        <tr>
                            <td align="center" style="background-color: #fef2f2; border: 2px dashed #dc2626; border-radius: 12px; padding: 24px 48px;">
                                <p style="margin: 0 0 6px 0; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 1px;">
                                    Password reset code
                                </p>
                                <p style="margin: 0; font-size: 36px; font-weight: 800; color: #dc2626; letter-spacing: 8px; font-family: 'Courier New', monospace;">
                                    {{ $code }}
                                </p>
                            </td>
                        </tr>
                    </table>

                    {{-- Security Note --}}
                    <table border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 10px;">
                        <tr>
                            <td style="background-color: #fefce8; border-radius: 8px; padding: 14px 18px;">
                                <p style="margin: 0; font-size: 13px; color: #854d0e; line-height: 1.5;">
                                    <strong>Didn't request this?</strong> If you did not request a password reset, please ignore this email. Your password will remain unchanged.
                                </p>
                            </td>
                        </tr>
                    </table>

                </td>
            </tr>

            {{-- Footer --}}
            <tr>
                <td style="background-color: #f9fafb; padding: 20px 40px; border-top: 1px solid #f3f4f6;">
                    <p style="margin: 0; font-size: 12px; color: #9ca3af; line-height: 1.5;">
                        For your security, this code can only be used once. If it expires, request a new one from the login page.
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
