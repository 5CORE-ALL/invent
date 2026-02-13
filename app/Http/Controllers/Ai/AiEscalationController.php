<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Mail\JuniorNotificationMail;
use App\Models\AiEscalation;
use App\Models\AiTrainingLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class AiEscalationController extends Controller
{
    /**
     * Show reply form for senior (signed URL - no auth required).
     */
    /**
     * Show reply form for senior (signed URL - no auth required).
     */
    public function showReplyForm(int $id): View
    {
        $escalation = AiEscalation::findOrFail($id);

        // Agar already answered hai to message ke saath view dikhao
        if ($escalation->status !== 'pending') {
            return view('ai.escalation-reply', [
                'escalation' => $escalation,
                'submitUrl' => '#',
                'already_answered' => true
            ]);
        }

        return view('ai.escalation-reply', [
            'escalation' => $escalation,
            'submitUrl' => url('/ai/escalation/' . $id . '/reply'),
            'already_answered' => false
        ]);
    }

    /**
     * Submit senior's reply (signed URL required).
     */
    public function submitReply(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'senior_reply' => ['required', 'string', 'max:10000'],
        ]);

        $escalation = AiEscalation::findOrFail($id);
        if ($escalation->status !== 'pending') {
            return redirect()->back()->with('error', 'This escalation has already been answered.');
        }

        $seniorReply = strip_tags($request->senior_reply);
        $escalation->update([
            'senior_reply' => $seniorReply,
            'status' => 'answered',
            'answered_at' => now(),
        ]);

        AiTrainingLog::create([
            'question' => $escalation->original_question,
            'answer' => $seniorReply,
            'answered_by' => null,
            'escalation_id' => $escalation->id,
            'is_approved' => false,
        ]);

        // Send email notification to junior (user offline scenario)
        if (!$escalation->email_notification_sent && $escalation->user) {
            $junior = $escalation->user;
            $juniorEmail = $junior->email ?? '';
            if (filter_var($juniorEmail, FILTER_VALIDATE_EMAIL) && str_ends_with(strtolower($juniorEmail), '@5core.com')) {
                try {
                    Mail::to($juniorEmail)->send(new JuniorNotificationMail(
                        $junior->name ?? $juniorEmail,
                        $escalation->original_question,
                        $seniorReply,
                        url('/')
                    ));
                    $escalation->update(['email_notification_sent' => true]);
                } catch (\Throwable $e) {
                    Log::error('Junior notification email failed', [
                        'escalation_id' => $escalation->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return redirect()->back()->with('success', 'Your reply has been sent to the team member. Thank you.');
    }
}
