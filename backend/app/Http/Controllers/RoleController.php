<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of roles
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $guard = $request->get('guard', 'web');

            $roles = Role::where('guard_name', $guard)
                ->with('permissions')
                ->get();

            return $this->success($roles, 'Roles retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching roles: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch roles', 500);
        }
    }

    /**
     * Display the specified role
     */
    public function show(string $id): JsonResponse
    {
        try {
            $role = Role::with('permissions')->findOrFail($id);

            return $this->success($role, 'Role retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Role not found', 404);
        } catch (\Exception $e) {
            Log::error('Error fetching role: ' . $e->getMessage(), [
                'role_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch role', 500);
        }
    }

    /**
     * Create a new role
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|unique:roles,name',
                'guard_name' => 'required|string|in:web,medical,life,motor,travel',
                'permissions' => 'nullable|array',
                'permissions.*' => 'string|exists:permissions,name',
            ]);

            DB::beginTransaction();

            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => $validated['guard_name'],
            ]);

            if (isset($validated['permissions'])) {
                $role->givePermissionTo($validated['permissions']);
            }

            DB::commit();

            return $this->success(
                $role->load('permissions'),
                'Role created successfully',
                201
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating role: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to create role. Please try again.', 500);
        }
    }

    /**
     * Update the specified role
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $role = Role::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|unique:roles,name,' . $id,
                'permissions' => 'nullable|array',
                'permissions.*' => 'string|exists:permissions,name',
            ]);

            DB::beginTransaction();

            $role->update(['name' => $validated['name']]);

            if (isset($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }

            DB::commit();

            return $this->success(
                $role->fresh(['permissions']),
                'Role updated successfully'
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->error('Role not found', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating role: ' . $e->getMessage(), [
                'role_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to update role. Please try again.', 500);
        }
    }

    /**
     * Remove the specified role
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $role = Role::findOrFail($id);

            // Prevent deleting system roles
            $systemRoles = ['System Administrator', 'User Manager', 'Auditor'];
            if (in_array($role->name, $systemRoles)) {
                return $this->error('Cannot delete system role', 403);
            }

            DB::beginTransaction();

            $role->delete();

            DB::commit();

            return $this->success(null, 'Role deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->error('Role not found', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting role: ' . $e->getMessage(), [
                'role_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to delete role. Please try again.', 500);
        }
    }

    /**
     * Get all permissions for a guard
     */
    public function permissions(Request $request): JsonResponse
    {
        try {
            $guard = $request->get('guard', 'web');

            $permissions = Permission::where('guard_name', $guard)
                ->get()
                ->groupBy(function ($permission) {
                    // Group by category (e.g., 'medical.schemes.view' -> 'medical.schemes')
                    $parts = explode('.', $permission->name);
                    if (count($parts) >= 2) {
                        return $parts[0] . '.' . $parts[1];
                    }
                    return $parts[0];
                });

            return $this->success($permissions, 'Permissions retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching permissions: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch permissions', 500);
        }
    }
}
