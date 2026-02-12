<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiEscalation;
use App\Models\AiKnowledgeBase;
use App\Models\AiKnowledgeFile;
use App\Models\AiQuestion;
use App\Models\AiTrainingLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if ($request->user() && !$request->user()->is5CoreMember()) {
                abort(403, 'Access denied. Only 5Core members can access AI admin.');
            }
            return $next($request);
        });
    }

    /**
     * Admin dashboard with tabs: Chat History, Escalations, Training Queue, Knowledge Files.
     */
    public function index(): View
    {
        $questions = AiQuestion::with('user')
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'questions_page');

        return view('ai.admin-index', [
            'questions' => $questions,
            'activeTab' => 'chat',
        ]);
    }

    /**
     * Escalations list with optional filter (pending/answered).
     */
    public function escalations(Request $request): View
    {
        $query = AiEscalation::with('user')->orderByDesc('created_at');
        if ($request->filled('status') && in_array($request->status, ['pending', 'answered', 'closed'])) {
            $query->where('status', $request->status);
        }
        $escalations = $query->paginate(20);

        return view('ai.admin-index', [
            'escalations' => $escalations,
            'activeTab' => 'escalations',
        ]);
    }

    /**
     * Unapproved training logs (queue for approval).
     */
    public function trainingLogs(): View
    {
        $logs = AiTrainingLog::with('escalation')->where('is_approved', false)->orderByDesc('created_at')->paginate(20);

        return view('ai.admin-index', [
            'trainingLogs' => $logs,
            'activeTab' => 'training',
        ]);
    }

    /**
     * Approve a training log: insert into knowledge base and set is_approved = true.
     */
    public function approveTraining(int $id): RedirectResponse
    {
        $log = AiTrainingLog::findOrFail($id);
        if ($log->is_approved) {
            return redirect()->route('ai.admin.training')->with('info', 'Already approved.');
        }

        AiKnowledgeBase::create([
            'category' => 'General',
            'subcategory' => null,
            'question_pattern' => $log->question,
            'answer_steps' => is_string($log->answer) ? [$log->answer] : (array) $log->answer,
            'video_link' => null,
            'tags' => [],
        ]);

        $log->update(['is_approved' => true]);

        return redirect()->route('ai.admin.training')->with('success', 'Training log approved and added to knowledge base.');
    }

    /**
     * Knowledge files (uploaded CSVs) and processing status.
     */
    public function knowledgeFiles(): View
    {
        $files = AiKnowledgeFile::orderByDesc('created_at')->paginate(20);

        return view('ai.admin-index', [
            'knowledgeFiles' => $files,
            'activeTab' => 'files',
        ]);
    }
}
