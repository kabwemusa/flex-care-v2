import { Component, signal, OnInit, effect } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { DecimalPipe, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';

// ── Interfaces ────────────────────────────────────────────────────────────

interface WebPlan {
  id: string;
  code: string;
  name: string;
  plan_type: string;
  tier_level: number;
  description: string;
  scheme: { name: string };
  plan_benefits_count: number;
  plan_addons_count: number;
}

interface WebAddon {
  id: string;
  code: string;
  name: string;
  description: string;
  availability: 'optional' | 'mandatory' | 'included' | 'conditional';
  availability_label: string;
  pricing_type: string;
  pricing_type_label: string;
  currency: string;
  amount: number | null;
  percentage: number | null;
}

interface QuoteMember {
  member_type: string;
  age: number;
  gender: string;
  name: string;
}

interface QuoteResult {
  plan: { id: string; code: string; name: string };
  rate_card: { id: string; code: string; version: string };
  premium_basis: string;
  members: { member_type: string; age: number; gender: string | null; premium: number }[];
  base_premium: number;
  addon_premium: number;
  addons: { name: string; amount: number; is_included?: boolean }[];
  discounts: { name: string; amount: number }[];
  total_discount: number;
  promo_discount: number;
  loadings: { condition: string; amount: number }[];
  total_loading: number;
  final_premium: number;
  currency: string;
  frequency: string;
  quote_date: string;
  valid_until: string;
}

// ── Component ─────────────────────────────────────────────────────────────

@Component({
  selector: 'app-quote',
  standalone: true,
  imports: [RouterModule, FormsModule, DecimalPipe, DatePipe],
  templateUrl: './quote.html',
})
export class QuotePage implements OnInit {
  // Navigation
  step = signal(1);

  // Step 1: Plans
  plans = signal<WebPlan[]>([]);
  planFilter = signal('all');
  filteredPlans = signal<WebPlan[]>([]);
  selectedPlan = signal<WebPlan | null>(null);
  plansLoading = signal(true);

  // Step 2: Members
  members = signal<QuoteMember[]>([{ member_type: 'principal', age: 30, gender: 'M', name: '' }]);

  // Step 3: Customize + Quote
  addons = signal<WebAddon[]>([]);
  selectedAddonIds = signal<string[]>([]);
  promoCode = signal('');
  billingFrequency = signal('monthly');
  quoteResult = signal<QuoteResult | null>(null);
  quoteLoading = signal(false);
  quoteError = signal('');

  constructor(
    private http: HttpClient,
    private route: ActivatedRoute,
    private router: Router
  ) {}

  ngOnInit() {
    this.loadPlans();

    // If a plan ID was passed via query param (from plans page), pre-select it
    this.route.queryParams.subscribe((params) => {
      if (params['plan']) {
        this.plans().length > 0
          ? this.preselectPlan(params['plan'])
          : // Plans not loaded yet — store for later
            effect(() => {
              if (this.plans().length > 0) {
                this.preselectPlan(params['plan']);
              }
            });
      }
    });
  }

  // ── Data Loading ────────────────────────────────────────────────────────

  loadPlans() {
    this.plansLoading.set(true);
    this.http
      .get<{ data: WebPlan[] }>('/v1/medical/public/plans', {
        params: { active_only: 'true', per_page: '50' },
      })
      .subscribe({
        next: (res) => {
          this.plans.set(res.data);
          this.applyPlanFilter();
          this.plansLoading.set(false);
        },
        error: () => this.plansLoading.set(false),
      });
  }

  /**
   * Load plan-specific addons with availability info.
   * Auto-selects mandatory and included addons.
   */
  loadAddons() {
    const plan = this.selectedPlan();
    if (!plan) {
      this.addons.set([]);
      this.selectedAddonIds.set([]);
      return;
    }

    this.http
      .get<{ data: WebAddon[] }>(`/v1/medical/public/plans/${plan.id}/addons`)
      .subscribe({
        next: (res) => {
          this.addons.set(res.data);
          // Auto-select mandatory and included addons
          const lockedIds = res.data
            .filter((a) => a.availability === 'mandatory' || a.availability === 'included')
            .map((a) => a.id);
          this.selectedAddonIds.set(lockedIds);
        },
        error: () => {
          this.addons.set([]);
          this.selectedAddonIds.set([]);
        },
      });
  }

  private preselectPlan(planId: string) {
    const plan = this.plans().find((p) => p.id === planId);
    if (plan) {
      this.selectedPlan.set(plan);
      this.loadAddons();
      this.step.set(2);
    }
  }

  // ── Step 1: Plan Selection ──────────────────────────────────────────────

  setPlanFilter(filter: string) {
    this.planFilter.set(filter);
    this.applyPlanFilter();
  }

  private applyPlanFilter() {
    const filter = this.planFilter();
    const all = this.plans();
    this.filteredPlans.set(filter === 'all' ? all : all.filter((p) => p.plan_type === filter));
  }

  selectPlan(plan: WebPlan) {
    this.selectedPlan.set(plan);
    this.loadAddons();
    this.step.set(2);
  }

  tierLabel(level: number): string {
    const labels: Record<number, string> = { 1: 'Basic', 2: 'Standard', 3: 'Premium', 4: 'Elite' };
    return labels[level] ?? `Tier ${level}`;
  }

  planTypeIcon(type: string): string {
    const icons: Record<string, string> = {
      individual: 'person',
      family: 'family_restroom',
      corporate: 'business',
      sme: 'store',
    };
    return icons[type] ?? 'health_and_safety';
  }

  // ── Step 2: Members ─────────────────────────────────────────────────────

  addMember(type: string) {
    this.members.update((m) => [...m, { member_type: type, age: type === 'child' ? 5 : 30, gender: 'M', name: '' }]);
  }

  removeMember(index: number) {
    if (index === 0) return; // Can't remove principal
    this.members.update((m) => m.filter((_, i) => i !== index));
  }

  updateMember(index: number, field: string, value: string | number) {
    this.members.update((m) =>
      m.map((member, i) => (i === index ? { ...member, [field]: value } : member))
    );
  }

  canAddDependent(type: string): boolean {
    const members = this.members();
    if (type === 'spouse') return !members.some((m) => m.member_type === 'spouse');
    if (type === 'parent') return members.filter((m) => m.member_type === 'parent').length < 2;
    if (type === 'child') return members.filter((m) => m.member_type === 'child').length < 5;
    return false;
  }

  // ── Step 3: Quote Calculation ───────────────────────────────────────────

  toggleAddon(addonId: string) {
    // Prevent toggling mandatory or included addons
    const addon = this.addons().find((a) => a.id === addonId);
    if (addon && (addon.availability === 'mandatory' || addon.availability === 'included')) {
      return;
    }
    const current = this.selectedAddonIds();
    const updated = current.includes(addonId) ? current.filter((id) => id !== addonId) : [...current, addonId];
    this.selectedAddonIds.set(updated);
    this.calculateQuote();
  }

  isAddonSelected(addonId: string): boolean {
    return this.selectedAddonIds().includes(addonId);
  }

  // Addon classification helpers
  includedAddons(): WebAddon[] {
    return this.addons().filter((a) => a.availability === 'included');
  }

  mandatoryAddons(): WebAddon[] {
    return this.addons().filter((a) => a.availability === 'mandatory');
  }

  optionalAddons(): WebAddon[] {
    return this.addons().filter((a) => a.availability === 'optional');
  }

  calculateQuote() {
    const plan = this.selectedPlan();
    if (!plan) return;

    this.quoteLoading.set(true);
    this.quoteError.set('');

    const payload = {
      plan_id: plan.id,
      members: this.members().map((m) => ({
        member_type: m.member_type,
        age: m.age,
        gender: m.gender || null,
      })),
      addon_ids: this.selectedAddonIds(),
      promo_code: this.promoCode() || undefined,
      discount_context: { billing_frequency: this.billingFrequency() },
    };

    this.http.post<{ data: QuoteResult }>('/v1/medical/public/quotes', payload).subscribe({
      next: (res) => {
        this.quoteResult.set(res.data);
        this.quoteLoading.set(false);
      },
      error: (err) => {
        this.quoteError.set(err.error?.message ?? 'Failed to calculate quote');
        this.quoteLoading.set(false);
      },
    });
  }

  // ── Navigation ──────────────────────────────────────────────────────────

  canNavigateToStep(s: number): boolean {
    if (s === 1) return true; // Can always go back to step 1
    if (s === 2) return this.selectedPlan() !== null; // Need a plan selected
    if (s === 3) return this.selectedPlan() !== null && this.isStep2Valid(); // Need plan + valid members
    return false;
  }

  isStep2Valid(): boolean {
    const members = this.members();
    return members.every(m => m.age > 0 && m.age <= 120 && (m.gender === 'M' || m.gender === 'F'));
  }

  goToStep(s: number) {
    if (!this.canNavigateToStep(s)) return;
    if (s === 3) this.calculateQuote(); // Trigger initial quote when entering step 3
    this.step.set(s);
  }

  frequencyLabel(f: string): string {
    const labels: Record<string, string> = {
      monthly: 'Monthly',
      quarterly: 'Quarterly',
      semi_annual: 'Semi-Annual',
      annual: 'Annual',
    };
    return labels[f] ?? f;
  }

  /** Check if the rate card uses per-member age-banded pricing */
  isPerMemberPricing(): boolean {
    return this.quoteResult()?.premium_basis === 'per_member';
  }

  proceedToApply() {
    const quote = this.quoteResult();
    if (!quote) return;
    localStorage.setItem('flex_quote', JSON.stringify({
      quote,
      members: this.members(),
      selectedAddonIds: this.selectedAddonIds(),
      billingFrequency: this.billingFrequency(),
    }));
    this.router.navigate(['/apply']);
  }

  selectValue(event: Event): string {
    return (event.target as HTMLSelectElement).value;
  }

  memberTypeLabel(type: string): string {
    const labels: Record<string, string> = {
      principal: 'Principal',
      spouse: 'Spouse',
      child: 'Child',
      parent: 'Parent',
    };
    return labels[type] ?? type;
  }
}
