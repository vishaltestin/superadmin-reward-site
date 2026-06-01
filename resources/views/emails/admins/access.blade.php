<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RewardsApp - Admin Access</title>
</head>
<body style="margin:0; padding:0; background:#f4f7fb; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb; padding:40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background:#2563eb; padding:32px 40px; text-align:center;">
                            <h1 style="margin:0; font-size:28px; line-height:1.2; color:#ffffff;">RewardsApp</h1>
                            <p style="margin:10px 0 0; font-size:15px; color:#dbeafe;">Your account has been approved</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:40px;">
                            <p style="margin:0 0 16px; font-size:16px; line-height:1.7;">
                                Hi <strong>{{ $user->first_name }}</strong>,
                            </p>

                            <p style="margin:0 0 16px; font-size:16px; line-height:1.7;">
                                Congratulations — your account has been approved.
                            </p>

                            <p style="margin:0 0 20px; font-size:16px; line-height:1.7;">
                                You are now the <strong>Super Admin</strong> for your organization on <strong>RewardsApp</strong>.
                            </p>

                            <p style="margin:0 0 12px; font-size:15px; font-weight:bold;">With this access, you can:</p>
                            <ul style="margin:0 0 24px; padding-left:20px; font-size:15px; line-height:1.8;">
                                <li>Send gifts and vouchers to employees, customers, and partners</li>
                                <li>Create and manage gifting campaigns</li>
                                <li>Add team members and assign roles</li>
                                <li>Track all activity from a single dashboard</li>
                            </ul>

                            <h2 style="margin:0 0 16px; font-size:20px; color:#111827;">Login Details</h2>

                            <div style="background:#f8fafc; border:1px solid #e5e7eb; border-radius:12px; padding:20px; margin:0 0 24px;">
                                <p style="margin:0 0 12px; font-size:15px;">
                                    <strong>Login URL:</strong><br>
                                    <a href="{{ $loginUrl }}" style="color:#2563eb; text-decoration:none;">{{ $loginUrl }}</a>
                                </p>

                                <p style="margin:0 0 12px; font-size:15px;">
                                    <strong>Username:</strong><br>
                                    {{ $user->email }}
                                </p>

                                <p style="margin:0; font-size:15px;">
                                    <strong>Temporary Password:</strong><br>
                                    <span style="font-family:Courier New, monospace; background:#e5e7eb; padding:6px 10px; border-radius:8px; display:inline-block;">
                                        {{ $rawPassword }}
                                    </span>
                                </p>
                            </div>

                            <p style="margin:0 0 20px; font-size:15px; line-height:1.7;">
                                For security reasons, please change your password after your first login.
                            </p>

                            <p style="margin:0 0 28px; text-align:center;">
                                <a href="{{ $ctaLink }}" style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none; padding:14px 26px; border-radius:10px; font-size:15px; font-weight:bold;">
                                    Login and Start Sending
                                </a>
                            </p>

                            <p style="margin:0; font-size:14px; line-height:1.7; color:#6b7280; text-align:center;">
                                If you need any assistance or a quick walkthrough, our team is here to help at
                                <a href="mailto:support@rewardsapp.in" style="color:#2563eb; text-decoration:none;">support@rewardsapp.in</a>.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 40px 32px; text-align:center; font-size:13px; color:#9ca3af;">
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