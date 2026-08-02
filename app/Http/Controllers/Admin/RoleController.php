<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Roles\StoreRoleRequest;
use App\Http\Requests\Admin\Roles\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RoleController extends Controller implements HasMiddleware
{
    private const PROTECTED_ROLES = ['super_admin', 'customer'];

    public static function middleware(): array
    {
        return ['can:roles.manage'];
    }

    public function index(): Response
    {
        return Inertia::render('admin/roles/Index', [
            'roles' => Role::query()
                ->with('permissions:id,name')
                ->withCount('users')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'permissions' => $r->permissions->pluck('name'),
                    'users_count' => $r->users_count,
                    'protected' => in_array($r->name, self::PROTECTED_ROLES, true),
                ]),
            'permissions' => Permission::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Role::create(['name' => $validated['name']])->syncPermissions($validated['permissions'] ?? []);

        activity()->event('role_created')->withProperties($validated)->log("Role created: {$validated['name']}");

        return back()->with('success', 'Role created.');
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->name === 'super_admin', 403, 'The super admin role cannot be edited.');

        $validated = $request->validated();

        $role->syncPermissions($validated['permissions'] ?? []);

        activity()->event('role_updated')->withProperties($validated)->log("Role permissions updated: {$role->name}");

        return back()->with('success', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if(in_array($role->name, self::PROTECTED_ROLES, true), 403, 'This role cannot be deleted.');

        if ($role->users()->exists()) {
            return back()->with('error', 'Reassign users before deleting this role.');
        }

        $role->delete();

        activity()->event('role_deleted')->log("Role deleted: {$role->name}");

        return back()->with('success', 'Role deleted.');
    }
}
