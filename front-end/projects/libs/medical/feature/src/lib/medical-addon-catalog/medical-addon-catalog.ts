// libs/medical/ui/src/lib/addons/medical-addons-catalog.ts

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
import { MatChipsModule } from '@angular/material/chips';
import { MatExpansionModule } from '@angular/material/expansion';

// Domain/Shared Imports
import { Addon, AddonCatalogStore, ADDON_TYPES, getLabelByValue } from 'medical-data';
import { MedicalAddonCatalogDialog } from '../dialogs/medical-addon-catalog-dialog/medical-addon-catalog-dialog';
import { FeedbackService, PageHeaderComponent } from 'shared';

@Component({
  selector: 'lib-medical-addons-catalog',
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
    MatExpansionModule,
    PageHeaderComponent,
  ],
  templateUrl: './medical-addon-catalog.html',
})
export class MedicalAddonsCatalog implements OnInit, AfterViewInit {
  readonly store = inject(AddonCatalogStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  displayedColumns = ['status', 'name', 'type', 'benefits', 'plans', 'actions'];
  dataSource = new MatTableDataSource<Addon>([]);

  @ViewChild(MatPaginator) paginator!: MatPaginator;
  @ViewChild(MatSort) sort!: MatSort;
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  // Filters
  selectedType = signal<string>('');
  searchQuery = signal<string>('');

  // Detail View
  selectedAddon = signal<Addon | null>(null);

  // Constants
  readonly addonTypes = ADDON_TYPES;

  // KPIs
  totalAddons = computed(() => this.store.addons()?.length || 0);
  activeAddons = computed(() => this.store.addons()?.filter((a) => a.is_active).length || 0);
  riderAddons = computed(
    () => this.store.addons()?.filter((a) => a.addon_type === 'rider').length || 0
  );

  constructor() {
    effect(() => {
      const addons = this.store.addons();
      if (Array.isArray(addons)) {
        this.dataSource.data = addons;
      }
    });
  }

  ngOnInit() {
    this.store.loadAll();

    this.dataSource.filterPredicate = (data: Addon, filter: string) => {
      const searchStr = filter.toLowerCase();
      const name = data.name?.toLowerCase() || '';
      const code = data.code?.toLowerCase() || '';
      const desc = data.description?.toLowerCase() || '';
      return name.includes(searchStr) || code.includes(searchStr) || desc.includes(searchStr);
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

  filterByType(type: string) {
    this.selectedType.set(type);
    this.applyFilters();
  }

  private applyFilters() {
    let filtered = this.store.addons() || [];

    if (this.selectedType()) {
      filtered = filtered.filter((a) => a.addon_type === this.selectedType());
    }

    this.dataSource.data = filtered;
  }

  clearFilters() {
    this.selectedType.set('');
    this.searchQuery.set('');
    this.dataSource.filter = '';
    this.dataSource.data = this.store.addons() || [];
  }

  // Labels
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

  // Drawer
  viewDetails(addon: Addon) {
    this.store.loadOne(addon.id).subscribe({
      next: () => {
        this.selectedAddon.set(this.store.selectedAddon());
        this.detailDrawer.open();
      },
      error: () => this.feedback.error('Failed to load addon details'),
    });
  }

  closeDrawer() {
    this.detailDrawer.close();
    this.selectedAddon.set(null);
  }

  // Dialog
  openCreateDialog(addon?: Addon) {
    const dialogRef = this.dialog.open(MedicalAddonCatalogDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      data: addon ? { ...addon } : null,
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      const request$ = addon ? this.store.update(addon.id, result) : this.store.create(result);

      request$.subscribe({
        next: () => {
          this.feedback.success(`Addon ${addon ? 'updated' : 'created'} successfully`);
          if (this.selectedAddon()?.id === addon?.id) {
            this.selectedAddon.set({ ...this.selectedAddon()!, ...result });
          }
        },
        error: (err) =>
          this.feedback.error(
            err?.error?.message ?? `Failed to ${addon ? 'update' : 'create'} addon`
          ),
      });
    });
  }

  async toggleStatus(addon: Addon) {
    const action = addon.is_active ? 'deactivate' : 'activate';

    const confirmed = await this.feedback.confirm(
      `${action.charAt(0).toUpperCase() + action.slice(1)} Addon?`,
      addon.is_active
        ? 'This addon will no longer be available for new configurations.'
        : 'This addon will become available for plan configuration.'
    );

    if (!confirmed) return;

    this.store.update(addon.id, { is_active: !addon.is_active }).subscribe({
      next: () => {
        this.feedback.success(`Addon ${action}d successfully`);
        if (this.selectedAddon()?.id === addon.id) {
          this.selectedAddon.set({ ...this.selectedAddon()!, is_active: !addon.is_active });
        }
      },
      error: (err) => this.feedback.error(err?.error?.message ?? `Failed to ${action} addon`),
    });
  }

  async deleteAddon(addon: Addon) {
    if (addon.is_active) {
      this.feedback.error('Cannot delete an active addon. Deactivate it first.');
      return;
    }

    const confirmed = await this.feedback.confirm(
      'Delete Addon?',
      `Are you sure you want to delete "${addon.name}"? This action cannot be undone.`
    );

    if (!confirmed) return;

    this.store.delete(addon.id).subscribe({
      next: () => {
        this.feedback.success('Addon deleted successfully');
        if (this.selectedAddon()?.id === addon.id) {
          this.closeDrawer();
        }
      },
      error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to delete addon'),
    });
  }
}
