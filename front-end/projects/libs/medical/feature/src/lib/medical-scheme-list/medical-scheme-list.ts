// libs/medical/ui/src/lib/schemes/medical-schemes.component.ts

import {
  Component,
  OnInit,
  AfterViewInit,
  ViewChild,
  inject,
  effect,
  computed,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

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

// Domain/Shared Imports
import { MedicalScheme, SchemeListStore, MARKET_SEGMENTS, getLabelByValue } from 'medical-data';
import { MedicalSchemeDialog } from '../dialogs/medical-scheme-dialog/medical-scheme-dialog';
import { FeedbackService, PageHeaderComponent } from 'shared';

@Component({
  selector: 'lib-medical-schemes',
  standalone: true,
  imports: [
    CommonModule,
    RouterLink,
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
    PageHeaderComponent,
  ],
  templateUrl: './medical-scheme-list.html',
  // styleUrls: ['./medical-scheme-list.scss'],
})
export class MedicalSchemesList implements OnInit, AfterViewInit {
  readonly store = inject(SchemeListStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  displayedColumns = ['status', 'name', 'segment', 'plans', 'effective', 'actions'];
  dataSource = new MatTableDataSource<MedicalScheme>([]);

  @ViewChild(MatPaginator) paginator!: MatPaginator;
  @ViewChild(MatSort) sort!: MatSort;
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  // Filters
  selectedSegment = signal<string>('');
  searchQuery = signal<string>('');

  // Detail View
  selectedScheme = signal<MedicalScheme | null>(null);

  // Constants
  marketSegments = MARKET_SEGMENTS;

  // Computed KPIs
  totalSchemes = computed(() => this.store.schemes().length);
  activeSchemes = computed(() => this.store.schemes().filter((s) => s.is_active).length);
  totalPlans = computed(() =>
    this.store.schemes().reduce((sum, s) => sum + (s.plans_count || 0), 0)
  );

  constructor() {
    effect(() => {
      this.dataSource.data = this.store.schemes();
    });
  }

  ngOnInit() {
    this.store.loadAll();

    // Custom filter predicate for frontend filtering
    this.dataSource.filterPredicate = (data: MedicalScheme, filter: string) => {
      const searchStr = filter.toLowerCase();
      const name = data.name?.toLowerCase() || '';
      const code = data.code?.toLowerCase() || '';
      const segment = data.market_segment?.toLowerCase() || '';
      const status = data.is_active ? 'active' : 'inactive';

      return (
        name.includes(searchStr) ||
        code.includes(searchStr) ||
        segment.includes(searchStr) ||
        status.includes(searchStr)
      );
    };
  }

  ngAfterViewInit() {
    this.dataSource.paginator = this.paginator;
    this.dataSource.sort = this.sort;
  }

  applyFilter(event: Event) {
    const filterValue = (event.target as HTMLInputElement).value;
    this.searchQuery.set(filterValue);
    this.dataSource.filter = filterValue.trim().toLowerCase();

    if (this.dataSource.paginator) {
      this.dataSource.paginator.firstPage();
    }
  }

  filterBySegment(segment: string) {
    this.selectedSegment.set(segment);
    if (segment) {
      // Filter locally for instant response
      this.dataSource.data = this.store.schemes().filter((s) => s.market_segment === segment);
    } else {
      this.dataSource.data = this.store.schemes();
    }
  }

  clearFilters() {
    this.selectedSegment.set('');
    this.searchQuery.set('');
    this.dataSource.filter = '';
    this.dataSource.data = this.store.schemes();
  }

  getSegmentLabel(value: string): string {
    return getLabelByValue(MARKET_SEGMENTS, value);
  }

  getSegmentIcon(value: string): string {
    return MARKET_SEGMENTS.find((s) => s.value === value)?.icon || 'category';
  }

  getSegmentClass(value: string): string {
    const classes: Record<string, string> = {
      corporate: 'bg-blue-50 text-blue-700 border-blue-200',
      sme: 'bg-purple-50 text-purple-700 border-purple-200',
      individual: 'bg-green-50 text-green-700 border-green-200',
      family: 'bg-amber-50 text-amber-700 border-amber-200',
      senior: 'bg-rose-50 text-rose-700 border-rose-200',
    };
    return classes[value] || 'bg-slate-50 text-slate-700 border-slate-200';
  }

  // Detail Drawer
  viewDetails(scheme: MedicalScheme) {
    this.store.loadOne(scheme.id).subscribe({
      next: () => {
        this.selectedScheme.set(this.store.selectedScheme());
        this.detailDrawer.open();
      },
      error: () => this.feedback.error('Failed to load scheme details'),
    });
  }

  closeDrawer() {
    this.detailDrawer.close();
    this.selectedScheme.set(null);
  }

  // Dialog Actions
  openCreateDialog(scheme?: MedicalScheme) {
    const dialogRef = this.dialog.open(MedicalSchemeDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: scheme ? { ...scheme } : null,
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      const request$ = scheme ? this.store.update(scheme.id, result) : this.store.create(result);

      request$.subscribe({
        next: () => {
          this.feedback.success(`Scheme ${scheme ? 'updated' : 'created'} successfully`);
          if (this.selectedScheme()?.id === scheme?.id) {
            this.selectedScheme.set({ ...this.selectedScheme()!, ...result });
          }
        },
        error: (err) =>
          this.feedback.error(
            err?.error?.message ?? `Failed to ${scheme ? 'update' : 'create'} scheme`
          ),
      });
    });
  }

  async toggleStatus(scheme: MedicalScheme) {
    const action = scheme.is_active ? 'Deactivate' : 'Activate';

    const confirmed = await this.feedback.confirm(
      `${action} Scheme?`,
      scheme.is_active
        ? 'This scheme will no longer be available for new policies.'
        : 'This scheme will become available for policy creation.'
    );

    if (!confirmed) return;

    this.store.activate(scheme.id).subscribe({
      next: () => this.feedback.success(`Scheme ${action.toLowerCase()}d successfully`),
      error: (err) => {
        this.feedback.error(err?.error?.message ?? 'Failed to update scheme status');
      },
    });
  }

  async deleteScheme(scheme: MedicalScheme) {
    if (scheme.is_active) {
      this.feedback.error('Cannot delete an active scheme. Please deactivate it first.');
      return;
    }

    if ((scheme.plans_count || 0) > 0) {
      this.feedback.error(
        'Cannot delete a scheme with linked plans. Please remove all plans first.'
      );
      return;
    }

    const confirmed = await this.feedback.confirm(
      'Delete Scheme?',
      `Are you sure you want to delete "${scheme.name}"? This action cannot be undone.`
    );

    if (!confirmed) return;

    this.store.delete(scheme.id).subscribe({
      next: () => {
        this.feedback.success('Scheme deleted successfully');
        if (this.selectedScheme()?.id === scheme.id) {
          this.closeDrawer();
        }
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to delete scheme'),
    });
  }

  // Add this to your component class
  getSegmentDetails(value: string) {
    return this.marketSegments.find((s) => s.value === value);
  }

  exportToCsv() {
    const dataToExport = this.dataSource.filteredData.length
      ? this.dataSource.filteredData
      : this.store.schemes();

    if (dataToExport.length === 0) {
      this.feedback.error('No data to export');
      return;
    }

    const headers = ['Code', 'Name', 'Market Segment', 'Plans', 'Effective From', 'Status'];
    const rows = dataToExport.map((s) => [
      s.code,
      `"${s.name}"`,
      this.getSegmentLabel(s.market_segment),
      s.plans_count || 0,
      s.effective_from || '',
      s.is_active ? 'Active' : 'Inactive',
    ]);

    const csvContent = [headers.join(','), ...rows.map((r) => r.join(','))].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    link.setAttribute('href', url);
    link.setAttribute('download', `medical_schemes_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }
}
