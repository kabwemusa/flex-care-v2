// libs/medical/feature/src/lib/dialogs/medical-application-addon-dialog/medical-application-addon-dialog.ts

import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
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
  private readonly http = inject(HttpClient);
  private readonly feedback = inject(FeedbackService);

  readonly addonTypes = ADDON_TYPES;
  readonly pricingTypes = ADDON_PRICING_TYPES;

  searchQuery = signal('');
  selectedType = signal('');
  selectedAddon = signal<Addon | null>(null);
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

  estimatedPremium = computed(() => {
    const addon = this.selectedAddon();
    if (!addon) return 0;

    const memberCount = this.data.memberCount;
    const basePremium = this.data.basePremium;

    switch (addon.pricing_type) {
      case 'fixed':
        return addon.amount || 0;
      case 'per_member':
        return (addon.amount || 0) * memberCount;
      case 'percentage':
        const basis = addon.percentage_basis === 'total_premium' ? basePremium : basePremium;
        return Math.round(basis * ((addon.percentage || 0) / 100) * 100) / 100;
      default:
        return 0;
    }
  });

  ngOnInit() {
    this.loadAvailableAddons();
  }

  private loadAvailableAddons() {
    this.isLoading.set(true);

    // Load plan addons directly via HTTP (configured addons for this plan)
    this.http.get<any>(`/api/v1/medical/plans/${this.data.planId}/addons`).subscribe({
      next: (res) => {
        const planAddons = res.data || [];

        // Extract addons from plan addons and filter out already added ones
        const available = planAddons
          .filter((pa: any) => pa.addon && !this.data.existingAddonIds.includes(pa.addon_id))
          .map((pa: any) => pa.addon)
          .filter((addon: any) => addon !== null && addon !== undefined);

        this.availableAddons.set(available);
        this.isLoading.set(false);
      },
      error: () => {
        this.feedback.error('Failed to load available addons');
        this.isLoading.set(false);
      }
    });
  }

  onSearchInput(event: Event) {
    this.searchQuery.set((event.target as HTMLInputElement).value);
  }

  selectAddon(addon: Addon) {
    this.selectedAddon.set(addon);
  }

  backToList() {
    this.selectedAddon.set(null);
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

  getPricingDescription(addon: Addon): string {
    switch (addon.pricing_type) {
      case 'fixed':
        return `${addon.currency || 'ZMW'} ${this.formatPremium(addon.amount || 0)} (flat rate)`;
      case 'per_member':
        return `${addon.currency || 'ZMW'} ${this.formatPremium(addon.amount || 0)} per member`;
      case 'percentage':
        return `${addon.percentage}% of ${
          addon.percentage_basis === 'total_premium' ? 'total premium' : 'base premium'
        }`;
      default:
        return 'Unknown pricing';
    }
  }

  canAdd(): boolean {
    const addon = this.selectedAddon();
    // Must have addon selected and be active
    return !!addon && addon.is_active;
  }

  addAddon() {
    const addon = this.selectedAddon();

    if (!this.canAdd() || !addon) return;

    this.dialogRef.close({
      addonId: addon.id,
    });
  }
}
