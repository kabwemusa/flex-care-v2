// libs/medical/ui/src/lib/benefits/benefit-catalog.component.ts

import {
  Component,
  OnInit,
  ViewChild,
  inject,
  effect,
  computed,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';

// Material Imports
import { MatTableDataSource, MatTableModule } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatSort, MatSortModule } from '@angular/material/sort';
import { MatDialog } from '@angular/material/dialog';
import { MatMenuModule } from '@angular/material/menu';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatDividerModule } from '@angular/material/divider';
import { MatSelectModule } from '@angular/material/select';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatDrawer, MatSidenavModule } from '@angular/material/sidenav';
import { MatChipsModule } from '@angular/material/chips';
import { MatTabsModule } from '@angular/material/tabs';
import { MatTreeModule, MatTreeNestedDataSource } from '@angular/material/tree';
import { NestedTreeControl } from '@angular/cdk/tree';

// Domain/Shared Imports
import {
  Benefit,
  BenefitStore,
  BENEFIT_TYPES,
  LIMIT_TYPES,
  getLabelByValue,
} from 'medical-data';
import { MedicalBenefitsCatalogDialog } from '../dialogs/medical-benefits-catalog-dialog/medical-benefits-catalog-dialog';
import { FeedbackService, PageHeaderComponent } from 'shared';

interface BenefitNode {
  id: string;
  name: string;
  code: string;
  benefit_type: string;
  is_active: boolean;
  children?: BenefitNode[];
  isTypeGroup?: boolean;
  icon?: string;
}

@Component({
  selector: 'lib-medical-benefits-catalog',
  standalone: true,
  imports: [
    CommonModule,
    MatTableModule,
    MatPaginatorModule,
    MatSortModule,
    MatMenuModule,
    MatIconModule,
    MatButtonModule,
    MatTooltipModule,
    MatDividerModule,
    MatSelectModule,
    MatFormFieldModule,
    MatSidenavModule,
    MatChipsModule,
    MatTabsModule,
    MatTreeModule,
    PageHeaderComponent,
  ],
  templateUrl: './medical-benefits-catalog.html',
})
export class MedicalBenefitsCatalog implements OnInit {
  readonly store = inject(BenefitStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  // Table view
  displayedColumns = ['status', 'name', 'type', 'defaults', 'actions'];
  dataSource = new MatTableDataSource<Benefit>([]);

  // Setter pattern: safe for deferred rendering (@if blocks)
  @ViewChild(MatPaginator) set paginator(p: MatPaginator) {
    if (p) this.dataSource.paginator = p;
  }
  @ViewChild(MatSort) set matSort(s: MatSort) {
    if (s) this.dataSource.sort = s;
  }
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  // Tree view
  treeControl = new NestedTreeControl<BenefitNode>((node) => node.children);
  treeDataSource = new MatTreeNestedDataSource<BenefitNode>();

  // State
  selectedBenefit = signal<Benefit | null>(null);
  selectedType = signal<string>('');
  searchQuery = signal<string>('');
  viewMode = signal<'table' | 'tree'>('table');

  // Constants
  benefitTypes = BENEFIT_TYPES;
  limitTypes = LIMIT_TYPES;

  // Computed
  totalBenefits = computed(() => this.store.benefits().length);
  totalTypes = computed(() => {
    const types = new Set(this.store.benefits().map((b) => b.benefit_type));
    return types.size;
  });
  activeBenefits = computed(() => this.store.benefits().filter((b) => b.is_active).length);

  hasChild = (_: number, node: BenefitNode) => !!node.children && node.children.length > 0;

  constructor() {
    // Sync benefits to table (respecting type filter)
    effect(() => {
      const all = this.store.benefits();
      const type = this.selectedType();
      this.dataSource.data = type ? all.filter((b) => b.benefit_type === type) : all;
    });

    // Build tree data grouped by benefit_type
    effect(() => {
      const benefits = this.store.benefits();
      this.treeDataSource.data = this.buildTreeData(benefits);
    });
  }

  ngOnInit() {
    this.store.loadBenefits();

    // Custom filter
    this.dataSource.filterPredicate = (data: Benefit, filter: string) => {
      const searchStr = filter.toLowerCase();
      const name = data.name?.toLowerCase() || '';
      const code = data.code?.toLowerCase() || '';
      const type = data.benefit_type?.toLowerCase() || '';
      return name.includes(searchStr) || code.includes(searchStr) || type.includes(searchStr);
    };
  }

  private buildTreeData(benefits: Benefit[]): BenefitNode[] {
    const rootBenefits = benefits.filter((b) => !b.parent_id);
    const grouped = new Map<string, Benefit[]>();

    for (const b of rootBenefits) {
      const type = b.benefit_type;
      if (!grouped.has(type)) grouped.set(type, []);
      grouped.get(type)!.push(b);
    }

    return Array.from(grouped.entries()).map(([type, typeBenefits]) => ({
      id: type,
      name: typeBenefits[0]?.benefit_type_label || type,
      code: '',
      benefit_type: type,
      is_active: true,
      isTypeGroup: true,
      icon: this.getBenefitTypeIcon(type),
      children: this.buildBenefitNodes(typeBenefits),
    }));
  }

  private buildBenefitNodes(benefits: Benefit[]): BenefitNode[] {
    return benefits.map((b) => ({
      id: b.id,
      name: b.name,
      code: b.code,
      benefit_type: b.benefit_type,
      is_active: b.is_active,
      children: b.children ? this.buildBenefitNodes(b.children) : undefined,
    }));
  }

  // Filters
  applyFilter(event: Event) {
    const filterValue = (event.target as HTMLInputElement).value;
    this.searchQuery.set(filterValue);
    this.dataSource.filter = filterValue.trim().toLowerCase();

    if (this.dataSource.paginator) {
      this.dataSource.paginator.firstPage();
    }
  }

  filterByType(type: string) {
    this.selectedType.set(type);
    this.applyLocalFilters();
  }

  clearFilters() {
    this.selectedType.set('');
    this.searchQuery.set('');
    this.dataSource.filter = '';
    this.applyLocalFilters();
  }

  private applyLocalFilters() {
    const type = this.selectedType();
    const all = this.store.benefits();
    this.dataSource.data = type ? all.filter((b) => b.benefit_type === type) : all;

    if (this.dataSource.paginator) {
      this.dataSource.paginator.firstPage();
    }
  }

  toggleViewMode() {
    this.viewMode.update((m) => (m === 'table' ? 'tree' : 'table'));
  }

  // Labels
  getBenefitTypeLabel(value: string): string {
    return getLabelByValue(BENEFIT_TYPES, value);
  }

  getBenefitTypeIcon(value: string): string {
    return BENEFIT_TYPES.find((t) => t.value === value)?.icon || 'medical_services';
  }

  getLimitTypeLabel(value: string): string {
    return getLabelByValue(LIMIT_TYPES, value);
  }

  // Drawer
  viewDetails(benefit: Benefit) {
    this.store.loadOneBenefit(benefit.id).subscribe({
      next: () => {
        this.selectedBenefit.set(this.store.selectedBenefit());
        this.detailDrawer.open();
      },
      error: () => this.feedback.error('Failed to load benefit details'),
    });
  }

  closeDrawer() {
    this.detailDrawer.close();
    this.selectedBenefit.set(null);
  }

  // Dialogs
  openBenefitDialog(benefit?: Benefit, parentId?: string) {
    const dialogRef = this.dialog.open(MedicalBenefitsCatalogDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: benefit ? { ...benefit } : { parent_id: parentId },
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      const request$ = benefit
        ? this.store.updateBenefit(benefit.id, result)
        : this.store.createBenefit(result);

      request$.subscribe({
        next: () => {
          this.feedback.success(`Benefit ${benefit ? 'updated' : 'created'} successfully`);
          if (this.selectedBenefit()?.id === benefit?.id) {
            this.selectedBenefit.set({ ...this.selectedBenefit()!, ...result });
          }
        },
        error: (err) =>
          this.feedback.error(
            err?.error?.message ?? `Failed to ${benefit ? 'update' : 'create'} benefit`
          ),
      });
    });
  }

  addSubBenefit(parentBenefit: Benefit) {
    this.openBenefitDialog(undefined, parentBenefit.id);
  }

  async deleteBenefit(benefit: Benefit) {
    if (benefit.has_children) {
      this.feedback.error('Cannot delete a benefit with sub-benefits. Remove sub-benefits first.');
      return;
    }

    const confirmed = await this.feedback.confirm(
      'Delete Benefit?',
      `Are you sure you want to delete "${benefit.name}"? This will also remove it from any plans where it's configured.`
    );

    if (!confirmed) return;

    this.store.deleteBenefit(benefit.id).subscribe({
      next: () => {
        this.feedback.success('Benefit deleted successfully');
        if (this.selectedBenefit()?.id === benefit.id) {
          this.closeDrawer();
        }
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to delete benefit'),
    });
  }

  // Tree node click
  onTreeNodeClick(node: BenefitNode) {
    if (node.isTypeGroup) return;

    const benefit = this.store.benefits().find((b) => b.id === node.id);
    if (benefit) {
      this.viewDetails(benefit);
    }
  }

  // Export
  exportToCsv() {
    const dataToExport = this.dataSource.filteredData.length
      ? this.dataSource.filteredData
      : this.store.benefits();

    if (dataToExport.length === 0) {
      this.feedback.error('No data to export');
      return;
    }

    const headers = ['Code', 'Name', 'Type', 'Limit Type', 'Preauth', 'Status'];
    const rows = dataToExport.map((b) => [
      b.code,
      `"${b.name}"`,
      this.getBenefitTypeLabel(b.benefit_type),
      this.getLimitTypeLabel(b.default_limit_type || ''),
      b.requires_preauth ? 'Yes' : 'No',
      b.is_active ? 'Active' : 'Inactive',
    ]);

    const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    link.setAttribute('href', url);
    link.setAttribute('download', `benefit_catalog_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }
}
