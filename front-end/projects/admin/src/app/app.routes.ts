import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    loadComponent: () => import('medical-ui').then((m) => m.MedicalMain),
    children: [
      {
        path: 'schemes',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalSchemes),
      },
      {
        path: 'plans',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalPlans),
      },
      {
        path: 'features',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalFeatures),
      },
      {
        path: 'addons',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalAddons),
      },
      {
        path: 'rate-cards',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalRateCards),
      },
      {
        path: 'discounts',
        loadComponent: () => import('medical-feature').then((m) => m.MedicalDiscounts),
      },
    ],
  },
];
