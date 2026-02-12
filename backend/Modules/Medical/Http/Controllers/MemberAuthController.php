<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Medical\Services\MemberAuthService;
use Modules\Medical\Models\MemberToken;
use App\Traits\ApiResponse;

class MemberAuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MemberAuthService $authService
    ) {}

    /**
     * Request OTP for passwordless login.
     * POST /v1/medical/member/auth/request-otp
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $result = $this->authService->requestLoginOtp(
            $request->email,
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['success']) {
            return $this->error($result['error'], 429);
        }

        return $this->success([
            'expires_in' => $result['expires_in'] ?? 600,
        ], $result['message']);
    }

    /**
     * Verify OTP and login.
     * POST /v1/medical/member/auth/verify-otp
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'code' => 'required|string|size:6',
        ]);

        $result = $this->authService->verifyOtpAndLogin(
            $request->email,
            $request->code,
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['success']) {
            return $this->error($result['error'], 401);
        }

        return $this->success([
            'token' => $result['token'],
            'expires_at' => $result['expires_at'],
            'member' => $result['member'],
        ], 'Login successful');
    }

    /**
     * Login with password.
     * POST /v1/medical/member/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
        ]);

        $result = $this->authService->loginWithPassword(
            $request->email,
            $request->password,
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['success']) {
            return $this->error($result['error'], 401);
        }

        return $this->success([
            'token' => $result['token'],
            'expires_at' => $result['expires_at'],
            'member' => $result['member'],
        ], 'Login successful');
    }

    /**
     * Get current member context.
     * GET /v1/medical/member/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $member = $request->attributes->get('member');

        if (!$member) {
            return $this->error('Not authenticated', 401);
        }

        return $this->success(
            $this->authService->getMemberContext($member),
            'Member context retrieved'
        );
    }

    /**
     * Set password (for first time or update).
     * POST /v1/medical/member/auth/set-password
     */
    public function setPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $member = $request->attributes->get('member');

        if (!$member) {
            return $this->error('Not authenticated', 401);
        }

        $result = $this->authService->setPassword($member, $request->password);

        return $this->success(null, $result['message']);
    }

    /**
     * Request password reset.
     * POST /v1/medical/member/auth/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $result = $this->authService->requestPasswordReset(
            $request->email,
            $request->ip()
        );

        if (!$result['success']) {
            return $this->error($result['error'], 429);
        }

        return $this->success(null, $result['message']);
    }

    /**
     * Reset password with OTP.
     * POST /v1/medical/member/auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $result = $this->authService->resetPassword(
            $request->email,
            $request->code,
            $request->password
        );

        if (!$result['success']) {
            return $this->error($result['error'], 400);
        }

        return $this->success(null, $result['message']);
    }

    /**
     * Logout.
     * POST /v1/medical/member/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $this->extractToken($request);

        if ($token) {
            $this->authService->logout($token);
        }

        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Logout from all devices.
     * POST /v1/medical/member/auth/logout-all
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $member = $request->attributes->get('member');

        if (!$member) {
            return $this->error('Not authenticated', 401);
        }

        $result = $this->authService->logoutAll($member);

        return $this->success(null, $result['message']);
    }

    /**
     * Extract bearer token from request.
     */
    protected function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}
