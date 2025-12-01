{#
    Email Change Verification Template
    Sent to: NEW email address
    Purpose: Verify ownership of the new email address
#}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email Change</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            padding: 14px 28px;
            background: #667eea;
            color: white !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.3s;
        }
        .button:hover {
            background: #5568d3;
        }
        .info-box {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box strong {
            color: #667eea;
            display: block;
            margin-bottom: 5px;
        }
        .info-box p {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
        }
        .link-fallback {
            word-break: break-all;
            background: #f9fafb;
            padding: 10px;
            border-radius: 4px;
            font-size: 13px;
            color: #667eea;
            border: 1px solid #e5e7eb;
        }
        .warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning strong {
            color: #f59e0b;
            display: block;
            margin-bottom: 5px;
        }
        .warning p {
            margin: 0;
            font-size: 14px;
            color: #78350f;
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
            .button {
                padding: 12px 24px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>= Verify Your Email Change</h1>
        </div>

        <div class="content">
            <p>Hello <strong>{{ user.email|default('there') }}</strong>,</p>

            <p>You recently requested to change your email address. To complete this process, please verify your new email address by clicking the button below:</p>

            <div class="button-container">
                <a href="{{ signature.signedUrl }}" class="button">Verify New Email Address</a>
            </div>

            <div class="info-box">
                <strong>ñ This link will expire in {{ (signature.expiresAt.timestamp - "now"|date('U')) // 3600 }} hour(s)</strong>
                <p>If you don't verify within this time, you'll need to request a new email change.</p>
            </div>

            <p><strong>If the button doesn't work</strong>, copy and paste this link into your browser:</p>
            <p class="link-fallback">{{ signature.signedUrl }}</p>

            <div class="warning">
                <strong>  Didn't request this change?</strong>
                <p>If you didn't request to change your email address, please ignore this email or contact support if you're concerned about your account security.</p>
            </div>
        </div>

        <div class="footer">
            <p>This is an automated message, please do not reply to this email.</p>
            <p>&copy; {{ "now"|date("Y") }} {{ app_name|default('Your Application') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
