<?php

namespace App\Http\Controllers;

use App\Actions\Staff\CreateStaffMember;
use App\Actions\Staff\DeactivateStaffMember;
use App\Actions\Staff\ReactivateStaffMember;
use App\Actions\Staff\SendStaffInvitation;
use App\Actions\Staff\UpdateStaffMember;
use App\Http\Requests\Staff\StaffMemberRequest;
use App\Http\Requests\Staff\StoreStaffMemberRequest;
use App\Http\Requests\Staff\UpdateStaffMemberRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Staff management from the manager overview's drawer (#27).
 *
 * Every action redirects back so the overview reloads in place: the drawer
 * never navigates away. All of them are behind can:manage_waiter_accounts.
 */
class StaffController extends Controller
{
    public function store(
        StoreStaffMemberRequest $request,
        CreateStaffMember $create,
    ): RedirectResponse {
        $member = $create->handle($this->actor($request), $request->staffData());

        return back()->with('success', "{$member->name} added to tonight.");
    }

    public function update(
        UpdateStaffMemberRequest $request,
        User $member,
        UpdateStaffMember $update,
    ): RedirectResponse {
        $this->ensureManageable($request, $member);

        $update->handle($this->actor($request), $member, $request->staffData());

        return back()->with('success', "{$member->name} updated.");
    }

    public function deactivate(
        Request $request,
        User $member,
        DeactivateStaffMember $deactivate,
    ): RedirectResponse {
        $this->ensureManageable($request, $member);

        $deactivate->handle($this->actor($request), $member);

        return back()->with('success', "{$member->name} deactivated.");
    }

    public function reactivate(
        Request $request,
        User $member,
        ReactivateStaffMember $reactivate,
    ): RedirectResponse {
        $this->ensureManageable($request, $member);

        $reactivate->handle($this->actor($request), $member);

        return back()->with('success', "{$member->name} can sign in again.");
    }

    public function invite(
        Request $request,
        User $member,
        SendStaffInvitation $invite,
    ): RedirectResponse {
        $this->ensureManageable($request, $member);

        abort_if($member->isDeactivated(), 422, 'Reactivate this account first.');

        $invite->handle($this->actor($request), $member);

        return back()->with('success', "Set-password link sent to {$member->name}.");
    }

    private function actor(Request $request): User
    {
        /** @var User $actor */
        $actor = $request->user();

        return $actor;
    }

    /**
     * Same restaurant, not yourself, and only roles a manager may manage.
     * Another restaurant's account is a 404 so its existence is not leaked.
     */
    private function ensureManageable(Request $request, User $member): void
    {
        $actor = $this->actor($request);

        abort_unless(
            $member->restaurant_id !== null
                && (int) $member->restaurant_id === (int) $actor->restaurant_id,
            404,
        );

        abort_if($member->is($actor), 403, 'You cannot change your own account here.');

        abort_unless(
            $member->hasAnyRole(StaffMemberRequest::manageableRoles()),
            403,
            'Manager and admin accounts are managed by the owner.',
        );
    }
}
