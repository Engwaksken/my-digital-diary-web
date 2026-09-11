<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\OrganizationInviteMail;
use App\Mail\OrganizationMemberAccountCreatedMail;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\OrganizationMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;

final class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationMembershipService $members
    ) {}

    public function show(Request $request): View
    {
        $organization = $this->members
            ->ensureManagedOrganization($request->user());

        return view('organization.show', [
            'organization' => $organization,
            'members' => $organization
                ? $organization->members()
                    ->with('user')
                    ->orderByRaw(
                        "FIELD(status, 'active', 'invited', 'inactive')"
                    )
                    ->orderByDesc('id')
                    ->paginate(15)
                    ->withQueryString()
                : null,
            'seatLimit' => $organization
                ? $this->members->seatLimit($organization)
                : 0,
            'seatsUsed' => $organization
                ? $this->members->seatsUsed($organization)
                : 0,
            'remainingSeats' => $organization
                ? $this->members->remainingSeats($organization)
                : 0,
            'eligibleForTeam' => $this->members
                ->canUseTeamWorkspace($request->user()),
        ]);
    }

    public function invite(Request $request): RedirectResponse
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

        $existing = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        try {
            if ($existing) {
                $this->members->addExistingUser(
                    $organization,
                    $existing,
                    $data['role']
                );

                return back()->with(
                    'success',
                    'Existing My Digital Diary user added successfully. Their existing password was not changed.'
                );
            }

            if (empty(trim((string) ($data['name'] ?? '')))) {
                return back()->withErrors([
                    'name' => 'Name is required when creating a new member account.',
                ])->withInput();
            }

            if (empty($data['temporary_password'])) {
                return back()->withErrors([
                    'temporary_password' =>
                        'Set a temporary password for a new member account.',
                ])->withInput();
            }

            $member = $this->members->createAndAddNewUser(
                $organization,
                trim((string) $data['name']),
                $email,
                (string) $data['temporary_password'],
                $data['role']
            );

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
            }

            return back()->with(
                'success',
                'New member account created. Share the temporary password with the member through a separate trusted channel.'
            );
        } catch (RuntimeException $e) {
            return back()->withErrors([
                'email' => $e->getMessage(),
            ])->withInput();
        }
    }

    public function editMember(
        Request $request,
        OrganizationMember $member
    ): RedirectResponse {
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
            return back()->withErrors([
                'email' => 'Email is required for a pending invitation.',
            ]);
        }

        try {
            $this->members->editMember(
                $organization,
                $member,
                $data
            );
        } catch (RuntimeException $e) {
            return back()->withErrors([
                'member' => $e->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            'Member workspace details updated.'
        );
    }

    public function updateRole(
        Request $request,
        OrganizationMember $member
    ): RedirectResponse {
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
            return back()->withErrors(['role' => $e->getMessage()]);
        }

        return back()->with('success', 'Member role updated.');
    }

    public function activate(
        Request $request,
        OrganizationMember $member
    ): RedirectResponse {
        $organization = $this->managedOrganization($request);

        $this->members->setActive($organization, $member, true);

        return back()->with('success', 'Member reactivated.');
    }

    public function deactivate(
        Request $request,
        OrganizationMember $member
    ): RedirectResponse {
        $organization = $this->managedOrganization($request);

        $this->members->setActive($organization, $member, false);

        return back()->with('success', 'Member access suspended.');
    }

    public function removeMember(
        Request $request,
        OrganizationMember $member
    ): RedirectResponse {
        $organization = $this->managedOrganization($request);

        $this->members->remove($organization, $member);

        return back()->with(
            'success',
            'Member removed. Their personal diary data was not deleted.'
        );
    }

    public function replace(
        Request $request,
        OrganizationMember $member
    ): RedirectResponse {
        $organization = $this->managedOrganization($request);

        $data = $request->validate([
            'new_email' => ['required', 'email', 'max:255'],
            'new_role' => [
                'required',
                Rule::in(['admin', 'staff', 'member', 'viewer']),
            ],
        ]);

        $this->members->remove($organization, $member);

        $request->merge([
            'email' => $data['new_email'],
            'role' => $data['new_role'],
        ]);

        return $this->invite($request);
    }

    public function acceptInvite(
        Request $request,
        string $token
    ): RedirectResponse|View {
        $member = OrganizationMember::query()
            ->where('invite_token', $token)
            ->where('status', 'invited')
            ->first();

        if (! $member) {
            return view('organization.invite-invalid');
        }

        if (! $request->user()) {
            session(['pending_org_invite_token' => $token]);

            $existingUser = User::query()
                ->whereRaw(
                    'LOWER(email) = ?',
                    [strtolower((string) $member->invited_email)]
                )
                ->first();

            return redirect()
                ->route($existingUser ? 'login' : 'register')
                ->with(
                    'info',
                    'Sign in or register using '
                    .$member->invited_email
                    .' to accept this invitation.'
                );
        }

        abort_unless(
            strtolower((string) $request->user()->email)
                === strtolower((string) $member->invited_email),
            403,
            'Use the email address that received this invitation.'
        );

        $member->forceFill([
            'user_id' => $request->user()->id,
            'status' => 'active',
            'invite_token' => null,
            'activated_at' => now(),
            'deactivated_at' => null,
        ])->save();

        $organization = $member->organization;

        if ($organization) {
            $this->members->addExistingUser(
                $organization,
                $request->user(),
                $member->role
            );
        }

        return redirect()
            ->route('organization.show')
            ->with('success', 'Organization invitation accepted.');
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
