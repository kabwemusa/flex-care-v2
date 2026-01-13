/**
 * Permission Constants
 * Central place to manage all permission strings
 * Format: {MODULE}.{RESOURCE}.{ACTION}
 */

// Medical Module Permissions
export const MEDICAL_PERMISSIONS = {
  // Schemes
  SCHEMES_VIEW: 'medical.schemes.view',
  SCHEMES_CREATE: 'medical.schemes.create',
  SCHEMES_UPDATE: 'medical.schemes.update',
  SCHEMES_DELETE: 'medical.schemes.delete',
  SCHEMES_ACTIVATE: 'medical.schemes.activate',
  SCHEMES_DEACTIVATE: 'medical.schemes.deactivate',

  // Plans
  PLANS_VIEW: 'medical.plans.view',
  PLANS_CREATE: 'medical.plans.create',
  PLANS_UPDATE: 'medical.plans.update',
  PLANS_DELETE: 'medical.plans.delete',
  PLANS_CONFIGURE: 'medical.plans.configure',

  // Addons
  ADDONS_VIEW: 'medical.addons.view',
  ADDONS_CREATE: 'medical.addons.create',
  ADDONS_UPDATE: 'medical.addons.update',
  ADDONS_DELETE: 'medical.addons.delete',

  // Rate Cards
  RATE_CARDS_VIEW: 'medical.rate_cards.view',
  RATE_CARDS_CREATE: 'medical.rate_cards.create',
  RATE_CARDS_UPDATE: 'medical.rate_cards.update',
  RATE_CARDS_DELETE: 'medical.rate_cards.delete',
  RATE_CARDS_ACTIVATE: 'medical.rate_cards.activate',
  RATE_CARDS_DEACTIVATE: 'medical.rate_cards.deactivate',

  // Premium
  PREMIUM_VIEW: 'medical.premium.view',
  PREMIUM_CALCULATE: 'medical.premium.calculate',
  PREMIUM_OVERRIDE: 'medical.premium.override',

  // Applications
  APPLICATIONS_VIEW: 'medical.applications.view',
  APPLICATIONS_CREATE: 'medical.applications.create',
  APPLICATIONS_UPDATE: 'medical.applications.update',
  APPLICATIONS_DELETE: 'medical.applications.delete',
  APPLICATIONS_QUOTE: 'medical.applications.quote',
  APPLICATIONS_SUBMIT: 'medical.applications.submit',

  // Underwriting
  UNDERWRITING_VIEW: 'medical.underwriting.view',
  UNDERWRITING_ASSESS: 'medical.underwriting.assess',
  UNDERWRITING_APPROVE: 'medical.underwriting.approve',
  UNDERWRITING_REJECT: 'medical.underwriting.reject',
  UNDERWRITING_ADD_LOADING: 'medical.underwriting.add_loading',
  UNDERWRITING_ADD_EXCLUSION: 'medical.underwriting.add_exclusion',

  // Groups
  GROUPS_VIEW: 'medical.groups.view',
  GROUPS_CREATE: 'medical.groups.create',
  GROUPS_UPDATE: 'medical.groups.update',
  GROUPS_DELETE: 'medical.groups.delete',

  // Policies
  POLICIES_VIEW: 'medical.policies.view',
  POLICIES_CREATE: 'medical.policies.create',
  POLICIES_UPDATE: 'medical.policies.update',
  POLICIES_CANCEL: 'medical.policies.cancel',
  POLICIES_SUSPEND: 'medical.policies.suspend',
  POLICIES_REINSTATE: 'medical.policies.reinstate',
  POLICIES_RENEW: 'medical.policies.renew',

  // Members
  MEMBERS_VIEW: 'medical.members.view',
  MEMBERS_ADD: 'medical.members.add',
  MEMBERS_UPDATE: 'medical.members.update',
  MEMBERS_REMOVE: 'medical.members.remove',
  MEMBERS_SUSPEND: 'medical.members.suspend',
  MEMBERS_EXIT: 'medical.members.exit',

  // Claims
  CLAIMS_VIEW: 'medical.claims.view',
  CLAIMS_PROCESS: 'medical.claims.process',
  CLAIMS_APPROVE: 'medical.claims.approve',
  CLAIMS_REJECT: 'medical.claims.reject',

  // Reports
  REPORTS_VIEW: 'medical.reports.view',
  REPORTS_EXPORT: 'medical.reports.export',
} as const;

// Module codes
export const MODULES = {
  ADMIN: 'admin',
  MEDICAL: 'medical',
  LIFE: 'life',
  MOTOR: 'motor',
  TRAVEL: 'travel',
} as const;

// Guards
export const GUARDS = {
  WEB: 'web',
  MEDICAL: 'medical',
  LIFE: 'life',
  MOTOR: 'motor',
  TRAVEL: 'travel',
} as const;

// Helper type for type-safe permission checking
export type MedicalPermission = (typeof MEDICAL_PERMISSIONS)[keyof typeof MEDICAL_PERMISSIONS];
// export type ModuleCode = typeof MODULES[keyof typeof MODULES];
export type GuardName = (typeof GUARDS)[keyof typeof GUARDS];
