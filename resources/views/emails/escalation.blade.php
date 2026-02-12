<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>5Core AI Support - Escalation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .box { background: #f8f9fa; border-left: 4px solid #405189; padding: 16px; margin: 20px 0; }
        .btn { display: inline-block; background: #405189; color: #fff !important; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 16px 0; }
        .note { font-size: 0.9em; color: #666; margin-top: 24px; }
    </style>
</head>
<body>
    <p>Hello,</p>

    <p>A team member has asked a question that our AI assistant could not answer. Your expertise is needed.</p>

    <p><strong>Team member:</strong> {{ $juniorName }} ({{ $juniorEmail }})</p>
    <p><strong>Domain:</strong> {{ $domain }}</p>

    <div class="box">
        <strong>Question:</strong><br>
        {{ $question }}
    </div>

    <p>Please click the button below to provide your reply. Your response will be shared with the team member and may be used to improve our internal knowledge base.</p>

    <p>
        <a href="{{ $replyLink }}" class="btn">Reply to this question</a>
    </p>

    <p class="note">Note: Your response will help train our AI system and improve support for the entire team.</p>

    <p>Thank you,<br>5Core AI Support</p>
</body>
</html>
