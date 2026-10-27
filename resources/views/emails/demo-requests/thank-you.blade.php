<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RewardsApp demo request received</title>
</head>
<body style="margin:0; padding:0; background:#f4f7fb; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fb; padding:40px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px; max-width:600px; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background:#2563eb; padding:32px 40px; text-align:center;">
                            <h1 style="margin:0; font-size:28px; line-height:1.2; color:#ffffff;">RewardsApp</h1>
                            <p style="margin:10px 0 0; font-size:15px; color:#dbeafe;">Demo request received</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:40px;">
                            <p style="margin:0 0 16px; font-size:16px; line-height:1.7;">
                                Hi <strong>{{ $demoRequest->first_name }}</strong>,
                            </p>
                            <p style="margin:0 0 16px; font-size:16px; line-height:1.7;">
                                Thank you for your interest in RewardsApp. We have received your demo request and our team will contact you shortly to arrange a walkthrough.
                            </p>

                            <div style="background:#f8fafc; border:1px solid #e5e7eb; border-radius:12px; padding:20px 24px; margin:24px 0;">
                                <h2 style="margin:0 0 14px; font-size:17px; color:#111827;">Request details</h2>
                                <p style="margin:0 0 8px; font-size:14px; line-height:1.6;"><strong>Company:</strong> {{ $demoRequest->company_name }}</p>
                                <p style="margin:0 0 8px; font-size:14px; line-height:1.6;"><strong>Department:</strong> {{ $demoRequest->department }}</p>
                                <p style="margin:0 0 8px; font-size:14px; line-height:1.6;"><strong>Designation:</strong> {{ $demoRequest->designation }}</p>
                                <p style="margin:0 0 8px; font-size:14px; line-height:1.6;"><strong>Number of employees:</strong> {{ $demoRequest->number_of_employee }}</p>
                                <p style="margin:0; font-size:14px; line-height:1.6;"><strong>How you heard about us:</strong> {{ $demoRequest->how_did_you_hear_about_us }}</p>
                            </div>

                            @if ($demoRequest->message)
                                <p style="margin:0 0 24px; font-size:14px; line-height:1.7; color:#4b5563;">
                                    <strong>Your message:</strong><br>
                                    {{ $demoRequest->message }}
                                </p>
                            @endif

                            <p style="margin:0; font-size:14px; line-height:1.7; color:#6b7280;">
                                If you have any questions, contact us at
                                <a href="mailto:support@rewardsapp.in" style="color:#2563eb; text-decoration:none;">support@rewardsapp.in</a>.
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
