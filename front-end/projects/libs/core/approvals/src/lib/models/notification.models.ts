// Notification Models

export interface Notification {
  id: string;
  type: string;
  data: NotificationData;
  read_at: string | null;
  created_at: string;
}

export interface NotificationData {
  title: string;
  message: string;
  type: NotificationType;
  request_id?: string;
  workflow_id?: string;
  workflow_name?: string;
  entity_type?: string;
  entity_id?: string;
  entity_reference?: string;
  status?: string;
  current_step?: string;
  action_url?: string;
  [key: string]: unknown;
}

export type NotificationType =
  | 'approval_required'
  | 'approval_completed'
  | 'approval_rejected'
  | 'approval_returned'
  | 'approval_progress'
  | 'general';

export interface NotificationResponse {
  notifications: Notification[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface UnreadCountResponse {
  count: number;
}
