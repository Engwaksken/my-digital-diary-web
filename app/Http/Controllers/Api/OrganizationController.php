<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrganizationInviteMail;
use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Mobile equivalent of OrganizationController — same
 * invite/activate/deactivate/replace/remove logic, JSON instead of
 * redirects. Accepting an invite still happens via the emailed link
 * (which opens in a browser) rather than a mobile-specific flow — no
 * separate accept-invite endpoint needed here.
 */
class OrganizationController extends Controller
{
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

    public function show(Request $request): JsonResponse
    {
        $organization = $this->managedOrganization($request);

        if (! $organization) {
            return response()->json(['data' => null]);
        }

        $organization->load('plan');
        $members = $organization->members()->with('user')->orderByDesc('id')->get();

        return response()->json(['data' => [
            'id' => $organization->id,
            'name' => $organization->name,
            'plan_name' => $organization->plan?->name,
            'plan_category' => $organization->plan?->category,
            'seats_used' => $organization->seatsUsed(),
            'seat_limit' => $organization->seatLimit(),
            'remaining_seats' => $organization->remainingSeats(),
            'members' => $members->map(fn ($m) => [
                'id' => $m->id,
                'email' => $m->user->email ?? $m->invited_email,
                'role' => $m->role,
                'status' => $m->status,
            ]),
        ]]);
    }

    public function invite(Request $request): JsonResponse
    {
        $organization = $this->managedOrganization($request);
        if (! $organization) {
            return response()->json(['message' => 'You do not manage a team.'], 403);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:admin,staff'],
        ]);

        if (! $organization->hasSeatAvailable()) {
            return response()->json(['message' => 'No member slots available on your current plan — remove someone first or upgrade your plan.'], 422);
        }

        if ($organization->members()->where('invited_email', $data['email'])->whereIn('status', ['invited', 'active'])->exists()) {
            return response()->json(['message' => 'That email already has a pending invite or is already a member of this organization.'], 422);
        }

        $member = $organization->members()->create([
            'invited_email' => $data['email'],
            'role' => $data['role'],
            'status' => 'invited',
            'invite_token' => Str::random(48),
            'invited_at' => now(),
        ]);

        Mail::to($data['email'])->send(new OrganizationInviteMail($member, $organization));

        return response()->json(['message' => 'Invitation sent to ' . $data['email'] . '.']);
    }

    public function activate(Request $request, OrganizationMember $member): JsonResponse
    {
        $this->authorizeMember($request, $member);

        $member->update(['status' => 'active', 'activated_at' => now()]);
        $member->user?->update(['organization_id' => $member->organization_id, 'organization_role' => $member->role]);

        return response()->json(['message' => 'Member reactivated.']);
    }

    public function deactivate(Request $request, OrganizationMember $member): JsonResponse
    {
        $this->authorizeMember($request, $member);

        $member->update(['status' => 'inactive', 'deactivated_at' => now()]);

        return response()->json(['message' => 'Member deactivated — access paused.']);
    }

    public function removeMember(Request $request, OrganizationMember $member): JsonResponse
    {
        $this->authorizeMember($request, $member);

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

        return response()->json(['message' => 'Removed from the organization — a member slot is now free.']);
    }

    public function replace(Request $request, OrganizationMember $member): JsonResponse
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

        return response()->json(['message' => 'Member slot is now free, and a new invitation was sent to ' . $data['new_email'] . '.']);
    }

    private function authorizeMember(Request $request, OrganizationMember $member): void
    {
        $organization = $this->managedOrganization($request);
        abort_unless($organization && $organization->id === $member->organization_id, 403);
    }
}
