<?php

namespace App\Http\Modules\Users\Service;

use App\Http\Modules\Users\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function getAllUsers()
    {
        return User::with(['area', 'position'])->get();
    }

    public function createUser(array $data)
    {
        $data['password'] = Hash::make($data['password']);
        if (!isset($data['name'])) {
            $data['name'] = $data['first_name'] . ' ' . $data['last_name'];
        }
        return User::create($data);
    }

    public function updateUser(User $user, array $data)
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        
        if (isset($data['first_name']) || isset($data['last_name'])) {
            $firstName = $data['first_name'] ?? $user->first_name;
            $lastName = $data['last_name'] ?? $user->last_name;
            $data['name'] = $firstName . ' ' . $lastName;
        }

        $user->update($data);
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
}
