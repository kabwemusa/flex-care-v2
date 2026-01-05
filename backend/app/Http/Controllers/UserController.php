<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserModuleAccess;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
class UserController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of users
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = User::with(['moduleAccess', 'roles']);

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filter by module access
            if ($request->has('module')) {
                $query->whereHas('moduleAccess', function ($q) use ($request) {
                    $q->where('module_code', $request->module)
                      ->where('is_active', true);
                });
            }

            // Search by email or username
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $users = $query->latest()->paginate($perPage);

            return response()->json($users);
        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch users', 500);
        }
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|unique:users,email',
                'username' => 'nullable|string|unique:users,username',
                'password' => 'required|string|min:8',
                'is_system_admin' => 'boolean',
                'is_active' => 'boolean',
                'mfa_enabled' => 'boolean',
                'module_access' => 'nullable|array',
                'module_access.*' => 'string|in:admin,medical,life,motor,travel',
            ]);

            DB::beginTransaction();

            // Hash password
            $validated['password'] = Hash::make($validated['password']);

            // Extract module access
            $moduleAccess = $validated['module_access'] ?? [];
            unset($validated['module_access']);

            // Create user
            $user = User::create($validated);

            // Assign module access
            if (!empty($moduleAccess) && !$user->is_system_admin) {
                foreach ($moduleAccess as $moduleCode) {
                    UserModuleAccess::create([
                        'user_id' => $user->id,
                        'module_code' => $moduleCode,
                        'is_active' => true,
                        'granted_by' => Auth::id()
                    ]);
                }
            }

            DB::commit();

            return $this->success(
                $user->load(['moduleAccess', 'roles']),
                'User created successfully',
                201
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create user. Please try again.'.$e->getMessage(), 500);
        }
    }

    /**
     * Display the specified user
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = User::with([
                'moduleAccess',
                'roles.permissions',
                'permissions'
            ])->findOrFail($id);

            // Get all permissions grouped by guard
            $permissionsByGuard = $user->getAllPermissions()
                ->groupBy('guard_name')
                ->map(function ($permissions) {
                    return $permissions->pluck('name');
                });

            return $this->success([
                'user' => $user,
                'permissions_by_guard' => $permissionsByGuard,
                'module_codes' => $user->getModuleCodes(),
            ], 'User retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('User not found', 404);
        } catch (\Exception $e) {
            Log::error('Error fetching user: ' . $e->getMessage(), [
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch user', 500);
        }
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'email' => ['email', Rule::unique('users')->ignore($user->id)],
                'username' => ['nullable', 'string', Rule::unique('users')->ignore($user->id)],
                'password' => 'nullable|string|min:8',
                'is_system_admin' => 'boolean',
                'is_active' => 'boolean',
                'mfa_enabled' => 'boolean',
                'module_access' => 'nullable|array',
                'module_access.*' => 'string|in:admin,medical,life,motor,travel',
            ]);

            DB::beginTransaction();

            // Hash password if provided
            if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            }

            // Extract module access
            $moduleAccess = $validated['module_access'] ?? null;
            unset($validated['module_access']);

            // Update user
            $user->update($validated);

            // Update module access if provided
            if ($moduleAccess !== null && !$user->is_system_admin) {
                // Remove existing module access
                $user->moduleAccess()->delete();

                // Add new module access
                foreach ($moduleAccess as $moduleCode) {
                    UserModuleAccess::create([
                        'user_id' => $user->id,
                        'module_code' => $moduleCode,
                        'is_active' => true,
                        'granted_by' => Auth::id()
                    ]);
                }
            }

            DB::commit();

            return $this->success(
                $user->fresh(['moduleAccess', 'roles']),
                'User updated successfully'
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->error('User not found', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating user: ' . $e->getMessage(), [
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to update user. Please try again.', 500);
        }
    }

    /**
     * Remove the specified user (soft delete)
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            // Prevent deleting yourself
            if ($user->id === Auth::id()) {
                return $this->error('You cannot delete your own account', 403);
            }

            DB::beginTransaction();

            // Revoke all tokens
            $user->tokens()->delete();

            // Soft delete user
            $user->delete();

            DB::commit();

            return $this->success(null, 'User deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->error('User not found', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting user: ' . $e->getMessage(), [
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to delete user. Please try again.', 500);
        }
    }

    /**
     * Activate a user
     */
    public function activate(string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            DB::beginTransaction();

            $user->update(['is_active' => true]);

            DB::commit();

            return $this->success($user, 'User activated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->error('User not found', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error activating user: ' . $e->getMessage(), [
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to activate user', 500);
        }
    }

    /**
     * Deactivate a user
     */
    public function deactivate(string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            // Prevent deactivating yourself
            if ($user->id === Auth::id()) {
                return $this->error('You cannot deactivate your own account', 403);
            }

            DB::beginTransaction();

            $user->update(['is_active' => false]);

            // Revoke all tokens
            $user->tokens()->delete();

            DB::commit();

            return $this->success($user, 'User deactivated successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->error('User not found', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deactivating user: ' . $e->getMessage(), [
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to deactivate user', 500);
        }
    }

    /**
     * Assign roles to a user
     */
    public function assignRoles(Request $request, string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'roles' => 'required|array',
                'roles.*' => 'string',
                'guard' => 'nullable|string|in:web,medical,life,motor,travel',
            ]);

            $guard = $validated['guard'] ?? 'web';

            // Validate that roles exist for the specified guard
            $roleNames = $validated['roles'];
            $existingRoles = Role::where('guard_name', $guard)
                ->whereIn('name', $roleNames)
                ->pluck('name')
                ->toArray();

            $missingRoles = array_diff($roleNames, $existingRoles);
            if (!empty($missingRoles)) {
                return $this->error(
                    'The following roles do not exist for guard "' . $guard . '": ' . implode(', ', $missingRoles),
                    422
                );
            }

            DB::beginTransaction();

            // Get role IDs for the specified guard
            $roleIds = Role::where('guard_name', $guard)
                ->whereIn('name', $validated['roles'])
                ->pluck('id')
                ->toArray();

            // Remove existing role assignments for this guard
            DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_id', $user->id)
                ->whereIn('role_id', function ($query) use ($guard) {
                    $query->select('id')
                        ->from('roles')
                        ->where('guard_name', $guard);
                })
                ->delete();

            // Insert new role assignments
            $insertData = [];
            foreach ($roleIds as $roleId) {
                $insertData[] = [
                    'role_id' => $roleId,
                    'model_type' => User::class,
                    'model_id' => $user->id,
                ];
            }

            if (!empty($insertData)) {
                DB::table('model_has_roles')->insert($insertData);
            }

            // Clear permission cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            DB::commit();

            return $this->success(
                $user->fresh(['roles.permissions']),
                'Roles assigned successfully'
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->error('User not found', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error assigning roles: ' . $e->getMessage(), [
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to assign roles. Please try again.'. $e->getMessage(), 500);
        }
    }

    /**
     * Get user's permissions
     */
    public function permissions(string $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            $permissionsByGuard = $user->getAllPermissions()
                ->groupBy('guard_name')
                ->map(function ($permissions) {
                    return $permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'guard_name' => $permission->guard_name,
                        ];
                    });
                });

            return $this->success($permissionsByGuard, 'Permissions retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('User not found', 404);
        } catch (\Exception $e) {
            Log::error('Error fetching permissions: ' . $e->getMessage(), [
                'user_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch permissions', 500);
        }
    }
}
