<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScopeOfImprovementController extends Controller
{
    /**
     * Email allowed to edit the rich text editor field.
     */
    private const EDITOR_EMAIL = 'president@5core.com';

    public function index()
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        $canEditEditor = strtolower(Auth::user()->email ?? '') === self::EDITOR_EMAIL;

        return view('purchase-master.scope-of-improvement.index', compact('users', 'canEditEditor'));
    }
}
