<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Handle login request
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $result = $this->authService->login($request->only('email', 'password'));

            return response()->json([
                'message' => 'Login successful',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Login failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Handle logout request
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current authenticated user's context
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $context = $this->authService->getUserContext($request->user());

        return response()->json([
            'data' => $context,
        ]);
    }

    /**
     * Refresh user token (invalidate old, create new)
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Delete current token
        $request->user()->currentAccessToken()->delete();

        // Create new token
        $moduleCodes = $user->getModuleCodes();
        $token = $user->createToken('auth-token', $moduleCodes)->plainTextToken;

        return response()->json([
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $token,
            ],
        ]);
    }
}
