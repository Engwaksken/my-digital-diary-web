<?php

namespace App\Http\Controllers;

use App\Mail\OrganizationInviteMail;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Org-admin self-service — invite/activate/deactivate/replace/remove
 * staff, all scoped to the org the current user actually owns or
 * administers. Distinct from the SITE admin area (Admin\...
 * controllers) — this is a regular user managing their OWN team, not a
 * platform operator managing every account.
 */
class OrganizationController extends Controller
{
    /**
     * The organization the current user can manage — either they own
     * it, or they're a member with organization_role = 'admin'. Null if
     * neither, which every method below treats as "nothing to manage."
     */
    private function managedOrganization(Request $request): ?Organization
    {
        $user = $request->user();

        $owned = Organization::where('owner_user_id', $user->id)->first();
        if ($owned) {
            return $owned;
        }

        if ($user->organization_id && $user->organization_role === 'admin') {
            return $user->organization;
        }

        return null;
    }

    public function show(Request $request): View
    {
        $organization = $this->managedOrganization($request);
        $organization?->load('plan');

        return view('organization.show', [
            'organization' => $organization,
            'members' => $organization ? $organization->members()->with('user')->orderByDesc('id')->paginate(15)->withQueryString() : null,
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $organization = $this->managedOrganization($request);
        abort_unless($organization, 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:admin,staff'],
        ]);

        if (! $organization->hasSeatAvailable()) {
            return back()->withErrors(['email' => 'No member slots available on your current plan — remove someone first or upgrade your plan.']);
        }

        if ($organization->members()->where('invited_email', $data['email'])->whereIn('status', ['invited', 'active'])->exists()) {
            return back()->withErrors(['email' => 'That email already has a pending invite or is already a member of this organization.']);
        }

        $member = $organization->members()->create([
            'invited_email' => $data['email'],
            'role' => $data['role'],
            'status' => 'invited',
            'invite_token' => Str::random(48),
            'invited_at' => now(),
        ]);

        Mail::to($data['email'])->send(new OrganizationInviteMail($member, $organization));

        return back()->with('success', "Invitation sent to {$data['email']}.");
    }

    public function activate(Request $request, OrganizationMember $member): RedirectResponse
    {
        $this->authorizeMember($request, $member);

        $member->update(['status' => 'active', 'activated_at' => now()]);
        $member->user?->update(['organization_id' => $member->organization_id, 'organization_role' => $member->role]);

        return back()->with('success', 'Member reactivated.');
    }

    public function deactivate(Request $request, OrganizationMember $member): RedirectResponse
    {
        $this->authorizeMember($request, $member);

        // Deactivating (unlike removing) keeps the seat reserved for
        // this person — their access is paused, but they're not
        // offboarded and the seat isn't freed for someone else yet.
        $member->update(['status' => 'inactive', 'deactivated_at' => now()]);

        return back()->with('success', 'Member deactivated — access paused.');
    }

    /**
     * Offboarding: frees the seat, revokes company access, and — if the
     * person has no verified personal email on file — flags their
     * account so the NEXT time they log in, they're prompted to add and
     * verify one before continuing under a Free/Individual plan. Their
     * own personal tracking data (expenses, health logs, etc.) is never
     * touched here; only their organization membership and subscription
     * standing change.
     */
    public function removeMember(Request $request, OrganizationMember $member): RedirectResponse
    {
        $this->authorizeMember($request, $member);

        $user = $member->user;
        $member->delete(); // frees the seat immediately

        if ($user) {
            $user->update([
                'organization_id' => null,
                'organization_role' => null,
                'offboarded_at' => now(),
                // Grace period rather than an instant hard cutoff — gives
                // them a window to verify a personal email and/or
                // subscribe individually without losing access mid-task.
                // Adjust this if you'd rather cut access immediately.
                'subscription_status' => 'trialing',
                'trial_ends_at' => now()->addDays(7),
                'subscription_plan_id' => null,
                'subscription_expires_at' => null,
            ]);
        }

        return back()->with('success', 'Removed from the organization — a member slot is now free.');
    }

    /**
     * Convenience action: removes whoever currently holds a seat and
     * immediately invites someone new to take it, in one step rather
     * than two separate ones.
     */
    public function replace(Request $request, OrganizationMember $member): RedirectResponse
    {
        $this->authorizeMember($request, $member);

        $data = $request->validate([
            'new_email' => ['required', 'email', 'max:255'],
            'new_role' => ['required', 'in:admin,staff'],
        ]);

        $organization = $member->organization;
        $user = $member->user;
        $member->delete();

        if ($user) {
            $user->update([
                'organization_id' => null,
                'organization_role' => null,
                'offboarded_at' => now(),
                'subscription_status' => 'trialing',
                'trial_ends_at' => now()->addDays(7),
                'subscription_plan_id' => null,
                'subscription_expires_at' => null,
            ]);
        }

        $newMember = $organization->members()->create([
            'invited_email' => $data['new_email'],
            'role' => $data['new_role'],
            'status' => 'invited',
            'invite_token' => Str::random(48),
            'invited_at' => now(),
        ]);

        Mail::to($data['new_email'])->send(new OrganizationInviteMail($newMember, $organization));

        return back()->with('success', "A member slot is now free, and a new invitation was sent to {$data['new_email']}.");
    }

    /**
     * Public — reached from the emailed invite link, before the person
     * necessarily has an account. Existing users get linked immediately;
     * new ones are sent to register first, then land back here.
     */
    public function acceptInvite(Request $request, string $token): RedirectResponse|View
    {
        $member = OrganizationMember::where('invite_token', $token)->where('status', 'invited')->first();

        if (! $member) {
            return view('organization.invite-invalid');
        }

        if (! $request->user()) {
            session(['pending_org_invite_token' => $token]);

            $existingUser = User::where('email', $member->invited_email)->first();

            return redirect()->route($existingUser ? 'login' : 'register')
                ->with('info', 'Log in or create an account with ' . $member->invited_email . ' to accept this invitation.');
        }

        return $this->finalizeInviteAcceptance($request, $member);
    }

    /**
     * Called right after login/registration if a pending invite token
     * was stashed in the session — see EnsureUserHasAccess or the
     * post-login redirect logic, which should check for this. (If that
     * wiring isn't in place yet, this method is still safe to call
     * directly from acceptInvite() above for an already-logged-in user.)
     */
    public function finalizeInviteAcceptance(Request $request, OrganizationMember $member): RedirectResponse
    {
        $user = $request->user();

        if (strcasecmp($user->email, $member->invited_email) !== 0) {
            return redirect()->route('dashboard')->withErrors([
                'invite' => 'This invitation was sent to ' . $member->invited_email . ', which doesn\'t match your logged-in account.',
            ]);
        }

        $member->update(['user_id' => $user->id, 'status' => 'active', 'activated_at' => now()]);
        $user->update(['organization_id' => $member->organization_id, 'organization_role' => $member->role]);

        session()->forget('pending_org_invite_token');

        return redirect()->route('dashboard')->with('success', 'You\'ve joined ' . $member->organization->name . '.');
    }

    private function authorizeMember(Request $request, OrganizationMember $member): void
    {
        $organization = $this->managedOrganization($request);
        abort_unless($organization && $organization->id === $member->organization_id, 403);
    }
}
