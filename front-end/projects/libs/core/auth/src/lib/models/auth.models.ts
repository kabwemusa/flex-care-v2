/**
 * IAM (Identity and Access Management) Models
 * Global authentication models for all modules
 */

export interface User {
  id: string;
  email: string;
  username: string | null;
  is_system_admin: boolean;
  is_active: boolean;
  mfa_enabled: boolean;
  last_login_at?: string;
}

export interface ModuleAccess {
  module_code: ModuleCode;
  granted_at: string;
}

export type ModuleCode = 'admin' | 'medical' | 'life' | 'motor' | 'travel';

export interface RolesByGuard {
  web?: string[];
  medical?: string[];
  life?: string[];
  motor?: string[];
  travel?: string[];
}

export interface PermissionsByGuard {
  web?: string[];
  medical?: string[];
  life?: string[];
  motor?: string[];
  travel?: string[];
}

export interface UserContext {
  user: User;
  modules: ModuleCode[];
  module_access: ModuleAccess[];
  roles: RolesByGuard;
  permissions: PermissionsByGuard;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginResponse {
  message: string;
  data: {
    user: User;
    token: string;
    modules: ModuleCode[];
    roles: string[];
    permissions: string[];
  };
}

export interface AuthState {
  isAuthenticated: boolean;
  user: User | null;
  token: string | null;
  modules: ModuleCode[];
  roles: RolesByGuard;
  permissions: PermissionsByGuard;
  loading: boolean;
  error: string | null;
}

/**
 * User Management Models
 */
export interface UserListItem extends User {
  module_access?: ModuleAccess[];
  roles?: Role[];
  created_at?: string;
  updated_at?: string;
}

export interface UserDetail extends UserListItem {
  permissions_by_guard?: PermissionsByGuard;
  module_codes?: ModuleCode[];
}

export interface Role {
  id: string;
  name: string;
  guard_name: string;
  permissions?: Permission[];
}

export interface Permission {
  id: string;
  name: string;
  guard_name: string;
}

export interface PermissionGroup {
  [category: string]: Permission[];
}

export interface CreateUserRequest {
  email: string;
  username?: string;
  password: string;
  is_system_admin?: boolean;
  is_active?: boolean;
  mfa_enabled?: boolean;
}

export interface UpdateUserRequest {
  email?: string;
  username?: string;
  password?: string;
  is_system_admin?: boolean;
  is_active?: boolean;
  mfa_enabled?: boolean;
}

export interface AssignRolesRequest {
  roles: string[];
  guard?: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ApiResponse<T> {
  data: T;
  message?: string;
}
