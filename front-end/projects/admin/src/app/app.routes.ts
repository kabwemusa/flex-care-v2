import { Routes } from '@angular/router';
import { authGuard, moduleGuard, systemAdminGuard, anyPermissionGuard } from 'core-guards';
import { MEDICAL_PERMISSIONS, MODULES } from 'core-auth';

export const routes: Routes = [
  // Public routes
  {
    path: 'login',
    loadComponent: () => import('./pages/login/login.component').then((m) => m.LoginComponent),
  },

  // Change password route (requires auth but no layout)
  {
    path: 'change-password',
    loadComponent: () =>
      import('./pages/change-password/change-password.component').then(
        (m) => m.ChangePasswordComponent
      ),
    canActivate: [authGuard],
  },

  // Protected routes - all within the Medical shell (will be renamed to generic shell later)
  {
    path: '',
    loadComponent: () => import('medical-ui').then((m) => m.MedicalMain),
    canActivate: [authGuard],
    children: [
      // Dashboard (default route)
      {
        path: '',
        loadComponent: () =>
          import('./pages/dashboard/dashboard.component').then((m) => m.DashboardComponent),
      },
      // Global Admin Routes (system admin only)
      {
        path: 'admin/users',
        loadComponent: () =>
          import('./pages/user-management/user-management.component').then(
            (m) => m.UserManagementComponent
          ),
        canActivate: [systemAdminGuard],
      },
      {
        path: 'admin/roles',
        loadComponent: () =>
          import('./pages/roles-permissions/roles-permissions.component').then(
            (m) => m.RolesPermissionsComponent
          ),
        canActivate: [systemAdminGuard],
      },
      // Medical Module Routes
      {
        path: 'schemes',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalSchemesList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.SCHEMES_VIEW] },
      },
      {
        path: 'plans',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalPlanList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.PLANS_VIEW] },
      },
      {
        path: 'plans/:id',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalPlanDetail),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.PLANS_VIEW] },
      },
      {
        path: 'benefits',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalBenefitsCatalog),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.PLANS_VIEW] },
      },
      {
        path: 'benefits-config',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalPlanBenefitsConfig),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: {
          module: MODULES.MEDICAL,
          permissions: [MEDICAL_PERMISSIONS.PLANS_VIEW, MEDICAL_PERMISSIONS.PLANS_CONFIGURE],
        },
      },
      {
        path: 'addons',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalAddonsCatalog),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.ADDONS_VIEW] },
      },
      {
        path: 'rate-cards',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalRateCardsList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.RATE_CARDS_VIEW] },
      },
      {
        path: 'rate-cards/:id',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalRateCardDetail),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.RATE_CARDS_VIEW] },
      },
      {
        path: 'discounts',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalDiscountList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.PREMIUM_VIEW] },
      },
      {
        path: 'loading-rule',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalLoadingRuleList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.UNDERWRITING_VIEW] },
      },
      {
        path: 'applications',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalApplicationList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.APPLICATIONS_VIEW] },
      },
      {
        path: 'applications/:id',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalApplicationDetail),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.APPLICATIONS_VIEW] },
      },
      {
        path: 'groups',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalGroupsList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.GROUPS_VIEW] },
      },
      {
        path: 'groups/:id',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalGroupDetail),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.GROUPS_VIEW] },
      },
      {
        path: 'policies',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalPoliciesList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.POLICIES_VIEW] },
      },
      {
        path: 'policies/:id',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalPolicyDetail),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.POLICIES_VIEW] },
      },
      {
        path: 'members',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalMembersList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: MODULES.MEDICAL, permissions: [MEDICAL_PERMISSIONS.MEMBERS_VIEW] },
      },
    ],
  },
];
