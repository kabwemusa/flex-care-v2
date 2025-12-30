// libs/medical/ui/src/lib/dialogs/add-addon-to-plan-dialog/add-addon-to-plan-dialog.ts

import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

import {
  Addon,
  AddonCatalogStore,
  ADDON_TYPES,
  ADDON_AVAILABILITY,
  getLabelByValue,
} from 'medical-data';
import { FeedbackService } from 'shared';

interface DialogData {
  planId: string;
  existingAddonIds: string[];
}

@Component({
  selector: 'lib-add-addon-to-plan-dialog',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
    MatCheckboxModule,
    MatProgressSpinnerModule,
  ],
  templateUrl: `./medical-add-addon-plan-dialog.html`,
})
export class MedicalAddAddonPlanDialog implements OnInit {
  readonly dialogRef = inject(MatDialogRef<MedicalAddAddonPlanDialog>);
  readonly data = inject<DialogData>(MAT_DIALOG_DATA);
  readonly store = inject(AddonCatalogStore);
  private readonly feedback = inject(FeedbackService);

  readonly addonTypes = ADDON_TYPES;

  searchQuery = signal('');
  selectedType = signal('');
  selectedAddonIds = signal<Set<string>>(new Set());
  isLoading = signal(true);
  isSaving = signal(false);
  availableAddons = signal<Addon[]>([]);

  selectedCount = computed(() => this.selectedAddonIds().size);

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

  ngOnInit() {
    this.loadAvailableAddons();
  }

  private loadAvailableAddons() {
    this.isLoading.set(true);
    this.store.loadAvailableAddons(this.data.planId).subscribe({
      next: (res) => {
        // Filter out already configured addons
        const available = res.data.filter((a) => !this.data.existingAddonIds.includes(a.id));
        this.availableAddons.set(available);
        this.isLoading.set(false);
      },
      error: () => {
        this.feedback.error('Failed to load addons');
        this.isLoading.set(false);
      },
    });
  }

  onSearchInput(event: Event) {
    this.searchQuery.set((event.target as HTMLInputElement).value);
  }

  toggleSelection(id: string) {
    this.selectedAddonIds.update((ids) => {
      const newSet = new Set(ids);
      if (newSet.has(id)) {
        newSet.delete(id);
      } else {
        newSet.add(id);
      }
      return newSet;
    });
  }

  isSelected(id: string): boolean {
    return this.selectedAddonIds().has(id);
  }

  getAddonTypeLabel(value: string): string {
    return getLabelByValue(ADDON_TYPES, value);
  }

  getAddonTypeIcon(type: string): string {
    const icons: Record<string, string> = {
      rider: 'add_circle',
      top_up: 'trending_up',
      standalone: 'extension',
    };
    return icons[type] || 'extension';
  }

  getAddonTypeClass(type: string): string {
    const classes: Record<string, string> = {
      rider: 'bg-purple-100 text-purple-600',
      top_up: 'bg-blue-100 text-blue-600',
      standalone: 'bg-teal-100 text-teal-600',
    };
    return classes[type] || 'bg-slate-100 text-slate-600';
  }

  addSelected() {
    const ids = Array.from(this.selectedAddonIds());
    if (ids.length === 0) return;

    this.isSaving.set(true);

    // Add each addon with default 'optional' availability
    const requests = ids.map((addonId) =>
      this.store
        .configurePlanAddon(this.data.planId, {
          addon_id: addonId,
          availability: 'optional',
          is_active: true,
          sort_order: 0,
        })
        .toPromise()
    );

    Promise.all(requests)
      .then(() => {
        this.feedback.success(`Added ${ids.length} addon(s) to plan`);
        this.dialogRef.close(true);
      })
      .catch((err) => {
        this.feedback.error('Failed to add some addons');
        this.isSaving.set(false);
      });
  }
}
