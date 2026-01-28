<?php

namespace App\Http\Controllers;

use App\Models\ApprovalGroup;
use App\Models\User;
use App\Services\ApprovalService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ApprovalGroupController extends Controller
{
    use ApiResponse;

    protected ApprovalService $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * Display a listing of approval groups
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ApprovalGroup::with(['creator', 'memberships.user'])
                ->withCount('memberships as members_count');

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $groups = $query->orderBy('name')->get();

            return $this->success($groups, 'Approval groups retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching approval groups: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch approval groups', 500);
        }
    }

    /**
     * Display the specified approval group
     */
    public function show(string $id): JsonResponse
    {
        try {
            $group = ApprovalGroup::with(['creator', 'updater', 'memberships.user', 'steps.workflow'])
                ->withCount('memberships as members_count')
                ->findOrFail($id);

            return $this->success($group, 'Approval group retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Approval group not found', 404);
        } catch (\Exception $e) {
            Log::error('Error fetching approval group: ' . $e->getMessage(), [
                'group_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch approval group', 500);
        }
    }

    /**
     * Store a newly created approval group
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:100|unique:approval_groups,code',
                'description' => 'nullable|string|max:1000',
                'is_active' => 'nullable|boolean',
                'member_ids' => 'nullable|array',
                'member_ids.*' => 'uuid|exists:users,id',
            ]);

            DB::beginTransaction();

            $group = $this->approvalService->createGroup($validated, $request->user());

            // Add members if provided
            if (!empty($validated['member_ids'])) {
                $this->approvalService->addGroupMembers($group, $validated['member_ids'], $request->user());
            }

            DB::commit();

            return $this->success(
                $group->fresh(['memberships.user']),
                'Approval group created successfully',
                201
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating approval group: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to create approval group', 500);
        }
    }

    /**
     * Update the specified approval group
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $group = ApprovalGroup::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|required|string|max:100|unique:approval_groups,code,' . $id,
                'description' => 'nullable|string|max:1000',
                'is_active' => 'nullable|boolean',
            ]);

            DB::beginTransaction();

            $group = $this->approvalService->updateGroup($group, $validated, $request->user());

            DB::commit();

            return $this->success(
                $group->fresh(['memberships.user']),
                'Approval group updated successfully'
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->error('Approval group not found', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating approval group: ' . $e->getMessage(), [
                'group_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to update approval group', 500);
        }
    }

    /**
     * Remove the specified approval group
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $group = ApprovalGroup::findOrFail($id);

            // Check if group is used in any workflow steps (only from non-deleted workflows)
            if ($group->steps()->whereHas('workflow', function ($q) {
                $q->whereNull('deleted_at');
            })->exists()) {
                return $this->error('Cannot delete group that is used in workflow steps', 422);
            }

            DB::beginTransaction();

            $group->delete();

            DB::commit();

            return $this->success(null, 'Approval group deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->error('Approval group not found', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting approval group: ' . $e->getMessage(), [
                'group_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to delete approval group', 500);
        }
    }

    /**
     * Add members to a group
     */
    public function addMembers(Request $request, string $id): JsonResponse
    {
        try {
            $group = ApprovalGroup::findOrFail($id);

            $validated = $request->validate([
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'uuid|exists:users,id',
            ]);

            $this->approvalService->addGroupMembers($group, $validated['user_ids'], $request->user());

            return $this->success(
                $group->fresh(['memberships.user']),
                'Members added successfully'
            );
        } catch (ValidationException $e) {
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Approval group not found', 404);
        } catch (\Exception $e) {
            Log::error('Error adding members to group: ' . $e->getMessage(), [
                'group_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to add members', 500);
        }
    }

    /**
     * Remove a member from a group
     */
    public function removeMember(string $groupId, string $userId): JsonResponse
    {
        try {
            $group = ApprovalGroup::findOrFail($groupId);

            $removed = $this->approvalService->removeGroupMember($group, $userId);

            if (!$removed) {
                return $this->error('Member not found in group', 404);
            }

            return $this->success(
                $group->fresh(['memberships.user']),
                'Member removed successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Approval group not found', 404);
        } catch (\Exception $e) {
            Log::error('Error removing member from group: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to remove member', 500);
        }
    }

    /**
     * Get available users to add to a group
     */
    public function availableUsers(Request $request, string $id): JsonResponse
    {
        try {
            $group = ApprovalGroup::findOrFail($id);

            $existingMemberIds = $group->members()->pluck('users.id')->toArray();

            $users = User::active()
                ->whereNotIn('id', $existingMemberIds)
                ->when($request->has('search'), function ($q) use ($request) {
                    $search = $request->get('search');
                    $q->where(function ($query) use ($search) {
                        $query->where('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->select('id', 'username', 'email')
                ->orderBy('username')
                ->limit(50)
                ->get();

            return $this->success($users, 'Available users retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Approval group not found', 404);
        } catch (\Exception $e) {
            Log::error('Error fetching available users: ' . $e->getMessage(), [
                'group_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch available users', 500);
        }
    }
}
