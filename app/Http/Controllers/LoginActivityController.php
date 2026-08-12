<?php

namespace App\Http\Controllers;

use App\Models\LoginActivity;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginActivityController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = (int) $request->integer('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $search = trim((string) $request->query('q', ''));
        $period = (string) $request->query('period', '');
        $from = $request->query('from');
        $to = $request->query('to');

        $query = LoginActivity::query()
            ->where('user_id', $request->user()->id)
            ->latest('logged_in_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', '%' . $search . '%')
                    ->orWhere('user_agent', 'like', '%' . $search . '%')
                    ->orWhere('guard', 'like', '%' . $search . '%');
            });
        }

        if ($period === 'daily') {
            $query->whereDate('logged_in_at', today());
        } elseif ($period === 'weekly') {
            $query->whereBetween('logged_in_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($period === 'monthly') {
            $query->whereBetween('logged_in_at', [now()->startOfMonth(), now()->endOfMonth()]);
        } elseif ($period === 'range' && $from && $to) {
            $query->whereBetween('logged_in_at', [
                \Illuminate\Support\Carbon::parse($from)->startOfDay(),
                \Illuminate\Support\Carbon::parse($to)->endOfDay(),
            ]);
        }

        $activity = $query->paginate($perPage)->withQueryString();

        $base = LoginActivity::where('user_id', $request->user()->id);
        $stats = [
            'total' => (clone $base)->count(),
            'today' => (clone $base)->whereDate('logged_in_at', today())->count(),
            'this_month' => (clone $base)->whereBetween('logged_in_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
        ];

        return view('activity.login', compact(
            'activity',
            'stats',
            'search',
            'period',
            'from',
            'to',
            'perPage'
        ));
    }
}
