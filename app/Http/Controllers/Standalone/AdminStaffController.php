<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StaffAccessService;
use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class AdminStaffController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $users = User::with('roles.permissions', 'permissions')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('email', 'like', '%'.$request->query('q').'%')->orWhere('name', 'like', '%'.$request->query('q').'%')),
                fn ($q) => $q->where('user_type', 'admin'))
            ->orderBy('name')->paginate(20)->withQueryString();
        $roles = Role::with('permissions')->where('guard_name', 'web')->where('name', 'like', 'staff:%')->orderBy('name')->get();
        $catalog = AdminAccess::catalog();
        $audits = DB::table('staff_access_audits')->latest('id')->limit(30)->get();
        return view('standalone.admin.staff-access', compact('users', 'roles', 'catalog', 'audits'));
    }

    public function saveRole(Request $request, StaffAccessService $service, ?Role $role = null)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[\pL\pN][\pL\pN ._-]*$/u'],
            'permissions' => ['sometimes', 'array'], 'permissions.*' => ['string'],
        ]);
        $service->saveRole($request->user(), $data['name'], $data['permissions'] ?? [], $role);
        return back()->with('success', 'Role saved. Changes apply to every member.');
    }

    public function assign(Request $request, User $user, StaffAccessService $service)
    {
        $data = $request->validate(['roles' => ['sometimes', 'array'], 'roles.*' => ['integer'], 'super_admin' => ['sometimes', 'boolean']]);
        $service->assign($request->user(), $user, $data['roles'] ?? [], $request->boolean('super_admin'));
        return back()->with('success', 'Staff access updated. Empty roles remove operational access.');
    }

    public function destroyRole(Request $request, Role $role, StaffAccessService $service)
    {
        $service->deleteRole($request->user(), $role);
        return back()->with('success', 'Unused role deleted. Its audit history is retained.');
    }
}
