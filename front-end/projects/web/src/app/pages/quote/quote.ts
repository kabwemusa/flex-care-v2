import { Component, signal, computed, OnInit, inject, DestroyRef } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { DecimalPipe, DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import {
  PublicMedicalService,
  WebPlan,
  WebAddon,
  QuoteMember,
  QuoteResult,
} from '../../services/public-medical.service';
import { ToastService } from '../../services/toast.service';
import { UiUtilsService } from '../../services/ui-utils.service';

// ── Component ─────────────────────────────────────────────────────────────

@Component({
  selector: 'app-quote',
  standalone: true,
  imports: [RouterModule, FormsModule, DecimalPipe, DatePipe],
  templateUrl: './quote.html',
})
export class QuotePage implements OnInit {
  // ── Services (inject pattern for standalone components) ──────────────────
  private readonly medicalService = inject(PublicMedicalService);
  private readonly toast          = inject(ToastService);
  readonly ui                     = inject(UiUtilsService);
  private readonly route          = inject(ActivatedRoute);
  private readonly router         = inject(Router);
  private readonly destroyRef     = inject(DestroyRef);

  // ── Navigation ───────────────────────────────────────────────────────────
  step = signal(1);

  // ── Step 1: Plans ────────────────────────────────────────────────────────
  plans         = signal<WebPlan[]>([]);
  planFilter    = signal('all');
  selectedPlan  = signal<WebPlan | null>(null);
  plansLoading  = signal(true);

  /** Derived: recomputed only when plans or planFilter changes — no re-run on every CD cycle. */
  filteredPlans = computed(() => {
    const filter = this.planFilter();
    const all    = this.plans();
    return filter === 'all' ? all : all.filter((p) => p.plan_type === filter);
  });

  // ── Step 2: Members ──────────────────────────────────────────────────────
  members = signal<QuoteMember[]>([
    { member_type: 'principal', age: 30, gender: 'M', name: '' },
  ]);

  // ── Step 3: Customize + Quote ────────────────────────────────────────────
  addons           = signal<WebAddon[]>([]);
  selectedAddonIds = signal<string[]>([]);
  promoCode        = signal('');
  billingFrequency = signal('monthly');
  quoteResult      = signal<QuoteResult | null>(null);
  quoteLoading     = signal(false);

  /** Derived addon groups — computed instead of methods called on every render. */
  includedAddons  = computed(() => this.addons().filter((a) => a.availability === 'included'));
  mandatoryAddons = computed(() => this.addons().filter((a) => a.availability === 'mandatory'));
  optionalAddons  = computed(() => this.addons().filter((a) => a.availability === 'optional'));

  // ── Pre-select plan ID waiting to be resolved ────────────────────────────
  private pendingPlanId: string | null = null;

  // ── Lifecycle ────────────────────────────────────────────────────────────

  ngOnInit(): void {
    // Read the query param ONCE synchronously — no open subscription needed.
    const planId = this.route.snapshot.queryParamMap.get('plan');
    if (planId) {
      this.pendingPlanId = planId;
    }

    this.loadPlans();
  }

  // ── Data Loading ─────────────────────────────────────────────────────────

  loadPlans(): void {
    this.plansLoading.set(true);

    this.medicalService.getPlans()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.plans.set(res.data);
          this.plansLoading.set(false);

          // Resolve pre-selected plan now that data is available.
          if (this.pendingPlanId) {
            this.preselectPlan(this.pendingPlanId);
            this.pendingPlanId = null;
          }
        },
        error: () => {
          this.plansLoading.set(false);
          this.toast.error('Could not load plans. Please refresh and try again.');
        },
      });
  }

  loadAddons(): void {
    const plan = this.selectedPlan();
    if (!plan) {
      this.addons.set([]);
      this.selectedAddonIds.set([]);
      return;
    }

    this.medicalService.getPlanAddons(plan.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.addons.set(res.data);
          // Auto-select mandatory and included addons — user cannot deselect these.
          const lockedIds = res.data
            .filter((a) => a.availability === 'mandatory' || a.availability === 'included')
            .map((a) => a.id);
          this.selectedAddonIds.set(lockedIds);
        },
        error: () => {
          this.addons.set([]);
          this.selectedAddonIds.set([]);
          this.toast.error('Could not load add-ons for this plan.');
        },
      });
  }

  private preselectPlan(planId: string): void {
    const plan = this.plans().find((p) => p.id === planId);
    if (plan) {
      this.selectedPlan.set(plan);
      this.loadAddons();
      this.step.set(2);
    }
  }

  // ── Step 1: Plan Selection ────────────────────────────────────────────────

  setPlanFilter(filter: string): void {
    this.planFilter.set(filter);
  }

  selectPlan(plan: WebPlan): void {
    this.selectedPlan.set(plan);
    this.loadAddons();
    this.step.set(2);
  }

  // ── Step 2: Members ───────────────────────────────────────────────────────

  addMember(type: string): void {
    this.members.update((m) => [
      ...m,
      { member_type: type, age: type === 'child' ? 5 : 30, gender: 'M', name: '' },
    ]);
  }

  removeMember(index: number): void {
    if (index === 0) return; // Principal cannot be removed.
    this.members.update((m) => m.filter((_, i) => i !== index));
  }

  updateMember(index: number, field: string, value: string | number): void {
    this.members.update((m) =>
      m.map((member, i) => (i === index ? { ...member, [field]: value } : member))
    );
  }

  canAddDependent(type: string): boolean {
    const m = this.members();
    if (type === 'spouse') return !m.some((x) => x.member_type === 'spouse');
    if (type === 'parent') return m.filter((x) => x.member_type === 'parent').length < 2;
    if (type === 'child')  return m.filter((x) => x.member_type === 'child').length < 5;
    return false;
  }

  // ── Step 3: Addons & Quote ────────────────────────────────────────────────

  toggleAddon(addonId: string): void {
    const addon = this.addons().find((a) => a.id === addonId);
    // Locked addons cannot be toggled.
    if (addon && (addon.availability === 'mandatory' || addon.availability === 'included')) return;

    const current = this.selectedAddonIds();
    const updated  = current.includes(addonId)
      ? current.filter((id) => id !== addonId)
      : [...current, addonId];

    this.selectedAddonIds.set(updated);
    this.calculateQuote();
  }

  isAddonSelected(addonId: string): boolean {
    return this.selectedAddonIds().includes(addonId);
  }

  calculateQuote(): void {
    const plan = this.selectedPlan();
    if (!plan) return;

    this.quoteLoading.set(true);

    this.medicalService
      .calculateQuote({
        plan_id:          plan.id,
        members:          this.members().map((m) => ({
          member_type: m.member_type,
          age:         m.age,
          gender:      m.gender || null,
        })),
        addon_ids:        this.selectedAddonIds(),
        promo_code:       this.promoCode() || undefined,
        discount_context: { billing_frequency: this.billingFrequency() },
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.quoteResult.set(res.data);
          this.quoteLoading.set(false);
        },
        error: (err) => {
          this.quoteLoading.set(false);
          this.toast.error(err.error?.message ?? 'Failed to calculate quote. Please try again.');
        },
      });
  }

  // ── Navigation ────────────────────────────────────────────────────────────

  canNavigateToStep(s: number): boolean {
    if (s === 1) return true;
    if (s === 2) return this.selectedPlan() !== null;
    if (s === 3) return this.selectedPlan() !== null && this.isStep2Valid();
    return false;
  }

  isStep2Valid(): boolean {
    return this.members().every(
      (m) => m.age > 0 && m.age <= 120 && (m.gender === 'M' || m.gender === 'F')
    );
  }

  goToStep(s: number): void {
    if (!this.canNavigateToStep(s)) return;
    if (s === 3) this.calculateQuote();
    this.step.set(s);
  }

  isPerMemberPricing(): boolean {
    return this.quoteResult()?.premium_basis === 'per_member';
  }

  proceedToApply(): void {
    const quote = this.quoteResult();
    if (!quote) return;

    localStorage.setItem(
      'flex_quote',
      JSON.stringify({
        quote,
        members:          this.members(),
        selectedAddonIds: this.selectedAddonIds(),
        billingFrequency: this.billingFrequency(),
      })
    );

    this.router.navigate(['/apply']);
  }

  // ── Template helpers ──────────────────────────────────────────────────────

  selectValue(event: Event): string {
    return (event.target as HTMLSelectElement).value;
  }
}
