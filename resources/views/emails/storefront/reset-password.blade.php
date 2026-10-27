<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset your password</title>
</head>
<body style="margin:0; padding:0; background:#f4f7fb; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb; padding:40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background:#2563eb; padding:32px 40px; text-align:center;">
                            <h1 style="margin:0; font-size:28px; line-height:1.2; color:#ffffff;">{{ $company->name }}</h1>
                            <p style="margin:10px 0 0; font-size:15px; color:#dbeafe;">Password reset request</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:40px;">
                            <p style="margin:0 0 16px; font-size:16px; line-height:1.7;">
                                Hi <strong>{{ $user->first_name ?: $user->name }}</strong>,
                            </p>
                            <p style="margin:0 0 16px; font-size:16px; line-height:1.7;">
                                We received a request to reset the password for your {{ $company->name }} rewards account.
                            </p>
                            <p style="margin:0; font-size:16px; line-height:1.7;">
                                Click the button below to choose a new password. This link expires in {{ $expiresInMinutes }} minutes and can be used only once.
                            </p>

                            <div style="text-align:center; margin:32px 0;">
                                <a href="{{ $resetUrl }}" style="background:#2563eb; color:#ffffff; text-decoration:none; padding:14px 28px; border-radius:10px; display:inline-block; font-weight:bold;">
                                    Reset Password
                                </a>
                            </div>

                            <p style="margin:0 0 10px; font-size:13px; line-height:1.7; color:#6b7280;">
                                If the button does not work, copy and paste this URL into your browser:
                            </p>
                            <p style="margin:0 0 24px; font-size:13px; line-height:1.7; word-break:break-all;">
                                <a href="{{ $resetUrl }}" style="color:#2563eb;">{{ $resetUrl }}</a>
                            </p>

                            <div style="background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:16px 18px;">
                                <p style="margin:0; font-size:14px; line-height:1.7; color:#4b5563;">
                                    If you did not request a password reset, you can ignore this email. Your password will not change.
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 40px 32px; text-align:center; font-size:13px; color:#9ca3af; border-top:1px solid #f3f4f6;">
                            Regards,<br>
                            <strong style="color:#6b7280;">Team {{ config('app.name') }}</strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
