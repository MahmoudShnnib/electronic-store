<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\StoreUserRequest;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use App\Models\User;
use App\Support\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Staff user management (create staff accounts, assign roles, deactivate).
 */
final class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:users.manage'];
    }

    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('roles:id,name')
            ->whereHas('roles', fn ($q) => $q->whereNot('name', 'customer'))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($qq) => $qq
                ->whereRaw('name ILIKE ?', ['%'.$request->query('q').'%'])
                ->orWhereRaw('email ILIKE ?', ['%'.$request->query('q').'%'])))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'roles' => $u->roles->pluck('name'),
                'is_active' => $u->is_active,
                'last_login_at' => Dates::relative($u->last_login_at),
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'roles' => Role::query()->whereNot('name', 'customer')->pluck('name'),
            'filters' => (object) $request->only(['q']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Only a super admin can mint another super admin.
        if (in_array('super_admin', $validated['roles'], true) && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Only a super admin can assign the super admin role.');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_active' => true,
        ]);

        // Staff accounts are created by an admin, so treat the address as verified.
        $user->markEmailAsVerified();

        $user->syncRoles($validated['roles']);

        activity()->performedOn($user)->event('staff_created')->log("Staff account created: {$user->email}");

        return back()->with('success', __('Staff user created.'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        if ((in_array('super_admin', $validated['roles'], true) || $user->hasRole('super_admin'))
            && ! $request->user()->hasRole('super_admin')) {
            abort(403, 'Only a super admin can manage super admin accounts.');
        }

        if ($user->id === $request->user()->id && ! ($validated['is_active'] ?? true)) {
            return back()->with('error', __('You cannot deactivate your own account.'));
        }

        $user->update([
            'name' => $validated['name'],
            'is_active' => (bool) ($validated['is_active'] ?? $user->is_active),
            ...(isset($validated['password']) ? ['password' => $validated['password']] : []),
        ]);

        $user->syncRoles($validated['roles']);

        activity()->performedOn($user)->event('staff_updated')->log("Staff account updated: {$user->email}");

        return back()->with('success', __('User updated.'));
    }
}
