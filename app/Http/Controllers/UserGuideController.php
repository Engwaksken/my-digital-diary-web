<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class UserGuideController extends Controller
{
    public function index(): View
    {
        return view('user-guide.index');
    }
}
