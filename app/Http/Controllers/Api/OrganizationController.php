<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrganizationInviteMail;
use App\Mail\OrganizationMemberAccountCreatedMail;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\OrganizationMembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

final class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationMembershipService $members
    ) {}

    public function show(Request $request): JsonResponse
    {
        $organization = $this->members
            ->ensureManagedOrganization($request->user());

        if (! $organization) {
            return response()->json([
                'message' =>
                    'Your current subscription does not include team member management.',
                'data' => null,
            ], 403);
        }

        $rows = $organization->members()
            ->with('user')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => [
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                ],
                'plan_name' => $organization->plan?->name,
                'plan_category' => $organization->plan?->category,
                'seat_limit' => $this->members->seatLimit($organization),
                'seats_used' => $this->members->seatsUsed($organization),
                'remaining_seats' =>
                    $this->members->remainingSeats($organization),
                'can_manage' => true,
                'members' => $rows->map(
                    fn (OrganizationMember $member): array => [
                        'id' => $member->id,
                        'membership_id' => $member->id,
                        'user_id' => $member->user_id,
                        'name' => $member->user?->name
                            ?? $member->invited_email
                            ?? 'Pending member',
                        'email' => $member->user?->email
                            ?? $member->invited_email
                            ?? '',
                        'role' => $member->role,
                        'status' => $member->status,
                        'is_pending' => $member->status === 'invited',
                        'invited_at' => $member->invited_at?->toIso8601String(),
                    ]
                )->values(),
            ],
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        $organization = $this->managedOrganization($request);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => [
                'required',
                Rule::in(['admin', 'staff', 'member', 'viewer']),
            ],
            'temporary_password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $email = strtolower(trim($data['email']));

        try {
            $existing = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            if ($existing) {
                $member = $this->members->addExistingUser(
                    $organization,
                    $existing,
                    $data['role']
                );

                return response()->json([
                    'message' =>
                        'Existing user added successfully. Their existing password was not changed.',
                    'data' => [
                        'id' => $member->id,
                        'status' => $member->status,
                        'existing_user' => true,
                    ],
                ], 201);
            }

            if (empty(trim((string) ($data['name'] ?? '')))) {
                return response()->json([
                    'message' =>
                        'Name is required when creating a new member account.',
                    'errors' => [
                        'name' => [
                            'Name is required when creating a new member account.',
                        ],
                    ],
                ], 422);
            }

            if (empty($data['temporary_password'])) {
                return response()->json([
                    'message' =>
                        'Set a temporary password for a new member account.',
                    'errors' => [
                        'temporary_password' => [
                            'Set a temporary password for a new member account.',
                        ],
                    ],
                ], 422);
            }

            $member = $this->members->createAndAddNewUser(
                $organization,
                trim((string) $data['name']),
                $email,
                (string) $data['temporary_password'],
                $data['role']
            );

            $mailSent = true;

            try {
                Mail::to($member->user->email)->send(
                    new OrganizationMemberAccountCreatedMail(
                        $member->user,
                        $organization,
                        $member->role
                    )
                );
            } catch (\Throwable $e) {
                report($e);
                $mailSent = false;
            }

            return response()->json([
                'message' =>
                    'New member account created. Share the temporary password separately.',
                'data' => [
                    'id' => $member->id,
                    'status' => $member->status,
                    'existing_user' => false,
                    'mail_sent' => $mailSent,
                ],
            ], 201);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function editMember(
        Request $request,
        OrganizationMember $member
    ): JsonResponse {
        $organization = $this->managedOrganization($request);

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'role' => [
                'required',
                Rule::in(['admin', 'staff', 'member', 'viewer']),
            ],
        ]);

        if (
            $member->status === 'invited'
            && empty($data['email'])
        ) {
            return response()->json([
                'message' =>
                    'Email is required for a pending invitation.',
            ], 422);
        }

        try {
            $this->members->editMember(
                $organization,
                $member,
                $data
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Member workspace details updated.',
        ]);
    }

    public function updateRole(
        Request $request,
        OrganizationMember $member
    ): JsonResponse {
        $organization = $this->managedOrganization($request);

        $data = $request->validate([
            'role' => [
                'required',
                Rule::in(['admin', 'staff', 'member', 'viewer']),
            ],
        ]);

        try {
            $this->members->updateRole(
                $organization,
                $member,
                $data['role']
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Member role updated.',
        ]);
    }

    public function activate(
        Request $request,
        OrganizationMember $member
    ): JsonResponse {
        $organization = $this->managedOrganization($request);

        $this->members->setActive($organization, $member, true);

        return response()->json([
            'message' => 'Member reactivated.',
        ]);
    }

    public function deactivate(
        Request $request,
        OrganizationMember $member
    ): JsonResponse {
        $organization = $this->managedOrganization($request);

        $this->members->setActive($organization, $member, false);

        return response()->json([
            'message' => 'Member access suspended.',
        ]);
    }

    public function removeMember(
        Request $request,
        OrganizationMember $member
    ): JsonResponse {
        $organization = $this->managedOrganization($request);

        $this->members->remove($organization, $member);

        return response()->json([
            'message' =>
                'Member removed. Their personal diary data was not deleted.',
        ]);
    }

    private function managedOrganization(Request $request): Organization
    {
        $organization = $this->members
            ->ensureManagedOrganization($request->user());

        abort_unless(
            $organization,
            403,
            'Your current subscription does not include team member management.'
        );

        $user = $request->user();

        $allowed =
            (int) $organization->owner_user_id === (int) $user->id
            || (
                isset($user->organization_id)
                && (int) $user->organization_id === (int) $organization->id
                && strtolower((string) ($user->organization_role ?? '')) === 'admin'
            );

        abort_unless($allowed, 403);

        return $organization;
    }

    private function sendInvite(
        OrganizationMember $member,
        Organization $organization
    ): bool {
        try {
            Mail::to($member->invited_email)->send(
                new OrganizationInviteMail($member, $organization)
            );

            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }
}
