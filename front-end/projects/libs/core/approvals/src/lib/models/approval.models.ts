// Approval System Models

export interface ApprovalGroup {
  id: string;
  name: string;
  code: string;
  description?: string;
  is_active: boolean;
  members_count?: number;
  members?: ApprovalGroupMember[];
  created_by?: string;
  updated_by?: string;
  creator?: UserRef;
  updater?: UserRef;
  created_at: string;
  updated_at: string;
}

export interface ApprovalGroupMember {
  id: string;
  group_id: string;
  user_id: string;
  user?: UserRef;
  added_by?: string;
  added_by_user?: UserRef;
  created_at: string;
}

export interface ApprovalWorkflow {
  id: string;
  name: string;
  code: string;
  entity_type: string;
  description?: string;
  is_active: boolean;
  steps_count?: number;
  steps?: ApprovalStep[];
  creator?: UserRef;
  updater?: UserRef;
  created_at: string;
  updated_at: string;
}

export interface ApprovalStep {
  id: string;
  workflow_id: string;
  group_id: string;
  step_order: number;
  name: string;
  description?: string;
  is_active: boolean;
  group?: ApprovalGroup;
  created_at: string;
  updated_at: string;
}

export interface ApprovalRequest {
  id: string;
  workflow_id: string;
  entity_type: string;
  entity_id: string;
  entity_reference?: string;
  current_step_id?: string;
  status: ApprovalStatus;
  status_label?: string;
  initiated_by: string;
  completed_at?: string;
  final_remarks?: string;
  workflow?: ApprovalWorkflow;
  current_step?: ApprovalStepRef;
  initiator?: UserRef;
  progress?: ApprovalProgress;
  histories?: ApprovalHistory[];
  created_at: string;
  updated_at?: string;
}

export interface ApprovalHistory {
  id: string;
  request_id: string;
  step_id: string;
  action: ApprovalAction;
  action_label: string;
  actioned_by: string;
  comments?: string;
  actioned_at: string;
  actioned_by_user?: UserRef;
  step?: ApprovalStepRef;
}

export interface ApprovalStepRef {
  id: string;
  name: string;
  order?: number;
  group?: {
    id: string;
    name: string;
  };
}

export interface ApprovalProgress {
  current: number;
  total: number;
  percentage: number;
}

export interface UserRef {
  id: string;
  name?: string;
  username?: string;
  email?: string;
}

export type ApprovalStatus = 'pending' | 'approved' | 'rejected' | 'returned' | 'cancelled';
export type ApprovalAction = 'approved' | 'rejected' | 'returned';

export interface PendingApproval {
  id: string;
  workflow: {
    id: string;
    name: string;
  };
  entity_type: string;
  entity_id: string;
  entity_reference: string;
  current_step: {
    id: string;
    name: string;
    group: string;
  };
  initiated_by: {
    id: string;
    name: string;
  };
  created_at: string;
  progress: ApprovalProgress;
}

export interface ApprovalHistoryItem {
  id: string;
  action: ApprovalAction;
  action_label: string;
  comments?: string;
  actioned_at: string;
  step: {
    id: string;
    name: string;
  };
  request: {
    id: string;
    entity_type: string;
    entity_id: string;
    workflow_name: string;
  };
}

// Request DTOs
export interface CreateApprovalGroupRequest {
  name: string;
  code: string;
  description?: string;
  is_active?: boolean;
  member_ids?: string[];
}

export interface UpdateApprovalGroupRequest {
  name?: string;
  code?: string;
  description?: string;
  is_active?: boolean;
}

export interface AddGroupMembersRequest {
  user_ids: string[];
}

export interface CreateApprovalWorkflowRequest {
  name: string;
  code: string;
  entity_type: string;
  description?: string;
  is_active?: boolean;
  steps?: CreateStepRequest[];
}

export interface UpdateApprovalWorkflowRequest {
  name?: string;
  code?: string;
  entity_type?: string;
  description?: string;
  is_active?: boolean;
}

export interface CreateStepRequest {
  name: string;
  group_id: string;
  description?: string;
  step_order?: number;
}

export interface UpdateStepRequest {
  name?: string;
  group_id?: string;
  description?: string;
  is_active?: boolean;
}

export interface ReorderStepsRequest {
  step_ids: string[];
}

export interface ApproveRequest {
  comments?: string;
}

export interface RejectRequest {
  reason: string;
}

export interface ReturnRequest {
  reason: string;
}

// Entity Types
export interface EntityType {
  code: string;
  label: string;
}

export const ENTITY_TYPES: EntityType[] = [
  { code: 'application', label: 'Application' },
  { code: 'claim', label: 'Claim' },
  { code: 'payment', label: 'Payment' },
  { code: 'invoice', label: 'Invoice' },
  { code: 'policy_activation', label: 'Policy Activation' },
  { code: 'scheme', label: 'Scheme' },
  { code: 'endorsement', label: 'Endorsement' },
  { code: 'refund', label: 'Refund' },
];
