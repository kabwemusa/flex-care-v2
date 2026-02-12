<?php

namespace Modules\Medical\Services;

use App\Mail\MemberOtpMail;
use App\Mail\MemberPasswordResetMail;
use App\Mail\MemberWelcomeMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\MemberOtp;
use Modules\Medical\Models\MemberToken;
use Modules\Medical\Constants\MedicalConstants;

class MemberAuthService
{
    /**
     * Request OTP for login.
     */
    public function requestLoginOtp(string $email, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $email = strtolower(trim($email));

        // Check if member exists with this email
        $member = Member::where('email', $email)
            ->where('has_portal_access', true)
            ->where('status', MedicalConstants::MEMBER_STATUS_ACTIVE)
            ->first();

        if (!$member) {
            // Don't reveal if email exists or not (security)
            return [
                'success' => true,
                'message' => 'If an account exists with this email, you will receive a verification code.',
            ];
        }

        // Check rate limiting
        if (MemberOtp::isRateLimited($email, MemberOtp::PURPOSE_LOGIN)) {
            return [
                'success' => false,
                'error' => 'Please wait a moment before requesting another code.',
            ];
        }

        // Generate OTP
        $otp = MemberOtp::generate(
            $email,
            MemberOtp::PURPOSE_LOGIN,
            $member->phone,
            $ipAddress,
            $userAgent
        );

        // Send OTP via email
        $this->sendOtpEmail($email, $otp->code, $member->first_name);

        return [
            'success' => true,
            'message' => 'Verification code sent to your email.',
            'expires_in' => MemberOtp::EXPIRY_MINUTES * 60, // seconds
        ];
    }

    /**
     * Verify OTP and login.
     */
    public function verifyOtpAndLogin(
        string $email,
        string $code,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $email = strtolower(trim($email));

        // Verify OTP
        $result = MemberOtp::verify($email, $code, MemberOtp::PURPOSE_LOGIN);

        if (!$result['success']) {
            return $result;
        }

        // Find member
        $member = Member::where('email', $email)
            ->where('has_portal_access', true)
            ->where('status', MedicalConstants::MEMBER_STATUS_ACTIVE)
            ->with(['policy.plan', 'policy.scheme'])
            ->first();

        if (!$member) {
            return [
                'success' => false,
                'error' => 'Account not found or inactive.',
            ];
        }

        // Update last login
        $member->portal_last_login_at = now();
        $member->portal_last_login_ip = $ipAddress;
        $member->save();

        // Generate session token
        $tokenData = MemberToken::generate($member, 'Web Browser', $ipAddress, $userAgent);

        return [
            'success' => true,
            'token' => $tokenData['token'],
            'expires_at' => $tokenData['expires_at']->toIso8601String(),
            'member' => $this->getMemberContext($member),
        ];
    }

    /**
     * Login with password (for members who set a password).
     */
    public function loginWithPassword(
        string $email,
        string $password,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $email = strtolower(trim($email));

        $member = Member::where('email', $email)
            ->where('has_portal_access', true)
            ->where('status', MedicalConstants::MEMBER_STATUS_ACTIVE)
            ->whereNotNull('portal_password')
            ->with(['policy.plan', 'policy.scheme'])
            ->first();

        if (!$member || !Hash::check($password, $member->portal_password)) {
            return [
                'success' => false,
                'error' => 'Invalid email or password.',
            ];
        }

        // Update last login
        $member->portal_last_login_at = now();
        $member->portal_last_login_ip = $ipAddress;
        $member->save();

        // Generate session token
        $tokenData = MemberToken::generate($member, 'Web Browser', $ipAddress, $userAgent);

        return [
            'success' => true,
            'token' => $tokenData['token'],
            'expires_at' => $tokenData['expires_at']->toIso8601String(),
            'member' => $this->getMemberContext($member),
        ];
    }

    /**
     * Set or update password for member.
     */
    public function setPassword(Member $member, string $password): array
    {
        $member->portal_password = Hash::make($password);
        $member->portal_password_set_at = now();
        $member->save();

        return [
            'success' => true,
            'message' => 'Password set successfully.',
        ];
    }

    /**
     * Request password reset OTP.
     */
    public function requestPasswordReset(string $email, ?string $ipAddress = null): array
    {
        $email = strtolower(trim($email));

        $member = Member::where('email', $email)
            ->where('has_portal_access', true)
            ->first();

        if (!$member) {
            // Don't reveal if email exists
            return [
                'success' => true,
                'message' => 'If an account exists, you will receive password reset instructions.',
            ];
        }

        if (MemberOtp::isRateLimited($email, MemberOtp::PURPOSE_PASSWORD_RESET)) {
            return [
                'success' => false,
                'error' => 'Please wait before requesting another reset.',
            ];
        }

        $otp = MemberOtp::generate($email, MemberOtp::PURPOSE_PASSWORD_RESET, null, $ipAddress);
        $this->sendPasswordResetEmail($email, $otp->code, $member->first_name);

        return [
            'success' => true,
            'message' => 'Password reset instructions sent to your email.',
        ];
    }

    /**
     * Reset password with OTP.
     */
    public function resetPassword(string $email, string $code, string $newPassword): array
    {
        $result = MemberOtp::verify($email, $code, MemberOtp::PURPOSE_PASSWORD_RESET);

        if (!$result['success']) {
            return $result;
        }

        $member = Member::where('email', strtolower(trim($email)))->first();

        if (!$member) {
            return [
                'success' => false,
                'error' => 'Account not found.',
            ];
        }

        $member->portal_password = Hash::make($newPassword);
        $member->portal_password_set_at = now();
        $member->save();

        // Revoke all existing tokens (force re-login)
        MemberToken::revokeAllForMember($member->id);

        return [
            'success' => true,
            'message' => 'Password reset successfully. Please login with your new password.',
        ];
    }

    /**
     * Logout (revoke token).
     */
    public function logout(string $token): array
    {
        $tokenModel = MemberToken::findByToken($token);

        if ($tokenModel) {
            $tokenModel->delete();
        }

        return [
            'success' => true,
            'message' => 'Logged out successfully.',
        ];
    }

    /**
     * Logout from all devices.
     */
    public function logoutAll(Member $member): array
    {
        MemberToken::revokeAllForMember($member->id);

        return [
            'success' => true,
            'message' => 'Logged out from all devices.',
        ];
    }

    /**
     * Get member context for frontend.
     */
    public function getMemberContext(Member $member): array
    {
        $member->loadMissing(['policy.plan', 'policy.scheme', 'dependents']);

        return [
            'id' => $member->id,
            'member_number' => $member->member_number,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'full_name' => $member->full_name,
            'email' => $member->email,
            'phone' => $member->phone,
            'member_type' => $member->member_type,
            'has_password' => $member->portal_password !== null,
            'policy' => $member->policy ? [
                'id' => $member->policy->id,
                'policy_number' => $member->policy->policy_number,
                'status' => $member->policy->status,
                'plan_name' => $member->policy->plan?->name,
                'scheme_name' => $member->policy->scheme?->name,
                'inception_date' => $member->policy->inception_date?->toDateString(),
                'expiry_date' => $member->policy->expiry_date?->toDateString(),
            ] : null,
            'dependents_count' => $member->dependents->count(),
            'card_status' => $member->card_status,
            'card_number' => $member->card_number,
        ];
    }

    /**
     * Enable portal access for a member.
     */
    public function enablePortalAccess(Member $member): array
    {
        if (!$member->email) {
            return [
                'success' => false,
                'error' => 'Member does not have an email address.',
            ];
        }

        $member->has_portal_access = true;
        $member->save();

        // Send welcome email with login instructions
        $this->sendWelcomeEmail($member);

        return [
            'success' => true,
            'message' => 'Portal access enabled. Welcome email sent.',
        ];
    }

    /**
     * Send OTP via email.
     */
    protected function sendOtpEmail(string $email, string $code, ?string $name = null): void
    {
        try {
            Mail::to($email)->send(new MemberOtpMail($code, $name, 'login'));
            Log::info("OTP email sent to {$email}");
        } catch (\Exception $e) {
            Log::error("Failed to send OTP email to {$email}: {$e->getMessage()}");
        }
    }

    /**
     * Send password reset email.
     */
    protected function sendPasswordResetEmail(string $email, string $code, ?string $name = null): void
    {
        try {
            Mail::to($email)->send(new MemberPasswordResetMail($code, $name));
            Log::info("Password reset email sent to {$email}");
        } catch (\Exception $e) {
            Log::error("Failed to send password reset email to {$email}: {$e->getMessage()}");
        }
    }

    /**
     * Send welcome email when portal access is enabled.
     */
    protected function sendWelcomeEmail(Member $member): void
    {
        try {
            $loginUrl = config('app.frontend_url', config('app.url')) . '/login';

            Mail::to($member->email)->send(new MemberWelcomeMail(
                $member->full_name,
                $member->member_number,
                $member->email,
                $loginUrl
            ));
            Log::info("Welcome email sent to {$member->email}");
        } catch (\Exception $e) {
            Log::error("Failed to send welcome email to {$member->email}: {$e->getMessage()}");
        }
    }
}
