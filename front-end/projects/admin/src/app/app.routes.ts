import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    loadComponent: () => import('medical-ui').then((m) => m.MedicalMain),
    children: [
      {
        path: 'schemes',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalSchemesList),
      },
      {
        path: 'plans',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalPlanList),
      },
      {
        path: 'plans/:id',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalPlanDetail),
      },
      {
        path: 'benefits',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalBenefitsCatalog),
      },
      {
        path: 'benefits-config',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalPlanBenefitsConfig),
      },
      {
        path: 'addons',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalAddonsCatalog),
      },
      {
        path: 'rate-cards',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalRateCardsList),
      },
      {
        path: 'rate-cards/:id',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalRateCardDetail),
      },
      {
        path: 'discounts',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalDiscountList),
      },
      {
        path: 'loading-rule',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalLoadingRuleList),
      },
      {
        path: 'groups',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalGroupsList),
      },
      {
        path: 'policies',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalPoliciesList),
      },
      {
        path: 'members',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalMembersList),
      },
    ],
  },
];
