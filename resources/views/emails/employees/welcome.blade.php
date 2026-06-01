<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to RewardsApp</title>
</head>
<body style="margin:0; padding:0; background:#f4f7fb; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb; padding:40px 0;">
    <tr>
        <td align="center">

        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.08);">

            <tr>
                <td style="background:linear-gradient(135deg,#2563eb,#1d4ed8); padding:35px; text-align:center;">
                    <h1 style="margin:0; color:#ffffff; font-size:28px;">
                        Welcome to RewardsApp 🎉
                    </h1>
                    <p style="margin:10px 0 0; color:#dbeafe; font-size:15px;">
                        Your rewards account is ready
                    </p>
                </td>
            </tr>

            <tr>
                <td style="padding:40px;">

                    <p style="margin:0 0 20px; font-size:16px;">
                        Hi <strong>{{ $user->first_name }}</strong>,
                    </p>

                    <p style="margin:0 0 20px; font-size:16px; line-height:1.7;">
                        An account has been created for you on the
                        <strong>{{ $user->company->name }}</strong>
                        rewards platform.
                    </p>

                    <p style="margin:0 0 24px; font-size:16px; line-height:1.7;">
                        You can now browse the catalog, claim rewards, and manage your account.
                    </p>

                    <h2 style="margin:0 0 16px; font-size:20px; color:#111827;">
                        Login Details
                    </h2>

                    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
                        <tr>
                            <td style="padding-bottom:15px;">
                                <div style="font-size:13px; color:#6b7280;">Email</div>
                                <div style="font-size:15px; color:#111827;">
                                    {{ $user->email }}
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding-bottom:15px;">
                                <div style="font-size:13px; color:#6b7280;">Temporary Password</div>
                                <div style="display:inline-block; background:#eef2ff; padding:8px 12px; border-radius:8px; font-family:Courier New, monospace;">
                                    {{ $rawPassword }}
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <div style="font-size:13px; color:#6b7280;">Storefront URL</div>
                                <a href="{{ $storefrontUrl }}" style="color:#2563eb; text-decoration:none;">
                                    {{ $storefrontUrl }}
                                </a>
                            </td>
                        </tr>
                    </table>

                    <p style="margin:24px 0; font-size:15px; line-height:1.7; color:#4b5563;">
                        For security reasons, we highly recommend changing your password after your first login.
                    </p>

                    <div style="text-align:center; margin:30px 0;">
                        <a href="{{ $storefrontUrl }}"
                           style="background:#2563eb; color:#ffffff; text-decoration:none; padding:14px 28px; border-radius:10px; display:inline-block; font-weight:bold;">
                            Visit Rewards Portal
                        </a>
                    </div>

                    <p style="margin:0; font-size:14px; color:#6b7280;">
                        If you have any questions, please contact your administrator or support team.
                    </p>

                </td>
            </tr>

            <tr>
                <td style="padding:25px; text-align:center; background:#f9fafb; color:#9ca3af; font-size:13px;">
                    Thanks,<br>
                    <strong style="color:#6b7280;">{{ config('app.name') }}</strong>
                </td>
            </tr>

        </table>

    </td>
</tr>

</table>

</body>
</html>
