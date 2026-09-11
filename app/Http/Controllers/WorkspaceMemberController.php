<?php

namespace App\Http\Controllers;

use App\Models\OrganizationWorkspaceInvitation;
use App\Models\User;
use App\Services\OrganizationWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkspaceMemberController extends Controller
{
    public function __construct(
        private readonly OrganizationWorkspaceService $workspace
    ) {}

    public function index(Request $request)
    {
        $organization = $this->workspace
            ->organizationForUser($request->user());

        abort_unless($organization, 403);

        $organizationId = (int) $organization->id;

        abort_unless(
            $this->workspace->canManage(
                $organizationId,
                (int) $request->user()->id
            ),
            403
        );

        $members = $this->workspace->members($organizationId);
        $invitations = $this->workspace
            ->pendingInvitations($organizationId);

        $seatLimit = $this->workspace
            ->seatLimit($organization, $request->user());

        $seatsUsed = $this->workspace
            ->seatsUsed($organizationId);

        return view('workspace.members', compact(
            'organization',
            'members',
            'invitations',
            'seatLimit',
            'seatsUsed'
        ));
    }

    public function invite(Request $request): RedirectResponse
    {
        $organization = $this->managedOrganization($request);
        $organizationId = (int) $organization->id;

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => [
                'required',
                Rule::in(['admin', 'member', 'viewer']),
            ],
        ]);

        $email = strtolower(trim($data['email']));
        $seatLimit = $this->workspace
            ->seatLimit($organization, $request->user());

        if (
            $seatLimit !== null
            && $this->workspace->seatsUsed($organizationId) >= $seatLimit
        ) {
            return back()->withErrors([
                'email' => 'This subscription has reached its member/seat limit.',
            ]);
        }

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existingUser) {
            $this->workspace->addOrReactivateMember(
                $organizationId,
                (int) $existingUser->id,
                $data['role']
            );

            OrganizationWorkspaceInvitation::query()
                ->where('organization_id', $organizationId)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('status', 'pending')
                ->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                ]);

            $this->workspace->log(
                $organizationId,
                (int) $request->user()->id,
                'member_added',
                User::class,
                (int) $existingUser->id,
                [
                    'email' => $email,
                    'role' => $data['role'],
                ]
            );

            return back()->with(
                'success',
                'Existing My Digital Diary user added to the workspace.'
            );
        }

        $rawToken = Str::random(64);

        $invitation = OrganizationWorkspaceInvitation::query()
            ->updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'email' => $email,
                    'status' => 'pending',
                ],
                [
                    'invited_by_user_id' => $request->user()->id,
                    'role' => $data['role'],
                    'token_hash' => hash('sha256', $rawToken),
                    'expires_at' => now()->addDays(7),
                    'cancelled_at' => null,
                ]
            );

        $this->sendInvitation(
            $organization,
            $invitation,
            $rawToken
        );

        $this->workspace->log(
            $organizationId,
            (int) $request->user()->id,
            'member_invited',
            OrganizationWorkspaceInvitation::class,
            $invitation->id,
            [
                'email' => $email,
                'role' => $data['role'],
            ]
        );

        return back()->with(
            'success',
            'Invitation sent. It expires in 7 days.'
        );
    }

    public function resend(
        Request $request,
        OrganizationWorkspaceInvitation $invitation
    ): RedirectResponse {
        $organization = $this->managedOrganization($request);

        abort_unless(
            (int) $invitation->organization_id
                === (int) $organization->id,
            403
        );

        abort_unless($invitation->status === 'pending', 422);

        $rawToken = Str::random(64);

        $invitation->forceFill([
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
            'cancelled_at' => null,
        ])->save();

        $this->sendInvitation(
            $organization,
            $invitation,
            $rawToken
        );

        return back()->with('success', 'Invitation resent.');
    }

    public function cancel(
        Request $request,
        OrganizationWorkspaceInvitation $invitation
    ): RedirectResponse {
        $organization = $this->managedOrganization($request);

        abort_unless(
            (int) $invitation->organization_id
                === (int) $organization->id,
            403
        );

        $invitation->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ])->save();

        return back()->with('success', 'Invitation cancelled.');
    }

    public function role(
        Request $request,
        int $membership
    ): RedirectResponse {
        $organization = $this->managedOrganization($request);

        $data = $request->validate([
            'role' => [
                'required',
                Rule::in(['admin', 'member', 'viewer']),
            ],
        ]);

        $member = $this->workspace
            ->members((int) $organization->id)
            ->firstWhere('membership_id', $membership);

        abort_unless($member, 404);
        abort_if(
            strtolower((string) ($member->role ?? '')) === 'owner',
            422,
            'The workspace owner role cannot be changed here.'
        );

        $this->workspace->updateMemberRole(
            (int) $organization->id,
            $membership,
            $data['role']
        );

        return back()->with('success', 'Member role updated.');
    }

    public function suspend(
        Request $request,
        int $membership
    ): RedirectResponse {
        $organization = $this->managedOrganization($request);

        $this->assertNotOwner(
            (int) $organization->id,
            $membership
        );

        $this->workspace->setMemberActive(
            (int) $organization->id,
            $membership,
            false
        );

        return back()->with('success', 'Member suspended.');
    }

    public function activate(
        Request $request,
        int $membership
    ): RedirectResponse {
        $organization = $this->managedOrganization($request);

        $this->assertNotOwner(
            (int) $organization->id,
            $membership
        );

        $this->workspace->setMemberActive(
            (int) $organization->id,
            $membership,
            true
        );

        return back()->with('success', 'Member activated.');
    }

    public function remove(
        Request $request,
        int $membership
    ): RedirectResponse {
        $organization = $this->managedOrganization($request);

        $this->assertNotOwner(
            (int) $organization->id,
            $membership
        );

        $this->workspace->removeMember(
            (int) $organization->id,
            $membership
        );

        return back()->with(
            'success',
            'Member removed. Their personal diary data remains private and is not deleted.'
        );
    }

    public function showAccept(
        Request $request,
        string $token
    ): View {
        $invitation = $this->invitationForToken($request, $token);

        return view('workspace.accept-invitation', [
            'invitation' => $invitation,
            'token' => $token,
        ]);
    }

    public function accept(
        Request $request,
        string $token
    ): RedirectResponse {
        $invitation = $this->invitationForToken($request, $token);

        $this->workspace->addOrReactivateMember(
            (int) $invitation->organization_id,
            (int) $request->user()->id,
            $invitation->role
        );

        $invitation->forceFill([
            'status' => 'accepted',
            'accepted_at' => now(),
        ])->save();

        return redirect()
            ->route('workspace.index')
            ->with('success', 'Workspace invitation accepted.');
    }

    private function invitationForToken(
        Request $request,
        string $token
    ): OrganizationWorkspaceInvitation {
        $invitation = OrganizationWorkspaceInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'pending')
            ->whereNull('cancelled_at')
            ->firstOrFail();

        abort_if(
            $invitation->expires_at
                && $invitation->expires_at->isPast(),
            410,
            'This invitation has expired.'
        );

        abort_unless(
            strtolower((string) $request->user()->email)
                === strtolower((string) $invitation->email),
            403,
            'Sign in using the email address that received this invitation.'
        );

        return $invitation;
    }

    private function managedOrganization(Request $request): object
    {
        $organization = $this->workspace
            ->organizationForUser($request->user());

        abort_unless($organization, 403);

        abort_unless(
            $this->workspace->canManage(
                (int) $organization->id,
                (int) $request->user()->id
            ),
            403
        );

        return $organization;
    }

    private function assertNotOwner(
        int $organizationId,
        int $membershipId
    ): void {
        $member = $this->workspace
            ->members($organizationId)
            ->firstWhere('membership_id', $membershipId);

        abort_unless($member, 404);

        abort_if(
            strtolower((string) ($member->role ?? '')) === 'owner',
            422,
            'Transfer ownership before changing or removing the owner.'
        );
    }

    private function sendInvitation(
        object $organization,
        OrganizationWorkspaceInvitation $invitation,
        string $rawToken
    ): void {
        $url = route(
            'workspace.invitations.accept',
            ['token' => $rawToken]
        );

        $organizationName =
            $organization->name
            ?? $organization->organization_name
            ?? 'My Digital Diary Workspace';

        Mail::raw(
            "You have been invited to join {$organizationName} on My Digital Diary.\n\n"
            ."Role: {$invitation->role}\n"
            ."Invitation expires in 7 days.\n\n"
            ."Sign in or register using {$invitation->email}, then open:\n{$url}\n\n"
            ."Your personal diary information remains private unless you explicitly share it.",
            function ($message) use (
                $invitation,
                $organizationName
            ): void {
                $message
                    ->to($invitation->email)
                    ->subject(
                        'Invitation to '.$organizationName
                    );
            }
        );
    }
}
