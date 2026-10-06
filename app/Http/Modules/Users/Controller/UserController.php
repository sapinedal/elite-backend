<?php

namespace App\Http\Modules\Users\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Users\Models\User;
use App\Http\Modules\Users\Service\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request)
    {
        $includeInactive = $request->boolean('include_inactive', true);
        return response()->json($this->userService->getAllUsers($includeInactive));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'area_id' => 'required|exists:areas,id',
            'position_id' => 'required|exists:positions,id',
            'document' => 'required|string|unique:users',
            'roles' => 'nullable|array',
            'roles.*' => 'string|exists:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        $user = $this->userService->createUser($validated);
        return response()->json($user, 201);
    }

    public function show(User $user)
    {
        $user->load(['kpis', 'area', 'position', 'roles:id,name', 'permissions:id,name']);
        $user->roles_list = $user->roles->pluck('name')->values()->toArray();
        $user->direct_permissions_list = $user->permissions->pluck('name')->values()->toArray();
        return response()->json($user);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'area_id' => 'sometimes|exists:areas,id',
            'position_id' => 'sometimes|exists:positions,id',
            'document' => 'sometimes|string|unique:users,document,' . $user->id,
            'roles' => 'nullable|array',
            'roles.*' => 'string|exists:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        $user = $this->userService->updateUser($user, $validated);
        return response()->json($user);
    }

    public function changePassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed'
        ]);

        $this->userService->changePassword($user, $request->password);
        return response()->json(['message' => 'Password changed successfully']);
    }

    public function destroy(User $user)
    {
        $this->userService->deleteUser($user);
        return response()->json(['message' => 'User deactivated successfully', 'user' => $user->fresh()->load(['area', 'position'])]);
    }

    public function restore($id)
    {
        $user = $this->userService->restoreUser($id);
        return response()->json(['message' => 'User activated successfully', 'user' => $user]);
    }

    public function toggleStatus($id)
    {
        $user = $this->userService->toggleUserStatus($id);
        return response()->json(['message' => 'User status updated successfully', 'user' => $user]);
    }

    public function me(Request $request)
    {
        $user = (auth()->user() ?: User::first())->load(['area', 'position']);
        
        if (!$user) return response()->json(['message' => 'No user found'], 404);

        // Obtenemos todos los permisos (directos e heredados por roles) y roles asignados vía Spatie
        $permissions = $user->getAllPermissions()->pluck('name')->values()->toArray();
        $roles = $user->getRoleNames()->values()->toArray();

        return response()->json([
            'id' => $user->id,
            'nombre' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'document' => $user->document,
            'email' => $user->email,
            'area' => optional($user->area)->name,
            'position' => optional($user->position)->name,
            'permissions' => $permissions,
            'roles' => $roles
        ]);
    }
}
