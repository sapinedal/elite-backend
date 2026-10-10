<?php

namespace App\Http\Modules\Users\Service;

use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function getAllUsers(bool $includeInactive = true)
    {
        $query = User::with(['area', 'position', 'roles:id,name', 'permissions:id,name']);
        if ($includeInactive) {
            $query->withTrashed();
        }
        $users = $query->get();
        return $users->map(function ($u) {
            $u->roles_list = $u->roles->pluck('name')->values()->toArray();
            $u->direct_permissions_list = $u->permissions->pluck('name')->values()->toArray();
            return $u;
        });
    }

    public function createUser(array $data)
    {
        $roles = $data['roles'] ?? [];
        $permissions = $data['permissions'] ?? [];
        unset($data['roles'], $data['permissions']);

        $data['password'] = Hash::make($data['password']);
        if (!isset($data['name'])) {
            $data['name'] = $data['first_name'] . ' ' . $data['last_name'];
        }
        $user = User::create($data);

        if (!empty($roles)) {
            $user->syncRoles($roles);
        } else {
            $user->syncRoles(['empleado']);
        }

        if (!empty($permissions)) {
            $user->syncPermissions($permissions);
        }

        $user->load(['area', 'position', 'roles:id,name', 'permissions:id,name']);
        $user->roles_list = $user->roles->pluck('name')->values()->toArray();
        $user->direct_permissions_list = $user->permissions->pluck('name')->values()->toArray();
        return $user;
    }

    public function updateUser(User $user, array $data)
    {
        $roles = $data['roles'] ?? null;
        $permissions = $data['permissions'] ?? null;
        unset($data['roles'], $data['permissions']);

        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        
        if (isset($data['first_name']) || isset($data['last_name'])) {
            $firstName = $data['first_name'] ?? $user->first_name;
            $lastName = $data['last_name'] ?? $user->last_name;
            $data['name'] = $firstName . ' ' . $lastName;
        }

        $user->update($data);

        if (is_array($roles)) {
            $user->syncRoles($roles);
        }

        if (is_array($permissions)) {
            $user->syncPermissions($permissions);
        }

        $user->load(['area', 'position', 'roles:id,name', 'permissions:id,name']);
        $user->roles_list = $user->roles->pluck('name')->values()->toArray();
        $user->direct_permissions_list = $user->permissions->pluck('name')->values()->toArray();
        return $user;
    }

    public function changePassword(User $user, string $newPassword)
    {
        $user->update([
            'password' => Hash::make($newPassword)
        ]);
        return $user;
    }

    public function deleteUser(User $user)
    {
        return $user->delete();
    }

    public function restoreUser(int|string $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();
        return $user->load(['area', 'position']);
    }

    public function toggleUserStatus(int|string $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        if ($user->trashed()) {
            $user->restore();
        } else {
            $user->delete();
        }
        return $user->fresh()->load(['area', 'position']);
    }
}
