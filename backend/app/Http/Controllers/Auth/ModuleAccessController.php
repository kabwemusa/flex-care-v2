<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleAccessController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Grant module access to a user
     *
     * @param  Request  $request
     * @param  string  $userId
     * @return JsonResponse
     */
    public function grant(Request $request, string $userId): JsonResponse
    {
        $request->validate([
            'module_code' => 'required|string|in:medical,life,motor,travel,admin',
        ]);

        $user = User::findOrFail($userId);
        $grantedBy = $request->user();

        $access = $this->authService->grantModuleAccess(
            $user,
            $request->module_code,
            $grantedBy
        );

        return response()->json([
            'message' => 'Module access granted successfully',
            'data' => $access,
        ]);
    }

    /**
     * Revoke module access from a user
     *
     * @param  Request  $request
     * @param  string  $userId
     * @return JsonResponse
     */
    public function revoke(Request $request, string $userId): JsonResponse
    {
        $request->validate([
            'module_code' => 'required|string|in:medical,life,motor,travel,admin',
        ]);

        $user = User::findOrFail($userId);

        $this->authService->revokeModuleAccess($user, $request->module_code);

        return response()->json([
            'message' => 'Module access revoked successfully',
        ]);
    }

    /**
     * Get all module access for a user
     *
     * @param  string  $userId
     * @return JsonResponse
     */
    public function index(string $userId): JsonResponse
    {
        $user = User::with('moduleAccess')->findOrFail($userId);

        return response()->json([
            'data' => $user->moduleAccess,
        ]);
    }
}
