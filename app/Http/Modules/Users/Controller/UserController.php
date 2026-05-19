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

    public function index()
    {
        return response()->json($this->userService->getAllUsers());
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
            'document' => 'required|string|unique:users',  // se elimina roles y se deja como un solo usuario
        ]);

        $user = $this->userService->createUser($validated);
        return response()->json($user->load(['area', 'position']), 201);
    }

    public function show(User $user)
    {
        return response()->json($user->load(['kpis', 'area', 'position']));
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
        ]);

        $user = $this->userService->updateUser($user, $validated);
        return response()->json($user->load(['area', 'position']));
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
        return response()->json(null, 204);
    }

    public function me(Request $request)
    {
        // For now returning the first user or auth user if implemented
        $user = (auth()->user() ?: User::first())->load(['area', 'position']);
        
        if (!$user) return response()->json(['message' => 'No user found'], 404);

        // Simulamos la verificación de permisos para la bitácora de tareas.
        // Todo el personal puede agregar tareas ('bitacora.crear'), pero solo roles
        // específicos de gerencia/dirección o administradores pueden editar ('bitacora.editar' y 'bitacora.eliminar').
        $isEditor = str_contains(strtolower(optional($user->position)->name), 'director') || 
                    str_contains(strtolower(optional($user->position)->name), 'gerente') ||
                    $user->email === 'admin@elite.com' ||
                    str_contains(strtolower($user->name), 'admin');

        $permissions = ['admin', 'evaluar', 'ver-historial', 'bitacora.crear'];
        if ($isEditor) {
            $permissions[] = 'bitacora.editar';
            $permissions[] = 'bitacora.eliminar';
        }

        return response()->json([
            'id' => $user->id,
            'nombre' => $user->name,
            'email' => $user->email,
            'area' => optional($user->area)->name,
            'position' => optional($user->position)->name,
            'permissions' => $permissions,
            'roles' => $isEditor ? ['admin'] : ['empleado']
        ]);
    }
}
