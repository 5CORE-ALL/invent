<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>5Core AI - Senior Reply</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .box { background: #f8f9fa; border-left: 4px solid #405189; padding: 16px; margin: 16px 0; }
        .btn { display: inline-block; background: #405189; color: #fff !important; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 16px 0; }
    </style>
</head>
<body>
    <p>Hello {{ $juniorName }},</p>

    <p>A senior team member has replied to your question. Please find the details below.</p>

    <div class="box">
        <strong>Your question:</strong><br>
        {{ $originalQuestion }}
    </div>

    <div class="box">
        <strong>Senior's reply:</strong><br>
        {{ $seniorReply }}
    </div>

    <p>You can view this and future replies in the 5Core AI Assistant chat widget on your dashboard.</p>

    <p>
        <a href="{{ $dashboardLink }}" class="btn">Open 5Core Dashboard</a>
    </p>

    <p>Thank you,<br>5Core AI Support</p>
</body>
</html>
