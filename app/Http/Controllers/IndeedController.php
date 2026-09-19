<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class IndeedController extends Controller
{
    public const EMPLOYER_URL = 'https://employers.indeed.com/';

    public function index(): View
    {
        return view('pages.indeed', [
            'employerUrl' => self::EMPLOYER_URL,
        ]);
    }
}
