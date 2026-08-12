<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Admin account management — subscriptions, suspension (login access),
 * roles, and account creation/removal.
 *
 * DELIBERATE BOUNDARY: this controller (and every view it renders) only
 * ever touches columns on the `users` table itself. It never queries
 * plans, incomes, expenses, diet_logs, sleep_logs, health_checkups,
 * projects, education_plans, network_contacts, or personal_relationships.
 * An admin managing the platform should never be able to read what a
 * specific person actually tracked about their own life. If you extend
 * this controller, preserve that boundary.
 */
class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query();

        $search = $request->query('q');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $period = $request->query('period');
        $from = $request->query('from');
        $to = $request->query('to');

        match ($period) {
            'daily' => $query->whereDate('created_at', now()->toDateString()),
            'weekly' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'monthly' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            'range' => ($from && $to)
                ? $query->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                : null,
            default => null,
        };

        $users = $query->orderByDesc('id')->paginate(20)->withQueryString();

        $stats = [
            ['label' => 'Total users', 'value' => (string) User::count(), 'icon' => 'fa-solid fa-users', 'color' => 'blue'],
            ['label' => 'New this week', 'value' => (string) User::where('created_at', '>=', now()->startOfWeek())->count(), 'icon' => 'fa-solid fa-user-plus', 'color' => 'emerald'],
            ['label' => 'On trial', 'value' => (string) User::where('subscription_status', 'trialing')->count(), 'icon' => 'fa-solid fa-hourglass-half', 'color' => 'amber'],
            ['label' => 'Active subscribers', 'value' => (string) User::where('subscription_status', 'active')->count(), 'icon' => 'fa-solid fa-crown', 'color' => 'indigo'],
            ['label' => 'Suspended', 'value' => (string) User::whereNotNull('suspended_at')->count(), 'icon' => 'fa-solid fa-user-slash', 'color' => 'rose'],
        ];

        return view('admin.users.index', compact('users', 'stats', 'search', 'period', 'from', 'to'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function show(User $user): View
    {
        return view('admin.users.show', compact('user'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:user,admin'],
        ]);

        // Deliberately does NOT set data_consent_at — consent must come from
        // the account holder themselves, not be granted on their behalf by
        // whoever created the account. They'll see their consent status
        // (and can review the Privacy Policy) from their own Privacy & Data
        // page once they log in.
        //
        // DOES set email_verified_at, unlike self-registration — an admin
        // creating an account on someone's behalf (e.g. from a support
        // request) has no "click the confirmation link" loop to go
        // through; there's no unverified inbox to wait on here.
        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        if ($blocked = $this->blockIfSelf($request, $user, 'suspend your own account')) {
            return $blocked;
        }

        $user->update(['suspended_at' => now()]);

        return back()->with('success', "{$user->name}'s account has been suspended.");
    }

    public function unsuspend(Request $request, User $user): RedirectResponse
    {
        $user->update(['suspended_at' => null]);

        return back()->with('success', "{$user->name}'s account has been reactivated.");
    }

    public function updateSubscription(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'subscription_status' => ['required', 'in:trialing,active,canceled,expired'],
            'extend_trial_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $update = ['subscription_status' => $data['subscription_status']];

        if ($data['subscription_status'] === 'active') {
            $update['subscribed_at'] = $user->subscribed_at ?? now();
        }

        if (! empty($data['extend_trial_days'])) {
            $base = ($user->trial_ends_at && $user->trial_ends_at->isFuture()) ? $user->trial_ends_at : now();
            // Same TypeError risk as AdminSettingsController's trial_days —
            // validate()'s 'integer' rule doesn't cast the value, and it
            // arrives as a numeric string from the form.
            $update['trial_ends_at'] = $base->copy()->addDays((int) $data['extend_trial_days']);
        }

        $user->update($update);

        return back()->with('success', "{$user->name}'s subscription was updated.");
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        if ($blocked = $this->blockIfSelf($request, $user, 'change your own role')) {
            return $blocked;
        }

        $data = $request->validate([
            'role' => ['required', 'in:user,admin'],
        ]);

        $user->update(['role' => $data['role']]);

        return back()->with('success', "{$user->name} is now a {$data['role']}.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($blocked = $this->blockIfSelf($request, $user, 'delete your own account from here')) {
            return $blocked;
        }

        // Same cascadeOnDelete() foreign keys as self-service account
        // deletion (see PrivacyController::destroyAccount) — removes every
        // module record the user ever created.
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }

    /**
     * Returns a redirect-with-error response if the admin is targeting
     * their own account for a self-service-only action, or null to
     * continue normally.
     */
    private function blockIfSelf(Request $request, User $user, string $action): ?RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            return back()->withErrors(['self' => "You can't {$action} from here. Ask another admin, or use your own Privacy & Data page."]);
        }

        return null;
    }

    /**
     * Bulk suspend/unsuspend/delete from the checkboxes on the Users
     * table. The current admin's own row is never rendered with a
     * checkbox at all (see admin/users/index.blade.php), but this also
     * filters it out server-side as a second, independent safeguard —
     * never trust that a client-side omission alone is enough.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:suspend,unsuspend,delete'],
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $users = User::whereIn('id', $data['user_ids'])
            ->where('id', '!=', $request->user()->id)
            ->get();

        $count = 0;
        foreach ($users as $user) {
            match ($data['action']) {
                'suspend' => $user->update(['suspended_at' => $user->suspended_at ?? now()]),
                'unsuspend' => $user->update(['suspended_at' => null]),
                'delete' => $user->delete(),
            };
            $count++;
        }

        $verb = match ($data['action']) {
            'suspend' => 'suspended',
            'unsuspend' => 'reactivated',
            'delete' => 'deleted',
        };

        return redirect()->route('admin.users.index')->with('success', "{$count} user(s) {$verb}.");
    }
}
