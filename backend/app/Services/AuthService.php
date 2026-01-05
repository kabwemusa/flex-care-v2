<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Attempt to authenticate user and generate token
     *
     * @param  array  $credentials
     * @return array
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        // Find user by email or username
        $user = User::where('email', $credentials['email'])
            ->orWhere('username', $credentials['email'])
            ->first();

        // Validate credentials
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is active
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact support.'],
            ]);
        }

        // Record login activity
        $user->recordLogin(request()->ip());

        // Get user's accessible modules
        $moduleCodes = $user->getModuleCodes();

        // Create token with abilities based on module access
        $token = $user->createToken(
            'auth-token',
            $moduleCodes // Token abilities = module codes
        )->plainTextToken;

        // Group roles by guard
        $rolesByGuard = [];
        foreach ($user->roles as $role) {
            $guard = $role->guard_name;
            if (!isset($rolesByGuard[$guard])) {
                $rolesByGuard[$guard] = [];
            }
            $rolesByGuard[$guard][] = $role->name;
        }

        // Group permissions by guard
        $permissionsByGuard = $user->getAllPermissions()->groupBy('guard_name')->map(function ($permissions) {
            return $permissions->pluck('name')->toArray();
        })->toArray();

        return [
            'user' => $user,
            'token' => $token,
            'modules' => $moduleCodes,
            'roles' => $rolesByGuard,
            'permissions' => $permissionsByGuard,
        ];
    }

    /**
     * Revoke all tokens for a user (logout)
     *
     * @param  User  $user
     * @return void
     */
    public function logout(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Get user context with modules, roles, and permissions
     *
     * @param  User  $user
     * @return array
     */
    public function getUserContext(User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'username' => $user->username,
                'is_system_admin' => $user->is_system_admin,
                'is_active' => $user->is_active,
                'mfa_enabled' => $user->mfa_enabled,
            ],
            'modules' => $user->getModuleCodes(),
            'module_access' => $user->activeModules()
                ->get()
                ->map(fn($access) => [
                    'module_code' => $access->module_code,
                    'granted_at' => $access->granted_at,
                ]),
            'roles' => $user->getRoleNames()->groupBy(function ($role) use ($user) {
                // Group roles by guard
                $roleModel = $user->roles()->where('name', $role)->first();
                return $roleModel?->guard_name ?? 'web';
            }),
            'permissions' => $user->getAllPermissions()->groupBy('guard_name')->map(function ($permissions) {
                return $permissions->pluck('name');
            }),
        ];
    }

    /**
     * Grant module access to a user
     *
     * @param  User  $user
     * @param  string  $moduleCode
     * @param  User  $grantedBy
     * @return UserModuleAccess
     */
    public function grantModuleAccess(User $user, string $moduleCode, User $grantedBy): UserModuleAccess
    {
        return UserModuleAccess::updateOrCreate(
            [
                'user_id' => $user->id,
                'module_code' => $moduleCode,
            ],
            [
                'is_active' => true,
                'granted_at' => now(),
                'granted_by' => $grantedBy->id,
            ]
        );
    }

    /**
     * Revoke module access from a user
     *
     * @param  User  $user
     * @param  string  $moduleCode
     * @return bool
     */
    public function revokeModuleAccess(User $user, string $moduleCode): bool
    {
        return UserModuleAccess::where('user_id', $user->id)
            ->where('module_code', $moduleCode)
            ->update(['is_active' => false]);
    }
}
