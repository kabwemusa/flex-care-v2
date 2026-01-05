// libs/medical/feature/src/lib/dialogs/medical-application-addon-dialog/medical-application-addon-dialog.ts

import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatRadioModule } from '@angular/material/radio';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDividerModule } from '@angular/material/divider';
import { MatChipsModule } from '@angular/material/chips';

import {
  Addon,
  AddonRate,
  AddonCatalogStore,
  ADDON_TYPES,
  ADDON_PRICING_TYPES,
  getLabelByValue,
} from 'medical-data';
import { FeedbackService } from 'shared';

interface DialogData {
  planId: string;
  memberCount: number;
  basePremium: number;
  existingAddonIds: string[];
}

@Component({
  selector: 'lib-medical-application-addon-dialog',
  standalone: true,
  imports: [
    CommonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
    MatRadioModule,
    MatProgressSpinnerModule,
    MatDividerModule,
    MatChipsModule,
  ],
  templateUrl: './medical-application-addon-dialog.html',
})
export class MedicalApplicationAddonDialog implements OnInit {
  readonly dialogRef = inject(MatDialogRef<MedicalApplicationAddonDialog>);
  readonly data = inject<DialogData>(MAT_DIALOG_DATA);
  readonly store = inject(AddonCatalogStore);
  private readonly feedback = inject(FeedbackService);

  readonly addonTypes = ADDON_TYPES;
  readonly pricingTypes = ADDON_PRICING_TYPES;

  searchQuery = signal('');
  selectedType = signal('');
  selectedAddon = signal<Addon | null>(null);
  selectedRateId = signal<string | null>(null);
  isLoading = signal(true);
  availableAddons = signal<Addon[]>([]);

  filteredAddons = computed(() => {
    let addons = this.availableAddons();

    const search = this.searchQuery().toLowerCase();
    if (search) {
      addons = addons.filter(
        (a) =>
          a.name.toLowerCase().includes(search) ||
          a.code.toLowerCase().includes(search) ||
          a.description?.toLowerCase().includes(search)
      );
    }

    const type = this.selectedType();
    if (type) {
      addons = addons.filter((a) => a.addon_type === type);
    }

    return addons;
  });

  addonRates = computed(() => {
    const addon = this.selectedAddon();
    if (!addon || !addon.rates) return [];

    // Filter active and effective rates
    return addon.rates.filter((r) => r.is_active && r.is_effective);
  });

  selectedRate = computed(() => {
    const rateId = this.selectedRateId();
    const rates = this.addonRates();
    if (!rateId || rates.length === 0) return null;
    return rates.find((r) => r.id === rateId) || null;
  });

  estimatedPremium = computed(() => {
    const rate = this.selectedRate();
    if (!rate) return 0;

    const memberCount = this.data.memberCount;
    const basePremium = this.data.basePremium;

    switch (rate.pricing_type) {
      case 'fixed':
        return rate.amount || 0;
      case 'per_member':
        return (rate.amount || 0) * memberCount;
      case 'percentage':
        const basis = rate.percentage_basis === 'total_premium' ? basePremium : basePremium;
        return Math.round(basis * ((rate.percentage || 0) / 100) * 100) / 100;
      case 'age_rated':
        // For age-rated, show a note that calculation depends on member ages
        return 0;
      default:
        return 0;
    }
  });

  ngOnInit() {
    this.loadAvailableAddons();
  }

  private loadAvailableAddons() {
    this.isLoading.set(true);
    this.store.loadAvailableAddons(this.data.planId).subscribe({
      next: (res) => {
        // Filter out already added addons
        const available = res.data.filter((a) => !this.data.existingAddonIds.includes(a.id));
        this.availableAddons.set(available);
        this.isLoading.set(false);
      },
      error: () => {
        this.feedback.error('Failed to load available addons');
        this.isLoading.set(false);
      },
    });
  }

  onSearchInput(event: Event) {
    this.searchQuery.set((event.target as HTMLInputElement).value);
  }

  selectAddon(addon: Addon) {
    this.selectedAddon.set(addon);

    // Auto-select the first active rate if available
    const rates = addon.rates?.filter((r) => r.is_active && r.is_effective) || [];
    if (rates.length > 0) {
      // Prefer plan-specific rates over global rates
      const planSpecificRate = rates.find((r) => r.is_plan_specific);
      this.selectedRateId.set(planSpecificRate?.id || rates[0].id);
    } else {
      this.selectedRateId.set(null);
    }
  }

  backToList() {
    this.selectedAddon.set(null);
    this.selectedRateId.set(null);
  }

  getAddonTypeLabel(value: string): string {
    return getLabelByValue(ADDON_TYPES, value);
  }

  getPricingTypeLabel(value: string): string {
    return getLabelByValue(ADDON_PRICING_TYPES, value);
  }

  getAddonTypeIcon(type: string): string {
    const icons: Record<string, string> = {
      optional: 'add_circle',
      mandatory: 'check_circle',
      conditional: 'help',
    };
    return icons[type] || 'extension';
  }

  getAddonTypeClass(type: string): string {
    const classes: Record<string, string> = {
      optional: 'bg-blue-100 text-blue-600',
      mandatory: 'bg-purple-100 text-purple-600',
      conditional: 'bg-amber-100 text-amber-600',
    };
    return classes[type] || 'bg-slate-100 text-slate-600';
  }

  formatPremium(amount: number): string {
    return new Intl.NumberFormat('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount);
  }

  getRatePricingDescription(rate: AddonRate): string {
    switch (rate.pricing_type) {
      case 'fixed':
        return `ZMW ${this.formatPremium(rate.amount || 0)} (one-time)`;
      case 'per_member':
        return `ZMW ${this.formatPremium(rate.amount || 0)} per member`;
      case 'percentage':
        return `${rate.percentage}% of ${rate.percentage_basis === 'total_premium' ? 'total premium' : 'base premium'}`;
      case 'age_rated':
        return 'Age-rated pricing';
      default:
        return 'Unknown pricing';
    }
  }

  canAdd(): boolean {
    const addon = this.selectedAddon();
    const rateId = this.selectedRateId();

    // Must have addon selected
    if (!addon) return false;

    // If addon has rates, must select one
    const rates = this.addonRates();
    if (rates.length > 0 && !rateId) return false;

    return true;
  }

  addAddon() {
    const addon = this.selectedAddon();
    const rateId = this.selectedRateId();

    if (!this.canAdd() || !addon) return;

    this.dialogRef.close({
      addonId: addon.id,
      addonRateId: rateId,
    });
  }
}
