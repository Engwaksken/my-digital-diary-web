<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class TermsOfUseController extends Controller
{
    public function show(): View
    {
        return view('terms-of-use');
    }
}
