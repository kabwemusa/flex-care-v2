import { Routes } from '@angular/router';
import { authGuard, moduleGuard, systemAdminGuard, anyPermissionGuard } from 'core-guards';

export const routes: Routes = [
  // Public routes
  {
    path: 'login',
    loadComponent: () => import('./pages/login/login.component').then((m) => m.LoginComponent),
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
        data: { module: 'medical', permissions: ['view schemes'], guard: 'medical' },
      },
      {
        path: 'plans',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalPlanList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: 'medical', permissions: ['view plans'], guard: 'medical' },
      },
      {
        path: 'plans/:id',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalPlanDetail),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: 'medical', permissions: ['view plans'], guard: 'medical' },
      },
      {
        path: 'benefits',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalBenefitsCatalog),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: 'medical', permissions: ['view benefits'], guard: 'medical' },
      },
      {
        path: 'benefits-config',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalPlanBenefitsConfig),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: 'medical', permissions: ['view benefits', 'update benefits'], guard: 'medical' },
      },
      {
        path: 'addons',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalAddonsCatalog),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: 'medical', permissions: ['view addons'], guard: 'medical' },
      },
      {
        path: 'rate-cards',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalRateCardsList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: 'medical', permissions: ['view rate cards'], guard: 'medical' },
      },
      {
        path: 'rate-cards/:id',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalRateCardDetail),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: 'medical', permissions: ['view rate cards'], guard: 'medical' },
      },
      {
        path: 'discounts',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalDiscountList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: 'medical', permissions: ['view discounts'], guard: 'medical' },
      },
      {
        path: 'loading-rule',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalLoadingRuleList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: 'medical', permissions: ['view loading rules'], guard: 'medical' },
      },
      {
        path: 'applications',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalApplicationList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: 'medical', permissions: ['view applications'], guard: 'medical' },
      },
      {
        path: 'applications/:id',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalApplicationDetail),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: 'medical', permissions: ['view applications'], guard: 'medical' },
      },
      {
        path: 'groups',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalGroupsList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: 'medical', permissions: ['view groups'], guard: 'medical' },
      },
      {
        path: 'policies',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalPoliciesList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: 'medical', permissions: ['view policies'], guard: 'medical' },
      },
      {
        path: 'members',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalMembersList),
        canActivate: [moduleGuard, anyPermissionGuard],
        data: { module: 'medical', permissions: ['view members'], guard: 'medical' },
      },
    ],
  },
];
