// libs/medical/ui/src/lib/dialogs/add-benefits-to-plan-dialog/add-benefits-to-plan-dialog.component.ts

import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCheckboxModule, MatCheckboxChange } from '@angular/material/checkbox';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

import { Benefit, BenefitStore, BENEFIT_TYPES, getLabelByValue } from 'medical-data';
import { FeedbackService } from 'shared';

interface DialogData {
  planId: string;
  existingBenefitIds: string[];
  parentPlanBenefitId?: string;
  parentBenefitId?: string;
  categoryId?: string;
}

@Component({
  selector: 'lib-add-benefits-to-plan-dialog',
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
    MatChipsModule,
    MatProgressSpinnerModule,
  ],
  templateUrl: './medical-add-plan-benefits-dialog.html',
})
export class MedicalAddPlanBenefitsDialog implements OnInit {
  readonly dialogRef = inject(MatDialogRef<MedicalAddPlanBenefitsDialog>);
  readonly data = inject<DialogData>(MAT_DIALOG_DATA);
  readonly store = inject(BenefitStore);
  private readonly feedback = inject(FeedbackService);

  // State
  searchQuery = signal('');
  selectedCategory = signal<string>(this.data.categoryId || '');
  isSaving = signal(false);

  // Selection - using signal for proper reactivity
  selectedBenefitIds = signal<Set<string>>(new Set());

  // Available benefits (filtered)
  availableBenefits = computed(() => {
    let benefits = this.store.benefits();

    // Filter out already added
    benefits = benefits.filter((b) => !this.data.existingBenefitIds.includes(b.id));

    // Filter by parent if adding sub-benefits
    if (this.data.parentBenefitId) {
      benefits = benefits.filter((b) => b.parent_id === this.data.parentBenefitId);
    } else {
      // Only root benefits
      benefits = benefits.filter((b) => !b.parent_id);
    }

    // Filter by category
    const categoryId = this.selectedCategory();
    if (categoryId) {
      benefits = benefits.filter((b) => b.category_id === categoryId);
    }

    // Filter by search
    const query = this.searchQuery().toLowerCase();
    if (query) {
      benefits = benefits.filter(
        (b) => b.name.toLowerCase().includes(query) || b.code.toLowerCase().includes(query)
      );
    }

    return benefits;
  });

  selectedCount = computed(() => this.selectedBenefitIds().size);

  isAddingSubBenefits = computed(() => !!this.data.parentPlanBenefitId);

  ngOnInit() {
    this.store.loadCategories();
    this.store.loadBenefits();
  }

  onSearchInput(event: Event) {
    const value = (event.target as HTMLInputElement).value;
    this.searchQuery.set(value);
  }

  onCategoryChange(categoryId: string) {
    this.selectedCategory.set(categoryId);
  }

  getBenefitTypeIcon(value: string): string {
    return BENEFIT_TYPES.find((t) => t.value === value)?.icon || 'medical_services';
  }

  getCategoryName(categoryId: string): string {
    return this.store.categories().find((c) => c.id === categoryId)?.name || '';
  }

  toggleSelection(benefitId: string, event?: MatCheckboxChange) {
    this.selectedBenefitIds.update((ids) => {
      const newSet = new Set(ids);
      if (newSet.has(benefitId)) {
        newSet.delete(benefitId);
      } else {
        newSet.add(benefitId);
      }
      return newSet;
    });
  }

  isSelected(benefitId: string): boolean {
    return this.selectedBenefitIds().has(benefitId);
  }

  selectAll() {
    const allIds = this.availableBenefits().map((b) => b.id);
    this.selectedBenefitIds.set(new Set(allIds));
  }

  clearSelection() {
    this.selectedBenefitIds.set(new Set());
  }

  async addSelected() {
    if (this.selectedCount() === 0) return;

    this.isSaving.set(true);

    const benefitsToAdd = Array.from(this.selectedBenefitIds()).map((benefitId) => ({
      benefit_id: benefitId,
      parent_plan_benefit_id: this.data.parentPlanBenefitId || null,
      is_covered: true,
    }));

    this.store.bulkAddBenefits(this.data.planId, benefitsToAdd).subscribe({
      next: (res) => {
        this.isSaving.set(false);
        this.dialogRef.close({ added: res.data?.added_count || benefitsToAdd.length });
      },
      error: (err) => {
        this.isSaving.set(false);
        this.feedback.error(err?.error?.message ?? 'Failed to add benefits');
      },
    });
  }
}
