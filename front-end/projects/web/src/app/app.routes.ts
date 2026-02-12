import { Routes } from '@angular/router';
import { memberAuthGuard } from './guards/member-auth.guard';

export const routes: Routes = [
  // ─── PUBLIC SHELL (top navbar layout) ────────────────────────────────────
  {
    path: '',
    loadComponent: () =>
      import('./layout/web-layout/web-layout').then((m) => m.WebLayout),
    children: [
      // Landing page
      {
        path: '',
        loadComponent: () => import('./pages/home/home').then((m) => m.HomePage),
      },

      // ── Customer: Browse & Quote (Step 2) ───────────────────────────────
      {
        path: 'plans',
        loadComponent: () =>
          import('./pages/plans/plans-list').then((m) => m.PlansListPage),
      },
      {
        path: 'plans/:id',
        loadComponent: () =>
          import('./pages/plans/plan-detail').then((m) => m.PlanDetailPage),
      },
      {
        path: 'quote',
        loadComponent: () => import('./pages/quote/quote').then((m) => m.QuotePage),
      },

      // ── Customer: Apply & Portal (Step 3) ────────────────────────────────
      {
        path: 'apply',
        loadComponent: () => import('./pages/apply/apply').then((m) => m.ApplyPage),
      },
      {
        path: 'login',
        loadComponent: () => import('./pages/login/login').then((m) => m.LoginPage),
      },

      // ── Provider: Public pages (Steps 4 & 5) ─────────────────────────────
      {
        path: 'provider/register',
        loadComponent: () =>
          import('./pages/provider/register/provider-register').then(
            (m) => m.ProviderRegisterPage
          ),
      },
      {
        path: 'provider/login',
        loadComponent: () =>
          import('./pages/provider/login/provider-login').then(
            (m) => m.ProviderLoginPage
          ),
      },
    ],
  },

  // ─── PROVIDER PORTAL SHELL (sidebar layout — Steps 5 & 6) ──────────────
  {
    path: 'provider',
    loadComponent: () =>
      import('./layout/provider-layout/provider-layout').then(
        (m) => m.ProviderLayout
      ),
    children: [
      {
        path: '',
        loadComponent: () =>
          import('./pages/provider/dashboard/provider-dashboard').then(
            (m) => m.ProviderDashboardPage
          ),
      },
      {
        path: 'eligibility',
        loadComponent: () =>
          import('./pages/provider/eligibility/provider-eligibility').then(
            (m) => m.ProviderEligibilityPage
          ),
      },
      {
        path: 'preauth',
        loadComponent: () =>
          import('./pages/provider/preauth/provider-preauth').then(
            (m) => m.ProviderPreauthPage
          ),
      },
      {
        path: 'claims',
        loadComponent: () =>
          import('./pages/provider/claims/provider-claims').then(
            (m) => m.ProviderClaimsPage
          ),
      },
    ],
  },

  // ─── MEMBER PORTAL SHELL (authenticated member area) ────────────────────
  {
    path: 'portal',
    canActivate: [memberAuthGuard],
    loadComponent: () =>
      import('./layout/portal-layout/portal-layout').then(
        (m) => m.PortalLayout
      ),
    children: [
      {
        path: '',
        redirectTo: 'dashboard',
        pathMatch: 'full',
      },
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./pages/my-portal/dashboard/dashboard').then(
            (m) => m.PortalDashboard
          ),
      },
      {
        path: 'policy',
        loadComponent: () =>
          import('./pages/my-portal/policy/policy').then(
            (m) => m.PortalPolicy
          ),
      },
      {
        path: 'claims',
        loadComponent: () =>
          import('./pages/my-portal/claims/claims-list').then(
            (m) => m.PortalClaimsList
          ),
      },
      {
        path: 'claims/new',
        loadComponent: () =>
          import('./pages/my-portal/claims/claim-submit').then(
            (m) => m.PortalClaimSubmit
          ),
      },
      {
        path: 'claims/:id',
        loadComponent: () =>
          import('./pages/my-portal/claims/claim-detail').then(
            (m) => m.PortalClaimDetail
          ),
      },
      {
        path: 'id-cards',
        loadComponent: () =>
          import('./pages/my-portal/id-cards/id-cards').then(
            (m) => m.PortalIdCards
          ),
      },
      {
        path: 'profile',
        loadComponent: () =>
          import('./pages/my-portal/profile/profile').then(
            (m) => m.PortalProfile
          ),
      },
    ],
  },

  // Catch-all
  { path: '**', redirectTo: '' },
];
