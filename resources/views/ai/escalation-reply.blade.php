<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reply to escalation - 5Core AI Support</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 640px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .card { background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        h1 { margin-top: 0; color: #333; font-size: 1.25rem; }
        .question-box { background: #f8f9fa; border-left: 4px solid #405189; padding: 16px; margin: 16px 0; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #333; }
        textarea { width: 100%; min-height: 120px; padding: 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 1rem; }
        .btn { background: #405189; color: #fff; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #2c3e72; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .alert-info { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <div class="card">
        <h1>5Core AI Support – Reply to question</h1>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        @if(session('message'))
            <div class="alert alert-info">{{ session('message') }}</div>
        @endif

        {{-- 🔥 FIX: ALREADY ANSWERED CHECK --}}
        @if(isset($already_answered) && $already_answered)
            <div class="alert alert-info">This question has already been answered.</div>
            @if($escalation->senior_reply)
                <div class="question-box">
                    <strong>Previous reply:</strong><br>{{ e($escalation->senior_reply) }}
                </div>
            @endif
        @elseif($escalation->status === 'pending')
            <p><strong>Question from team member:</strong></p>
            <div class="question-box">{{ e($escalation->original_question) }}</div>

            <form action="{{ $submitUrl }}" method="POST">
                @csrf
                <label for="senior_reply">Your reply</label>
                <textarea id="senior_reply" name="senior_reply" required placeholder="Type your answer here...">{{ old('senior_reply') }}</textarea>
                @error('senior_reply')
                    <small style="color: #721c24;">{{ $message }}</small>
                @enderror
                <p style="margin-top: 16px;">
                    <button type="submit" class="btn">Submit reply</button>
                </p>
            </form>
        @else
            <p>This escalation has already been answered.</p>
            @if($escalation->senior_reply)
                <div class="question-box">
                    <strong>Previous reply:</strong><br>{{ e($escalation->senior_reply) }}
                </div>
            @endif
        @endif
    </div>
</body>
</html>