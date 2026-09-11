<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizationWorkspaceInvitation;
use App\Models\User;
use App\Services\OrganizationWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WorkspaceMemberController extends Controller
{
    public function __construct(
        private readonly OrganizationWorkspaceService $workspace
    ) {}

    public function index(Request $request): JsonResponse
    {
        $organization = $this->managedOrganization($request);
        $id = (int) $organization->id;

        $seatLimit = $this->workspace
            ->seatLimit($organization, $request->user());

        return response()->json([
            'data' => [
                'organization' => [
                    'id' => $id,
                    'name' =>
                        $organization->name
                        ?? $organization->organization_name
                        ?? 'Workspace',
                ],
                'seat_limit' => $seatLimit,
                'seats_used' => $this->workspace->seatsUsed($id),
                'members' => $this->workspace->members($id),
                'invitations' => $this->workspace
                    ->pendingInvitations($id),
            ],
        ]);
    }

    public function invite(Request $request): JsonResponse
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

        $seatLimit = $this->workspace
            ->seatLimit($organization, $request->user());

        if (
            $seatLimit !== null
            && $this->workspace->seatsUsed($organizationId) >= $seatLimit
        ) {
            return response()->json([
                'message' => 'This subscription has reached its member/seat limit.',
            ], 422);
        }

        $email = strtolower(trim($data['email']));

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($existingUser) {
            $this->workspace->addOrReactivateMember(
                $organizationId,
                (int) $existingUser->id,
                $data['role']
            );

            return response()->json([
                'message' => 'Existing My Digital Diary user added to the workspace.',
                'added_existing_user' => true,
            ], 201);
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

        return response()->json([
            'message' => 'Invitation sent.',
            'data' => $invitation->fresh(),
        ], 201);
    }

    public function resend(
        Request $request,
        OrganizationWorkspaceInvitation $invitation
    ): JsonResponse {
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

        return response()->json([
            'message' => 'Invitation resent.',
        ]);
    }

    public function cancel(
        Request $request,
        OrganizationWorkspaceInvitation $invitation
    ): JsonResponse {
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

        return response()->json([
            'message' => 'Invitation cancelled.',
        ]);
    }

    public function updateRole(
        Request $request,
        int $membership
    ): JsonResponse {
        $organization = $this->managedOrganization($request);

        $data = $request->validate([
            'role' => [
                'required',
                Rule::in(['admin', 'member', 'viewer']),
            ],
        ]);

        $this->assertNotOwner(
            (int) $organization->id,
            $membership
        );

        $this->workspace->updateMemberRole(
            (int) $organization->id,
            $membership,
            $data['role']
        );

        return response()->json([
            'message' => 'Member role updated.',
        ]);
    }

    public function suspend(
        Request $request,
        int $membership
    ): JsonResponse {
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

        return response()->json([
            'message' => 'Member suspended.',
        ]);
    }

    public function activate(
        Request $request,
        int $membership
    ): JsonResponse {
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

        return response()->json([
            'message' => 'Member activated.',
        ]);
    }

    public function remove(
        Request $request,
        int $membership
    ): JsonResponse {
        $organization = $this->managedOrganization($request);

        $this->assertNotOwner(
            (int) $organization->id,
            $membership
        );

        $this->workspace->removeMember(
            (int) $organization->id,
            $membership
        );

        return response()->json([
            'message' => 'Member removed.',
        ]);
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
            'The workspace owner cannot be changed or removed here.'
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

        $name =
            $organization->name
            ?? $organization->organization_name
            ?? 'My Digital Diary Workspace';

        Mail::raw(
            "You have been invited to join {$name} on My Digital Diary.\n\n"
            ."Role: {$invitation->role}\n\n"
            ."Sign in/register with {$invitation->email} and open:\n{$url}\n\n"
            ."Your personal diary remains private unless you explicitly share an item.",
            function ($message) use ($invitation, $name): void {
                $message
                    ->to($invitation->email)
                    ->subject('Invitation to '.$name);
            }
        );
    }
}
