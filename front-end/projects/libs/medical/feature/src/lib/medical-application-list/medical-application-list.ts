// libs/medical/ui/src/lib/applications/application-list.component.ts

import { Component, computed, effect, inject, OnInit, signal, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';

import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { MatInputModule } from '@angular/material/input';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatSortModule, MatSort } from '@angular/material/sort';
import { MatPaginatorModule, MatPaginator } from '@angular/material/paginator';
import { MatMenuModule } from '@angular/material/menu';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatChipsModule } from '@angular/material/chips';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { MatDrawer, MatSidenavModule } from '@angular/material/sidenav';

import {
  ApplicationStore,
  Application,
  APPLICATION_STATUSES,
  APPLICATION_TYPES,
  POLICY_TYPES,
  BILLING_FREQUENCIES,
  getLabelByValue,
  getStatusColor,
} from 'medical-data';

import { MedicalApplicationDialog } from '../dialogs/medical-application-dialog/medical-application-dialog';
import { MatDivider } from '@angular/material/divider';
import { FeedbackService } from 'shared';

@Component({
  selector: 'lib-application-list',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    FormsModule,
    MatButtonModule,
    MatIconModule,
    MatFormFieldModule,
    MatSelectModule,
    MatInputModule,
    MatTableModule,
    MatSortModule,
    MatPaginatorModule,
    MatMenuModule,
    MatTooltipModule,
    MatChipsModule,
    MatDialogModule,
    MatSnackBarModule,
    MatSidenavModule,
    MatDivider,
  ],
  templateUrl: './medical-application-list.html',
})
export class MedicalApplicationList implements OnInit {
  readonly store = inject(ApplicationStore);
  private readonly router = inject(Router);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  @ViewChild(MatSort) sort!: MatSort;
  @ViewChild(MatPaginator) paginator!: MatPaginator;
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  readonly applicationStatuses = APPLICATION_STATUSES;
  readonly applicationTypes = APPLICATION_TYPES;
  readonly policyTypes = POLICY_TYPES;

  readonly displayedColumns = [
    'status',
    'application_number',
    'applicant',
    'plan',
    'members',
    'premium',
    'created_at',
    'actions',
  ];

  dataSource = new MatTableDataSource<Application>([]);

  // Filters
  readonly searchQuery = signal('');
  readonly selectedStatus = signal('');
  readonly selectedType = signal('');
  readonly selectedPolicyType = signal('');

  // Stats computed
  readonly totalApplications = computed(() => this.store.stats()?.total ?? 0);
  readonly draftCount = computed(() => this.store.stats()?.draft ?? 0);
  readonly pendingUwCount = computed(() => this.store.stats()?.underwriting ?? 0);
  readonly approvedCount = computed(() => this.store.stats()?.approved ?? 0);
  readonly acceptedCount = computed(() => this.store.stats()?.accepted ?? 0);

  // Selected for drawer
  selectedApplication = signal<Application | null>(null);

  constructor() {
    // Watch for changes in the store and update the table
    effect(() => {
      const applications = this.store.applications();
      this.dataSource.data = applications;
    });
  }

  ngOnInit() {
    this.store.loadAll();
    this.store.loadStats();
  }

  private updateDataSource() {
    const applications = this.store.applications();
    this.dataSource.data = applications;

    if (this.sort) {
      this.dataSource.sort = this.sort;
    }
    if (this.paginator) {
      this.dataSource.paginator = this.paginator;
    }
  }

  ngAfterViewInit() {
    this.dataSource.sort = this.sort;
    this.dataSource.paginator = this.paginator;
  }

  applyFilter(event: Event) {
    const value = (event.target as HTMLInputElement).value.trim().toLowerCase();
    this.searchQuery.set(value);
    this.loadWithFilters();
  }

  filterByStatus(status: string) {
    this.selectedStatus.set(status);
    this.loadWithFilters();
  }

  filterByType(type: string) {
    this.selectedType.set(type);
    this.loadWithFilters();
  }

  filterByPolicyType(type: string) {
    this.selectedPolicyType.set(type);
    this.loadWithFilters();
  }

  clearFilters() {
    this.searchQuery.set('');
    this.selectedStatus.set('');
    this.selectedType.set('');
    this.selectedPolicyType.set('');
    // this.store.clearFilters();
    this.store.loadAll();
  }

  private loadWithFilters() {
    this.store.loadAll({
      search: this.searchQuery() || undefined,
      status: this.selectedStatus() || undefined,
      application_type: this.selectedType() || undefined,
      policy_type: this.selectedPolicyType() || undefined,
    });
  }

  openCreateDialog() {
    const dialogRef = this.dialog.open(MedicalApplicationDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      disableClose: true,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.store.loadAll();
        this.store.loadStats();
        this.feedback.success('Application created successfully');
      }
    });
  }

  viewDetails(application: Application) {
    this.selectedApplication.set(application);
    this.detailDrawer?.open();
  }

  navigateToDetail(application: Application) {
    this.router.navigate(['/applications', application.id]);
  }

  closeDrawer() {
    this.detailDrawer?.close();
    this.selectedApplication.set(null);
  }

  editApplication(application: Application) {
    const dialogRef = this.dialog.open(MedicalApplicationDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      disableClose: true,
      data: { application },
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.store.loadAll();
        this.feedback.success('Application updated successfully');
      }
    });
  }

  async calculatePremium(application: Application) {
    this.store.calculatePremium(application.id).subscribe({
      next: () => {
        this.feedback.success('Premium calculated successfully');
        this.store.loadAll();
      },
      error: (err) => {
        this.feedback.success(err.error?.message || 'Failed to calculate premium');
      },
    });
  }

  async markAsQuoted(application: Application) {
    this.store.markAsQuoted(application.id).subscribe({
      next: () => {
        this.feedback.success('Application marked as quoted');
        this.store.loadAll();
        this.store.loadStats();
      },
      error: (err) => {
        this.feedback.error(err.error?.message || 'Failed to mark as quoted');
      },
    });
  }

  async submitApplication(application: Application) {
    this.store.submit(application.id).subscribe({
      next: () => {
        this.feedback.success('Application submitted for underwriting');
        this.store.loadAll();
        this.store.loadStats();
      },
      error: (err) => {
        this.feedback.success(err.error?.message || 'Failed to submit application');
      },
    });
  }

  async deleteApplication(application: Application) {
    const confirmed = await this.feedback.confirm(
      'Delete Scheme?',
      `Are you sure you want to delete "${application.application_number}"? This action cannot be undone.`
    );

    if (!confirmed) return;

    this.store.delete(application.id).subscribe({
      next: () => {
        this.feedback.success('Application deleted');
        this.closeDrawer();
        this.store.loadStats();
      },
      error: (err) => {
        this.feedback.error(err.error?.message || 'Failed to delete application');
      },
    });
  }

  // Helper methods
  getStatusClass(status: string): string {
    const colorMap: Record<string, string> = {
      draft: 'bg-slate-100 text-slate-700 border-slate-200',
      quoted: 'bg-blue-50 text-blue-700 border-blue-200',
      submitted: 'bg-indigo-50 text-indigo-700 border-indigo-200',
      underwriting: 'bg-amber-50 text-amber-700 border-amber-200',
      approved: 'bg-green-50 text-green-700 border-green-200',
      declined: 'bg-red-50 text-red-700 border-red-200',
      referred: 'bg-orange-50 text-orange-700 border-orange-200',
      accepted: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      converted: 'bg-teal-50 text-teal-700 border-teal-200',
      expired: 'bg-gray-100 text-gray-600 border-gray-200',
      cancelled: 'bg-red-100 text-red-700 border-red-200',
    };
    return colorMap[status] || 'bg-slate-100 text-slate-700 border-slate-200';
  }

  getStatusLabel(status: string): string {
    return getLabelByValue(APPLICATION_STATUSES, status);
  }

  getTypeLabel(type: string): string {
    return getLabelByValue(APPLICATION_TYPES, type);
  }

  getPolicyTypeLabel(type: string): string {
    return getLabelByValue(POLICY_TYPES, type);
  }

  getBillingLabel(frequency: string): string {
    return getLabelByValue(BILLING_FREQUENCIES, frequency);
  }

  formatCurrency(amount: number, currency: string = 'ZMW'): string {
    return new Intl.NumberFormat('en-ZM', {
      style: 'currency',
      currency,
      minimumFractionDigits: 2,
    }).format(amount);
  }

  formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-ZM', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  }

  canEdit(application: Application): boolean {
    return application.can_be_edited ?? application.status === 'draft';
  }

  canSubmit(application: Application): boolean {
    return application.can_be_submitted ?? application.status === 'quoted';
  }

  canConvert(application: Application): boolean {
    return application.can_be_converted ?? application.status === 'accepted';
  }

  trackById(index: number, item: Application): string {
    return item.id;
  }
}
