<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RewardsApp - Signup Under Review</title>
</head>
<body style="margin:0; padding:0; background:#f4f7fb; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb; padding:40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.08);">

                    {{-- Header --}}
                    <tr>
                        <td style="background:#2563eb; padding:32px 40px; text-align:center;">
                            <h1 style="margin:0; font-size:28px; line-height:1.2; color:#ffffff;">RewardsApp</h1>
                            <p style="margin:10px 0 0; font-size:15px; color:#dbeafe;">Your signup is under review</p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:40px;">
                            <p style="margin:0 0 16px; font-size:16px; line-height:1.7;">
                                Hi <strong>{{ $lead->first_name }}</strong>,
                            </p>

                            <p style="margin:0 0 16px; font-size:16px; line-height:1.7;">
                                Thank you for signing up on <strong>RewardsApp</strong>.
                            </p>

                            <p style="margin:0 0 24px; font-size:16px; line-height:1.7;">
                                Your account is currently under review by our team. This is a quick verification step to ensure a secure and smooth experience for all users.
                            </p>

                            {{-- Status box --}}
                            <div style="background:#eff6ff; border:1px solid #bfdbfe; border-left:4px solid #2563eb; border-radius:10px; padding:20px 24px; margin:0 0 28px;">
                                <p style="margin:0; font-size:15px; line-height:1.7; color:#1e40af;">
                                    Once approved, you will receive a separate email with your login credentials.
                                </p>
                            </div>

                            {{-- What to expect --}}
                            <p style="margin:0 0 12px; font-size:15px; font-weight:bold; color:#111827;">
                                In the meantime, here's what you can expect with RewardsApp:
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 28px;">
                                @foreach ([
                                    ['🎁', 'Send gifts and vouchers to employees, customers, and partners'],
                                    ['🛍️', 'Choose from a wide range of options'],
                                    ['📦', 'Manage bulk gifting with ease'],
                                    ['📊', 'Track everything from one place'],
                                ] as [$icon, $text])
                                <tr>
                                    <td style="padding:8px 0; vertical-align:top; width:32px; font-size:18px;">{{ $icon }}</td>
                                    <td style="padding:8px 0 8px 8px; font-size:15px; line-height:1.6; color:#374151;">{{ $text }}</td>
                                </tr>
                                @endforeach
                            </table>

                            <p style="margin:0 0 28px; font-size:15px; line-height:1.7; color:#374151;">
                                We usually complete approvals shortly. If you have any urgent requirements or questions, feel free to reach out to us at
                                <a href="mailto:support@rewardsapp.in" style="color:#2563eb; text-decoration:none;">support@rewardsapp.in</a>.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
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