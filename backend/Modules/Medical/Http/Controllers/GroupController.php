<?php

namespace Modules\Medical\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Medical\Models\Group;
use Modules\Medical\Models\GroupContact;
use Modules\Medical\Http\Requests\GroupRequest;
use Modules\Medical\Http\Requests\GroupContactRequest;
use Modules\Medical\Http\Resources\GroupResource;
use Modules\Medical\Http\Resources\GroupContactResource;
use App\Traits\ApiResponse;
use Throwable;
use Exception;

class GroupController extends Controller
{
    use ApiResponse;

    /**
     * List corporate groups with filtering.
     * GET /v1/medical/groups
     */
    public function index(): JsonResponse
    {
        try {
            $query = Group::query()
                ->with(['primaryContact']);
            
            // Search
            if ($search = request('search')) {
                $query->search($search);
            }

            // Filters
            if ($status = request('status')) {
                $query->where('status', $status);
            }

            if ($industry = request('industry')) {
                $query->byIndustry($industry);
            }

            if ($size = request('company_size')) {
                $query->bySize($size);
            }

            // Sorting
            $sortBy = request('sort_by', 'created_at');
            $sortOrder = request('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $groups = $query->paginate(request('per_page', 20));

            return $this->success(
                GroupResource::collection($groups),
                'Corporate groups retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve groups: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create corporate group.
     * POST /v1/medical/groups
     */
    public function store(GroupRequest $request): JsonResponse
    {
        try {
            $group = DB::transaction(function () use ($request) {
                $group = Group::create($request->validated());

                // Create primary contact if provided
                if ($contactData = $request->input('primary_contact')) {
                    $contactData['contact_type'] = 'primary';
                    $contactData['is_primary'] = true;
                    $group->contacts()->create($contactData);
                }

                return $group;
            });

            $group->load(['primaryContact', 'contacts']);

            return $this->success(
                new GroupResource($group),
                'Corporate group created',
                201
            );
        } catch (Throwable $e) {
            return $this->error('Failed to create group: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Show corporate group details.
     * GET /v1/medical/groups/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $group = Group::with([
                'contacts',
                'policies' => fn($q) => $q->latest()->limit(10),
                'activePolicies',
            ])
            ->withCount(['policies', 'contacts'])
            ->findOrFail($id);

            return $this->success(
                new GroupResource($group),
                'Corporate group retrieved'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Group not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve group details', 500);
        }
    }

    /**
     * Update corporate group.
     * PUT /v1/medical/groups/{id}
     */
    public function update(GroupRequest $request, string $id): JsonResponse
    {
        try {
            $group = DB::transaction(function () use ($request, $id) {
                $group = Group::findOrFail($id);
                $group->update($request->validated());
                return $group->fresh(['primaryContact', 'contacts']);
            });

            return $this->success(
                new GroupResource($group),
                'Corporate group updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Group not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update group: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete corporate group.
     * DELETE /v1/medical/groups/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id) {
                $group = Group::findOrFail($id);

                // Prevent deletion if has active policies
                if ($group->activePolicies()->exists()) {
                    throw new Exception('Cannot delete group with active policies', 422);
                }

                $group->delete();
            });

            return $this->success(null, 'Corporate group deleted');
        } catch (ModelNotFoundException $e) {
            return $this->error('Group not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Activate a prospect group.
     * POST /v1/medical/groups/{id}/activate
     */
    public function activate(string $id): JsonResponse
    {
        try {
            $group = DB::transaction(function () use ($id) {
                $group = Group::findOrFail($id);
                
                if (!$group->is_prospect) {
                    throw new Exception('Only prospects can be activated', 422);
                }

                $group->activate();
                return $group->fresh();
            });

            return $this->success(
                new GroupResource($group),
                'Corporate group activated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Group not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Suspend a group.
     * POST /v1/medical/groups/{id}/suspend
     */
    public function suspend(string $id): JsonResponse
    {
        try {
            $group = DB::transaction(function () use ($id) {
                $group = Group::findOrFail($id);
                $group->suspend(request('reason'));
                return $group->fresh();
            });

            return $this->success(
                new GroupResource($group),
                'Corporate group suspended'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Group not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to suspend group', 500);
        }
    }

    /**
     * Get groups for dropdown.
     * GET /v1/medical/groups/dropdown
     */
    public function dropdown(): JsonResponse
    {
        try {
            $query = Group::query()
                ->select(['id', 'code', 'name', 'trading_name', 'status']);

            if (request('active_only', true)) {
                $query->active();
            }

            $groups = $query->orderBy('name')->get();

            return $this->success($groups, 'Groups retrieved');
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve groups list', 500);
        }
    }

    // =========================================================================
    // CONTACTS
    // =========================================================================

    /**
     * List contacts for a group.
     * GET /v1/medical/groups/{groupId}/contacts
     */
    public function contacts(string $groupId): JsonResponse
    {
        try {
            $contacts = GroupContact::where('group_id', $groupId)
                ->orderBy('is_primary', 'desc')
                ->orderBy('contact_type')
                ->get();

            return $this->success(
                GroupContactResource::collection($contacts),
                'Contacts retrieved'
            );
        } catch (Throwable $e) {
            return $this->error('Failed to retrieve contacts', 500);
        }
    }

    /**
     * Add contact to group.
     * POST /v1/medical/groups/{groupId}/contacts
     */
    public function addContact(GroupContactRequest $request, string $groupId): JsonResponse
    {
        try {
            $contact = DB::transaction(function () use ($request, $groupId) {
                // Verify group exists first
                Group::findOrFail($groupId);
                
                $data = $request->validated();
                $data['group_id'] = $groupId;

                $contact = GroupContact::create($data);

                // Handle primary contact toggle logic
                if ($contact->is_primary) {
                    $contact->makePrimary();
                }

                return $contact;
            });

            return $this->success(
                new GroupContactResource($contact),
                'Contact added',
                201
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Group not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to add contact', 500);
        }
    }

    /**
     * Update contact.
     * PUT /v1/medical/groups/{groupId}/contacts/{contactId}
     */
    public function updateContact(GroupContactRequest $request, string $groupId, string $contactId): JsonResponse
    {
        try {
            $contact = DB::transaction(function () use ($request, $groupId, $contactId) {
                $contact = GroupContact::where('group_id', $groupId)
                    ->findOrFail($contactId);

                $contact->update($request->validated());

                if ($request->input('is_primary')) {
                    $contact->makePrimary();
                }

                return $contact->fresh();
            });

            return $this->success(
                new GroupContactResource($contact),
                'Contact updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Contact not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update contact', 500);
        }
    }

    /**
     * Remove contact.
     * DELETE /v1/medical/groups/{groupId}/contacts/{contactId}
     */
    public function removeContact(string $groupId, string $contactId): JsonResponse
    {
        try {
            DB::transaction(function () use ($groupId, $contactId) {
                $contact = GroupContact::where('group_id', $groupId)
                    ->findOrFail($contactId);

                if ($contact->is_primary) {
                    throw new Exception('Cannot delete primary contact. Set another contact as primary first.', 422);
                }

                $contact->delete();
            });

            return $this->success(null, 'Contact removed');
        } catch (ModelNotFoundException $e) {
            return $this->error('Contact not found', 404);
        } catch (Throwable $e) {
            $code = $e->getCode() === 422 ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Set contact as primary.
     * POST /v1/medical/groups/{groupId}/contacts/{contactId}/primary
     */
    public function setPrimaryContact(string $groupId, string $contactId): JsonResponse
    {
        try {
            $contact = DB::transaction(function () use ($groupId, $contactId) {
                $contact = GroupContact::where('group_id', $groupId)
                    ->findOrFail($contactId);

                $contact->makePrimary();
                return $contact->fresh();
            });

            return $this->success(
                new GroupContactResource($contact),
                'Primary contact updated'
            );
        } catch (ModelNotFoundException $e) {
            return $this->error('Contact not found', 404);
        } catch (Throwable $e) {
            return $this->error('Failed to update primary contact', 500);
        }
    }
}