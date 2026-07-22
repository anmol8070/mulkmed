<?php

namespace App\Http\Controllers;

use App\Helpers\Helpers;
use App\Models\Admin;
use App\Models\AdminRoles;
use App\Models\GlobalFunction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class AdminManagementController extends Controller
{
    private function availableModules(): array
    {
        return Helpers::allPermissionKeys();
    }

    private function formatModuleLabels(?string $moduleAccess): string
    {
        if (empty($moduleAccess)) {
            return __('All Modules');
        }

        $keys = json_decode($moduleAccess, true) ?: [];

        return collect($keys)
            ->map(fn ($key) => Helpers::permissionLabel($key))
            ->take(8)
            ->implode(', ') . (count($keys) > 8 ? '...' : '');
    }

    private function ensureFullAdmin(): void
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            abort(403);
        }

        $admin = Admin::where('user_id', $userId)->first();
        if (!$admin || (int) $admin->admin_role_id !== 1) {
            abort(403);
        }
    }

    public function index()
    {
        $this->ensureFullAdmin();

        return view('adminManagement', [
            'roles' => AdminRoles::where('status', 1)->orderBy('id')->get(),
        ]);
    }

    private function roleFormViewData(?AdminRoles $role = null): array
    {
        return [
            'role' => $role,
            'allRoles' => AdminRoles::orderBy('id')->get(),
            'permissionTree' => Helpers::sidebarPermissionTree(),
            'selectedPermissions' => $role
                ? (json_decode($role->module_access ?? '[]', true) ?: [])
                : [],
        ];
    }

    public function createRole()
    {
        $this->ensureFullAdmin();

        return view('adminRoleForm', $this->roleFormViewData());
    }

    public function editRoleForm($id)
    {
        $this->ensureFullAdmin();

        $role = AdminRoles::findOrFail($id);

        return view('adminRoleForm', $this->roleFormViewData($role));
    }

    public function saveAdminRole(Request $request)
    {
        $this->ensureFullAdmin();

        $roleId = $request->input('id');
        $isEdit = !empty($roleId);

        $request->validate([
            'name' => 'required|string|max:100|unique:admin_roles,name' . ($isEdit ? ',' . $roleId : ''),
            'module_access' => 'nullable|array',
            'module_access.*' => ['string', function ($attribute, $value, $fail) {
                if (!in_array($value, Helpers::allPermissionKeys(), true)) {
                    $fail('Invalid permission selected.');
                }
            }],
        ]);

        if ($isEdit) {
            $role = AdminRoles::findOrFail($roleId);
        } else {
            $role = new AdminRoles();
            $role->status = 1;
        }

        $role->name = $request->name;

        if ((int) ($role->id ?? 0) !== 1) {
            $role->module_access = json_encode(array_values(array_unique($request->input('module_access', []))));
        }

        $role->save();

        return GlobalFunction::sendSimpleResponse(
            true,
            $isEdit ? 'Admin role updated successfully!' : 'Admin role added successfully!'
        );
    }

    public function fetchAdminRolesList(Request $request)
    {
        $this->ensureFullAdmin();

        $totalData = AdminRoles::count();
        $limit = $request->input('length');
        $start = $request->input('start');
        $dir = $request->input('order.0.dir', 'desc');

        $query = AdminRoles::query();

        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $totalFiltered = (clone $query)->count();
        $result = $query->orderBy('id', $dir)
            ->offset($start)
            ->limit($limit)
            ->get();

        $data = [];
        foreach ($result as $item) {
            $modules = $this->formatModuleLabels($item->module_access);

            $onOff = $item->status == 1
                ? '<label class="switch"><input rel="' . $item->id . '" type="checkbox" class="roleStatusToggle" checked><span class="slider round"></span></label>'
                : '<label class="switch"><input rel="' . $item->id . '" type="checkbox" class="roleStatusToggle"><span class="slider round"></span></label>';

            $edit = '<a href="' . url('adminManagement/role/edit/' . $item->id) . '" class="mr-2 btn btn-primary text-white">' . __('Edit') . '</a>';
            $delete = (int) $item->id === 1
                ? ''
                : '<a href="" class="mr-2 btn btn-danger text-white deleteRole" rel="' . $item->id . '">' . __('Delete') . '</a>';

            $data[] = [
                $item->id,
                $item->name,
                $modules,
                $onOff,
                $edit . $delete,
            ];
        }

        echo json_encode([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
        exit();
    }

    public function toggleAdminRoleStatus($id)
    {
        $this->ensureFullAdmin();

        if ((int) $id === 1) {
            return GlobalFunction::sendSimpleResponse(false, 'Cannot change status of the main admin role.');
        }

        $role = AdminRoles::findOrFail($id);
        $role->status = $role->status == 1 ? 0 : 1;
        $role->save();

        return GlobalFunction::sendSimpleResponse(true, 'Status updated successfully!');
    }

    public function deleteAdminRole($id)
    {
        $this->ensureFullAdmin();

        if ((int) $id === 1) {
            return GlobalFunction::sendSimpleResponse(false, 'Cannot delete the main admin role.');
        }

        $usersCount = Admin::where('admin_role_id', $id)->count();
        if ($usersCount > 0) {
            return GlobalFunction::sendSimpleResponse(false, 'Cannot delete role assigned to admin users.');
        }

        AdminRoles::where('id', $id)->delete();

        return GlobalFunction::sendSimpleResponse(true, 'Admin role deleted successfully!');
    }

    public function fetchAdminUsersList(Request $request)
    {
        $this->ensureFullAdmin();

        $totalData = Admin::count();
        $limit = $request->input('length');
        $start = $request->input('start');
        $dir = $request->input('order.0.dir', 'desc');

        $query = Admin::join('admin_roles', 'admin_user.admin_role_id', '=', 'admin_roles.id')
            ->select('admin_user.*', 'admin_roles.name as role_name');

        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('admin_user.user_name', 'LIKE', "%{$search}%")
                    ->orWhere('admin_roles.name', 'LIKE', "%{$search}%");
            });
        }

        $totalFiltered = (clone $query)->count();
        $result = $query->orderBy('admin_user.user_id', $dir)
            ->offset($start)
            ->limit($limit)
            ->get();

        $data = [];
        foreach ($result as $item) {
            $userType = (int) $item->user_type === 1 ? __('Admin') : __('Tester');

            $edit = '<a href="" class="mr-2 btn btn-primary text-white editUser" rel="' . $item->user_id . '" data-username="' . e($item->user_name) . '" data-role="' . $item->admin_role_id . '" data-usertype="' . $item->user_type . '">' . __('Edit') . '</a>';
            $delete = (int) $item->user_id === (int) Session::get('user_id')
                ? ''
                : '<a href="" class="mr-2 btn btn-danger text-white deleteUser" rel="' . $item->user_id . '">' . __('Delete') . '</a>';

            $data[] = [
                $item->user_id,
                $item->user_name,
                $item->role_name,
                $userType,
                $edit . $delete,
            ];
        }

        echo json_encode([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
        exit();
    }

    public function addAdminUser(Request $request)
    {
        $this->ensureFullAdmin();

        $request->validate([
            'user_name' => 'required|string|max:100|unique:admin_user,user_name',
            'user_password' => 'required|string|min:3|max:100',
            'admin_role_id' => 'required|integer|exists:admin_roles,id',
            'user_type' => 'required|in:0,1',
        ]);

        $admin = new Admin();
        $admin->user_name = $request->user_name;
        $admin->user_password = $request->user_password;
        $admin->admin_role_id = $request->admin_role_id;
        $admin->user_type = $request->user_type;
        $admin->save();

        return GlobalFunction::sendSimpleResponse(true, 'Admin user added successfully!');
    }

    public function editAdminUser(Request $request)
    {
        $this->ensureFullAdmin();

        $request->validate([
            'user_id' => 'required|integer|exists:admin_user,user_id',
            'user_name' => 'required|string|max:100|unique:admin_user,user_name,' . $request->user_id . ',user_id',
            'user_password' => 'nullable|string|min:3|max:100',
            'admin_role_id' => 'required|integer|exists:admin_roles,id',
            'user_type' => 'required|in:0,1',
        ]);

        $admin = Admin::findOrFail($request->user_id);
        $admin->user_name = $request->user_name;
        if ($request->filled('user_password')) {
            $admin->user_password = $request->user_password;
        }
        $admin->admin_role_id = $request->admin_role_id;
        $admin->user_type = $request->user_type;
        $admin->save();

        return GlobalFunction::sendSimpleResponse(true, 'Admin user updated successfully!');
    }

    public function deleteAdminUser($id)
    {
        $this->ensureFullAdmin();

        if ((int) $id === (int) Session::get('user_id')) {
            return GlobalFunction::sendSimpleResponse(false, 'You cannot delete your own account.');
        }

        Admin::where('user_id', $id)->delete();

        return GlobalFunction::sendSimpleResponse(true, 'Admin user deleted successfully!');
    }
}
