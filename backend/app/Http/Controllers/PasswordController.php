<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    use ApiResponse;

    /**
     * Change user's password
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            // Build password validation rules
            $passwordRules = ['required', 'string', 'confirmed'];

            // Add strength requirements based on config
            $passwordRule = Password::min(config('password.strength.min_length', 8));

            if (config('password.strength.require_uppercase', true)) {
                $passwordRule->mixedCase();
            }

            if (config('password.strength.require_numbers', true)) {
                $passwordRule->numbers();
            }

            if (config('password.strength.require_special', true)) {
                $passwordRule->symbols();
            }

            $passwordRules[] = $passwordRule;

            $validated = $request->validate([
                'current_password' => 'required_unless:force_change,true|string',
                'password' => $passwordRules,
                'force_change' => 'boolean',
            ]);

            DB::beginTransaction();

            // Verify current password unless it's a forced password change
            $isForceChange = $validated['force_change'] ?? false;

            if (!$isForceChange) {
                if (!Hash::check($validated['current_password'], $user->password)) {
                    return $this->error('Current password is incorrect', 422);
                }
            } else {
                // Only allow force change if user actually has force_password_change flag
                if (!$user->force_password_change) {
                    return $this->error('Password change is not required', 422);
                }
            }

            // Check password history
            if ($user->hasUsedPasswordRecently($validated['password'])) {
                $historyCount = config('password.history.count', 5);
                return $this->error(
                    "Password cannot be one of your last {$historyCount} passwords",
                    422
                );
            }

            // Update password with expiration
            $user->setPasswordWithExpiration($validated['password']);

            DB::commit();

            Log::info('User changed password', [
                'user_id' => $user->id,
                'forced' => $isForceChange,
            ]);

            return $this->success(
                [
                    'password_changed_at' => $user->password_changed_at,
                    'password_expires_at' => $user->password_expires_at,
                ],
                'Password changed successfully'
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error changing password: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('Failed to change password. Please try again.', 500);
        }
    }

    /**
     * Force password change for a user (admin only)
     */
    public function forcePasswordChange(Request $request, string $userId): JsonResponse
    {
        try {
            // This endpoint should be protected by system admin guard
            $validated = $request->validate([
                'force' => 'required|boolean',
            ]);

            $user = User::findOrFail($userId);

            DB::beginTransaction();

            $user->update([
                'force_password_change' => $validated['force'],
            ]);

            DB::commit();

            Log::info('Admin forced password change', [
                'admin_id' => auth()->id(),
                'user_id' => $userId,
                'forced' => $validated['force'],
            ]);

            return $this->success(
                $user,
                $validated['force']
                    ? 'User will be required to change password on next login'
                    : 'Password change requirement removed'
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error forcing password change: ' . $e->getMessage(), [
                'admin_id' => auth()->id(),
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('Failed to update password requirement. Please try again.', 500);
        }
    }

    /**
     * Reset user password (admin only) - generates temporary password
     */
    public function resetPassword(Request $request, string $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);

            DB::beginTransaction();

            // Generate a temporary password that meets all requirements
            $tempPassword = $this->generateTemporaryPassword();

            // Set the new password with expiration and force change
            $user->setPasswordWithExpiration($tempPassword);
            $user->update([
                'force_password_change' => true,
            ]);

            DB::commit();

            Log::info('Admin reset user password', [
                'admin_id' => auth()->id(),
                'user_id' => $userId,
            ]);

            return $this->success(
                [
                    'temporary_password' => $tempPassword,
                    'user' => $user,
                ],
                'Password reset successfully. User will be required to change password on next login.'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error resetting password: ' . $e->getMessage(), [
                'admin_id' => auth()->id(),
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('Failed to reset password. Please try again.', 500);
        }
    }

    /**
     * Generate a temporary password that meets all strength requirements
     */
    private function generateTemporaryPassword(): string
    {
        $minLength = config('password.strength.min_length', 8);
        $requireUppercase = config('password.strength.require_uppercase', true);
        $requireLowercase = config('password.strength.require_lowercase', true);
        $requireNumbers = config('password.strength.require_numbers', true);
        $requireSpecial = config('password.strength.require_special', true);

        $password = '';

        // Add required character types
        if ($requireUppercase) {
            $password .= Str::random(1, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ');
        }
        if ($requireLowercase) {
            $password .= Str::random(1, 'abcdefghijklmnopqrstuvwxyz');
        }
        if ($requireNumbers) {
            $password .= Str::random(1, '0123456789');
        }
        if ($requireSpecial) {
            $password .= Str::random(1, '!@#$%^&*');
        }

        // Fill the rest with random characters
        $remainingLength = $minLength - strlen($password);
        if ($remainingLength > 0) {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            $password .= substr(str_shuffle(str_repeat($chars, $remainingLength)), 0, $remainingLength);
        }

        // Shuffle the password to randomize character positions
        return str_shuffle($password);
    }
}
