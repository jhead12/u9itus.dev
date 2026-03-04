<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Profile</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 32px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
        }
        h1 {
            color: #1f2937;
            font-size: 24px;
            margin-bottom: 20px;
        }
        .content {
            margin-bottom: 30px;
        }
        .verification-button {
            display: inline-block;
            padding: 14px 32px;
            background-color: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
            margin: 20px 0;
        }
        .verification-button:hover {
            background-color: #1d4ed8;
        }
        .info-box {
            background-color: #f0f9ff;
            border-left: 4px solid #2563eb;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
        }
        .alternative-link {
            word-break: break-all;
            color: #2563eb;
            font-size: 12px;
            margin-top: 15px;
        }
        ul {
            padding-left: 20px;
        }
        li {
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">U9itus</div>
            <p style="color: #6b7280; margin: 0;">Political Loyalty Ads Platform</p>
        </div>

        <h1>Verify Your Political Profile</h1>

        <div class="content">
            <p>Hello {{ $politicianName }},</p>

            <p>You've requested to verify your U9itus political profile using this government email address. Verification unlocks public data transparency features that allow you to display official records on your campaign page.</p>

            <div class="info-box">
                <strong>🔒 Why Verification?</strong>
                <p style="margin: 10px 0 0 0;">Profile verification ensures authenticity and prevents impersonation. Only verified politicians can opt-in to display public data from trusted sources like Ballotpedia, OpenSecrets, Vote Smart, and the FEC.</p>
            </div>

            <p>Click the button below to complete your verification:</p>

            <div style="text-align: center;">
                <a href="{{ $verificationUrl }}" class="verification-button">
                    Verify My Profile
                </a>
            </div>

            <p style="font-size: 14px; color: #6b7280;">
                This verification link will expire in {{ $expiryHours }} hours.
            </p>

            <h3 style="margin-top: 30px; font-size: 18px;">What happens after verification?</h3>
            <ul>
                <li>Your profile will receive a "Verified" badge</li>
                <li>You can opt-in to display voting records (Ballotpedia)</li>
                <li>You can opt-in to display campaign finance data (OpenSecrets)</li>
                <li>You can opt-in to display issue ratings (Vote Smart)</li>
                <li>Federal candidates can display FEC filing data</li>
            </ul>

            <div class="info-box" style="background-color: #fef3c7; border-left-color: #f59e0b;">
                <strong>⚠️ Important:</strong>
                <p style="margin: 10px 0 0 0;">All data sources are optional and can be individually enabled/disabled in your transparency settings. You maintain full control over what public data appears on your profile.</p>
            </div>
        </div>

        <div class="footer">
            <p>If you didn't request this verification, you can safely ignore this email.</p>
            
            <div class="alternative-link">
                <p>If the button doesn't work, copy and paste this link into your browser:</p>
                <p>{{ $verificationUrl }}</p>
            </div>

            <p style="margin-top: 20px;">
                © {{ date('Y') }} U9itus - Political Loyalty Ads Platform<br>
                Head Enterprises
            </p>
        </div>
    </div>
</body>
</html>
