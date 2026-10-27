<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RewardsApp password changed</title>
</head>
<body style="margin:0; padding:0; background:#f4f7fb; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb; padding:40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background:#2563eb; padding:32px 40px; text-align:center;">
                            <h1 style="margin:0; font-size:28px; line-height:1.2; color:#ffffff;">RewardsApp</h1>
                            <p style="margin:10px 0 0; font-size:15px; color:#dbeafe;">Security notification</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:40px;">
                            <p style="margin:0 0 16px; font-size:16px; line-height:1.7;">
                                Hi <strong>{{ $user->first_name ?: $user->name }}</strong>,
                            </p>
                            <p style="margin:0 0 16px; font-size:16px; line-height:1.7;">
                                The password for your RewardsApp account was changed successfully.
                            </p>
                            <div style="background:#eff6ff; border:1px solid #bfdbfe; border-left:4px solid #2563eb; border-radius:10px; padding:18px 20px; margin:24px 0;">
                                <p style="margin:0; font-size:14px; line-height:1.7; color:#1e40af;">
                                    If you made this change, no further action is needed. If you did not change your password, contact our support team immediately at
                                    <a href="mailto:support@rewardsapp.in" style="color:#1d4ed8; text-decoration:none;">support@rewardsapp.in</a>.
                                </p>
                            </div>
                            <p style="margin:0; font-size:14px; line-height:1.7; color:#6b7280;">
                                For your security, this email does not include your password.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 40px 32px; text-align:center; font-size:13px; color:#9ca3af; border-top:1px solid #f3f4f6;">
                            Regards,<br>
                            <strong style="color:#6b7280;">Team RewardsApp</strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
