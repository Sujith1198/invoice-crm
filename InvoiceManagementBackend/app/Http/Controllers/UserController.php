<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users with pagination and search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->select(['id', 'name', 'email', 'role', 'permissions', 'status', 'created_at', 'updated_at']);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->input('per_page', 10);
        $users = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $users,
        ]);
    }

    /**
     * Store a newly created user with designated role and screen permissions.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => ['required', Rule::in(['admin', 'manager', 'team lead', 'user'])],
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        // Default screen access based on role if permissions array not explicitly supplied
        $permissions = $request->input('permissions');
        if ($permissions === null || !is_array($permissions)) {
            $permissions = match ($validated['role']) {
                'admin' => ['dashboard', 'customers', 'items', 'invoice_generate', 'invoices', 'reports', 'settings', 'users'],
                'manager' => ['dashboard', 'customers', 'items', 'invoice_generate', 'invoices', 'reports'],
                'team lead' => ['dashboard', 'customers', 'items', 'invoice_generate', 'invoices'],
                default => ['dashboard', 'invoices', 'items'],
            };
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'permissions' => $permissions,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "User {$user->name} created successfully with role '{$user->role}'.",
            'data' => $user,
        ], 201);
    }

    /**
     * Display the specified user profile.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?? 'user',
                'permissions' => $user->permissions ?? [],
                'status' => $user->status ?? 'active',
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }

    /**
     * Update the specified user profile, password, role, and screen permissions.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'role' => ['sometimes', 'required', Rule::in(['admin', 'manager', 'team lead', 'user'])],
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        // Prevent deactivating or demoting the primary Super Admin (ID 1)
        if ($user->id === 1) {
            $validated['role'] = 'admin';
            $validated['status'] = 'active';
        }

        $updateData = [
            'name' => $validated['name'] ?? $user->name,
            'email' => $validated['email'] ?? $user->email,
            'role' => $validated['role'] ?? $user->role,
            'status' => $validated['status'] ?? $user->status,
        ];

        if (array_key_exists('permissions', $validated)) {
            $updateData['permissions'] = $validated['permissions'];
        }

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return response()->json([
            'status' => 'success',
            'message' => "User {$user->name} updated successfully.",
            'data' => $user,
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->id === 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Primary Super Administrator account cannot be deleted.',
            ], 403);
        }

        $userName = $user->name;
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => "User {$userName} has been removed successfully.",
        ]);
    }
}
