<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiQuestion;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiAdminController extends Controller
{
    /**
     * List recent AI chat questions and answers.
     */
    public function index(): View
    {
        $questions = AiQuestion::with('user')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('ai-admin.index', compact('questions'));
    }

    public function escalations()
    {
        return redirect()->route('ai.admin.index');
    }

    public function answerEscalation(Request $request, $id)
    {
        return redirect()->route('ai.admin.index');
    }

    public function knowledge()
    {
        return redirect()->route('ai.admin.index');
    }

    public function uploadKnowledge(Request $request)
    {
        return redirect()->route('ai.admin.index');
    }

    public function reindexWebsite()
    {
        return redirect()->route('ai.admin.index');
    }

    public function analytics()
    {
        return redirect()->route('ai.admin.index');
    }
}
