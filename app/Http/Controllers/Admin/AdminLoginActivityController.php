<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminLoginActivityController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = (int) $request->integer('per_page', 25);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $search = trim((string) $request->query('q', ''));
        $period = (string) $request->query('period', '');
        $from = $request->query('from');
        $to = $request->query('to');
        $userId = (int) $request->integer('user_id', 0);

        $query = LoginActivity::query()->with('user')->latest('logged_in_at');

        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('user_agent', 'like', "%{$search}%")
                    ->orWhere('guard', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
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
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ]);
        }

        $activity = $query->paginate($perPage)->withQueryString();

        $base = LoginActivity::query();
        $stats = [
            'total' => (clone $base)->count(),
            'today' => (clone $base)->whereDate('logged_in_at', today())->count(),
            'week' => (clone $base)->whereBetween('logged_in_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'month' => (clone $base)->whereBetween('logged_in_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
        ];

        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.login-activities.index', compact(
            'activity', 'stats', 'users', 'search', 'period', 'from', 'to', 'userId', 'perPage'
        ));
    }
}
