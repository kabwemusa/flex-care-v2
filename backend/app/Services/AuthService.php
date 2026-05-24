<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Attempt to authenticate user and generate a Sanctum token.
     *
     * Security notes:
     *  - A dummy hash check is always performed when the user is not found so
     *    that response timing is indistinguishable from a wrong-password attempt,
     *    preventing username enumeration via timing differences.
     *  - Failed-attempt counters and lockout are checked before the password
     *    comparison so that a locked account can be detected quickly.
     *
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])
            ->orWhere('username', $credentials['email'])
            ->first();

        // Always run a constant-time hash check, even when the user doesn't
        // exist, to prevent timing-based username enumeration.
        $passwordCorrect = Hash::check(
            $credentials['password'],
            $user?->password ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi'
        );

        // Account locked — checked after the hash so timing is consistent.
        if ($user && $user->isLocked()) {
            $minutesRemaining = now()->diffInMinutes($user->locked_until);
            throw ValidationException::withMessages([
                'email' => ["Your account is locked due to multiple failed login attempts. Please try again in {$minutesRemaining} minutes."],
            ]);
        }

        if (!$user || !$passwordCorrect) {
            if ($user) {
                $user->incrementFailedLoginAttempts();
                $user->refresh();

                $maxAttempts      = config('password.lockout.max_attempts', 5);
                $remainingAttempts = max(0, $maxAttempts - $user->failed_login_attempts);
                $warningThreshold = (int) ceil($maxAttempts * 0.75);

                if ($user->failed_login_attempts >= $warningThreshold && $remainingAttempts > 0) {
                    throw ValidationException::withMessages([
                        'email' => ["The provided credentials are incorrect. Warning: You have {$remainingAttempts} login attempt(s) remaining before your account is locked."],
                    ]);
                }
            }

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact support.'],
            ]);
        }

        $user->resetFailedLoginAttempts();

        $passwordExpired      = $user->passwordExpired();
        $passwordExpiringSoon = $user->passwordExpiringSoon();
        $daysUntilExpiration  = $user->daysUntilPasswordExpires();

        $user->recordLogin(request()->ip());

        $moduleCodes = $user->getModuleCodes();
        $token       = $user->createToken('auth-token', $moduleCodes)->plainTextToken;

        // Group roles by guard — uses the already-loaded relationship, no extra queries.
        $rolesByGuard = $user->roles
            ->groupBy('guard_name')
            ->map(fn ($roles) => $roles->pluck('name')->all())
            ->all();

        // Group permissions by guard — uses getAllPermissions() which Spatie caches.
        $permissionsByGuard = $user->getAllPermissions()
            ->groupBy('guard_name')
            ->map(fn ($perms) => $perms->pluck('name')->all())
            ->all();

        return [
            'user'        => $user,
            'token'       => $token,
            'modules'     => $moduleCodes,
            'roles'       => $rolesByGuard,
            'permissions' => $permissionsByGuard,
            'password_status' => [
                'expired'            => $passwordExpired,
                'expiring_soon'      => $passwordExpiringSoon,
                'days_until_expiration' => $daysUntilExpiration,
                'force_change'       => $user->force_password_change,
            ],
        ];
    }

    /**
     * Revoke all tokens for a user (logout).
     */
    public function logout(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Get the full user context (modules, roles, permissions).
     *
     * Uses the already-loaded `roles` relationship to avoid N+1 queries.
     */
    public function getUserContext(User $user): array
    {
        // Eager-load roles and permissions once so the groupBy below is in-memory only.
        $user->loadMissing('roles');

        return [
            'user' => [
                'id'              => $user->id,
                'email'           => $user->email,
                'username'        => $user->username,
                'is_system_admin' => $user->is_system_admin,
                'is_active'       => $user->is_active,
                'mfa_enabled'     => $user->mfa_enabled,
            ],
            'modules'       => $user->getModuleCodes(),
            'module_access' => $user->activeModules()
                ->get()
                ->map(fn ($access) => [
                    'module_code' => $access->module_code,
                    'granted_at'  => $access->granted_at,
                ]),
            // Group roles by guard using the loaded collection — zero extra queries.
            'roles' => $user->roles
                ->groupBy('guard_name')
                ->map(fn ($roles) => $roles->pluck('name')),
            // Spatie caches getAllPermissions() internally.
            'permissions' => $user->getAllPermissions()
                ->groupBy('guard_name')
                ->map(fn ($perms) => $perms->pluck('name')),
        ];
    }

    /**
     * Grant module access to a user.
     */
    public function grantModuleAccess(User $user, string $moduleCode, User $grantedBy): UserModuleAccess
    {
        return UserModuleAccess::updateOrCreate(
            ['user_id' => $user->id, 'module_code' => $moduleCode],
            [
                'is_active'  => true,
                'granted_at' => now(),
                'granted_by' => $grantedBy->id,
            ]
        );
    }

    /**
     * Revoke module access from a user.
     */
    public function revokeModuleAccess(User $user, string $moduleCode): bool
    {
        return (bool) UserModuleAccess::where('user_id', $user->id)
            ->where('module_code', $moduleCode)
            ->update(['is_active' => false]);
    }
}
