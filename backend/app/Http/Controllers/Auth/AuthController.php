<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Authenticate user and return a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        // ValidationException is caught by the global handler → 422 with errors array.
        $result = $this->authService->login($request->only('email', 'password'));

        return $this->success($result, 'Login successful');
    }

    /**
     * Revoke all tokens for the authenticated user (logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(null, 'Logged out successfully');
    }

    /**
     * Return the authenticated user's full context.
     */
    public function me(Request $request): JsonResponse
    {
        $context = $this->authService->getUserContext($request->user());

        return $this->success($context, 'User context retrieved');
    }

    /**
     * Rotate the current access token (delete old, issue new).
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->user()->currentAccessToken()->delete();

        $moduleCodes = $user->getModuleCodes();
        $token       = $user->createToken('auth-token', $moduleCodes)->plainTextToken;

        return $this->success(['token' => $token], 'Token refreshed successfully');
    }
}
