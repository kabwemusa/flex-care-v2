<?php

namespace App\Http\Controllers;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalStep;
use App\Services\ApprovalService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ApprovalWorkflowController extends Controller
{
    use ApiResponse;

    protected ApprovalService $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * Display a listing of approval workflows
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ApprovalWorkflow::with(['creator', 'steps.group'])
                ->withCount('steps');

            // Filter by entity type
            if ($request->has('entity_type')) {
                $query->forEntity($request->get('entity_type'));
            }

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

            $workflows = $query->orderBy('name')->get();

            return $this->success($workflows, 'Approval workflows retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching approval workflows: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch approval workflows', 500);
        }
    }

    /**
     * Display the specified approval workflow
     */
    public function show(string $id): JsonResponse
    {
        try {
            $workflow = ApprovalWorkflow::with(['creator', 'updater', 'steps.group.members'])
                ->withCount(['steps', 'requests'])
                ->findOrFail($id);

            return $this->success($workflow, 'Approval workflow retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Approval workflow not found', 404);
        } catch (\Exception $e) {
            Log::error('Error fetching approval workflow: ' . $e->getMessage(), [
                'workflow_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch approval workflow', 500);
        }
    }

    /**
     * Store a newly created approval workflow
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:100|unique:approval_workflows,code',
                'entity_type' => 'required|string|max:100',
                'description' => 'nullable|string|max:1000',
                'is_active' => 'nullable|boolean',
                'steps' => 'nullable|array',
                'steps.*.name' => 'required_with:steps|string|max:255',
                'steps.*.group_id' => 'required_with:steps|uuid|exists:approval_groups,id',
                'steps.*.description' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $workflow = $this->approvalService->createWorkflow($validated, $request->user());

            // Add steps if provided
            if (!empty($validated['steps'])) {
                foreach ($validated['steps'] as $index => $stepData) {
                    $stepData['step_order'] = $index + 1;
                    $this->approvalService->addWorkflowStep($workflow, $stepData);
                }
            }

            DB::commit();

            return $this->success(
                $workflow->fresh(['steps.group']),
                'Approval workflow created successfully',
                201
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating approval workflow: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to create approval workflow'.$e->getMessage(), 500);
        }
    }

    /**
     * Update the specified approval workflow
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $workflow = ApprovalWorkflow::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|required|string|max:100|unique:approval_workflows,code,' . $id,
                'entity_type' => 'sometimes|required|string|max:100',
                'description' => 'nullable|string|max:1000',
                'is_active' => 'nullable|boolean',
            ]);

            DB::beginTransaction();

            $workflow = $this->approvalService->updateWorkflow($workflow, $validated, $request->user());

            DB::commit();

            return $this->success(
                $workflow->fresh(['steps.group']),
                'Approval workflow updated successfully'
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->error('Approval workflow not found', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating approval workflow: ' . $e->getMessage(), [
                'workflow_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to update approval workflow', 500);
        }
    }

    /**
     * Remove the specified approval workflow
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $workflow = ApprovalWorkflow::findOrFail($id);

            // Check if workflow has any pending requests
            if ($workflow->requests()->pending()->exists()) {
                return $this->error('Cannot delete workflow with pending approval requests', 422);
            }

            DB::beginTransaction();

            $workflow->delete();

            DB::commit();

            return $this->success(null, 'Approval workflow deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->error('Approval workflow not found', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting approval workflow: ' . $e->getMessage(), [
                'workflow_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to delete approval workflow', 500);
        }
    }

    /**
     * Add a step to a workflow
     */
    public function addStep(Request $request, string $workflowId): JsonResponse
    {
        try {
            $workflow = ApprovalWorkflow::findOrFail($workflowId);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'group_id' => 'required|uuid|exists:approval_groups,id',
                'description' => 'nullable|string|max:1000',
                'step_order' => 'nullable|integer|min:1',
            ]);

            $step = $this->approvalService->addWorkflowStep($workflow, $validated);

            return $this->success(
                $step->fresh(['group']),
                'Step added successfully',
                201
            );
        } catch (ValidationException $e) {
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Approval workflow not found', 404);
        } catch (\Exception $e) {
            Log::error('Error adding step to workflow: ' . $e->getMessage(), [
                'workflow_id' => $workflowId,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to add step', 500);
        }
    }

    /**
     * Update a workflow step
     */
    public function updateStep(Request $request, string $workflowId, string $stepId): JsonResponse
    {
        try {
            $step = ApprovalStep::where('workflow_id', $workflowId)
                ->where('id', $stepId)
                ->firstOrFail();

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'group_id' => 'sometimes|required|uuid|exists:approval_groups,id',
                'description' => 'nullable|string|max:1000',
                'is_active' => 'nullable|boolean',
            ]);

            $step = $this->approvalService->updateWorkflowStep($step, $validated);

            return $this->success(
                $step->fresh(['group']),
                'Step updated successfully'
            );
        } catch (ValidationException $e) {
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Step not found', 404);
        } catch (\Exception $e) {
            Log::error('Error updating workflow step: ' . $e->getMessage(), [
                'workflow_id' => $workflowId,
                'step_id' => $stepId,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to update step', 500);
        }
    }

    /**
     * Delete a workflow step
     */
    public function deleteStep(string $workflowId, string $stepId): JsonResponse
    {
        try {
            $step = ApprovalStep::where('workflow_id', $workflowId)
                ->where('id', $stepId)
                ->firstOrFail();

            $this->approvalService->deleteWorkflowStep($step);

            return $this->success(null, 'Step deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Step not found', 404);
        } catch (\Exception $e) {
            Log::error('Error deleting workflow step: ' . $e->getMessage(), [
                'workflow_id' => $workflowId,
                'step_id' => $stepId,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Reorder workflow steps
     */
    public function reorderSteps(Request $request, string $workflowId): JsonResponse
    {
        try {
            $workflow = ApprovalWorkflow::findOrFail($workflowId);

            $validated = $request->validate([
                'step_ids' => 'required|array|min:1',
                'step_ids.*' => 'uuid|exists:approval_steps,id',
            ]);

            $this->approvalService->reorderWorkflowSteps($workflow, $validated['step_ids']);

            return $this->success(
                $workflow->fresh(['steps.group']),
                'Steps reordered successfully'
            );
        } catch (ValidationException $e) {
            return $this->error('Validation failed', 422, $e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Approval workflow not found', 404);
        } catch (\Exception $e) {
            Log::error('Error reordering workflow steps: ' . $e->getMessage(), [
                'workflow_id' => $workflowId,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to reorder steps', 500);
        }
    }

    /**
     * Get available entity types
     */
    public function entityTypes(): JsonResponse
    {
        try {
            $types = ApprovalWorkflow::getEntityTypes();

            return $this->success($types, 'Entity types retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching entity types: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch entity types', 500);
        }
    }
}
