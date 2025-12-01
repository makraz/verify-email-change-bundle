{#
    Email Change Notification Template
    Sent to: OLD email address
    Purpose: Notify user that their email was successfully changed
#}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Address Changed</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px;
        }
        .content p {
            margin: 0 0 15px 0;
            color: #333;
        }
        .success-box {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .success-box strong {
            color: #10b981;
            display: block;
            margin-bottom: 5px;
        }
        .info-table {
            width: 100%;
            background: #f9fafb;
            border-radius: 6px;
            overflow: hidden;
            margin: 20px 0;
            border: 1px solid #e5e7eb;
        }
        .info-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-table tr:last-child td {
            border-bottom: none;
        }
        .info-table td:first-child {
            font-weight: 600;
            color: #6b7280;
            width: 40%;
        }
        .info-table td:last-child {
            color: #111827;
        }
        .security-warning {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .security-warning strong {
            color: #ef4444;
            display: block;
            margin-bottom: 5px;
        }
        .security-warning p {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #7f1d1d;
        }
        .button-container {
            text-align: center;
            margin: 15px 0 0 0;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #ef4444;
            color: white !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.3s;
        }
        .button:hover {
            background: #dc2626;
        }
        .info-list {
            background: #f9fafb;
            padding: 15px 15px 15px 35px;
            margin: 15px 0;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
        }
        .info-list li {
            margin: 8px 0;
            color: #4b5563;
        }
        .footer {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            padding: 20px;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            margin: 5px 0;
        }
        @media only screen and (max-width: 600px) {
            body {
                padding: 10px;
            }
            .header {
                padding: 20px;
            }
            .header h1 {
                font-size: 20px;
            }
            .content {
                padding: 20px;
            }
            .info-table td {
                display: block;
                width: 100% !important;
                padding: 8px 15px;
            }
            .info-table td:first-child {
                font-weight: 600;
                padding-bottom: 4px;
            }
            .button {
                padding: 10px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1> Email Address Changed</h1>
        </div>

        <div class="content">
            <p>Hello,</p>

            <div class="success-box">
                <strong>Your email address has been successfully changed.</strong>
            </div>

            <p>This is a confirmation that your email address associated with your account has been updated.</p>

            <table class="info-table">
                <tr>
                    <td>Previous Email:</td>
                    <td><strong>{{ old_email }}</strong></td>
                </tr>
                <tr>
                    <td>New Email:</td>
                    <td><strong>{{ new_email }}</strong></td>
                </tr>
                <tr>
                    <td>Changed At:</td>
                    <td>{{ changed_at|date('F j, Y \\a\\t g:i A') }}</td>
                </tr>
            </table>

            <div class="security-warning">
                <strong>=¨ Didn't make this change?</strong>
                <p>If you did NOT authorize this email change, your account may have been compromised. Please take immediate action to secure your account.</p>
                <div class="button-container">
                    <a href="{{ support_url|default('#') }}" class="button">Contact Support Immediately</a>
                </div>
            </div>

            <p><strong>What this means:</strong></p>
            <ul class="info-list">
                <li>All future account-related emails will be sent to your new email address</li>
                <li>You'll need to use your new email address to log in</li>
                <li>Your account settings and preferences remain unchanged</li>
                <li>This notification is being sent to your old email for security purposes</li>
            </ul>

            <p>If you made this change, no further action is needed. Your account is secure.</p>
        </div>

        <div class="footer">
            <p>This is an automated security notification.</p>
            <p>&copy; {{ "now"|date("Y") }} {{ app_name|default('Your Application') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
