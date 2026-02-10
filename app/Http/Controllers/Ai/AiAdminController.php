<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiQuestion;
use Illuminate\View\View;

class AiAdminController extends Controller
{
    /**
     * List recent AI questions and answers for admin.
     */
    public function index(): View
    {
        $questions = AiQuestion::with('user')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('ai.admin-index', compact('questions'));
    }
}
