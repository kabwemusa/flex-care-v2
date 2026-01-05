// libs/medical/ui/src/lib/applications/application-detail.component.ts

import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';

import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatTabsModule } from '@angular/material/tabs';
import { MatTableModule, MatTableDataSource } from '@angular/material/table';
import { MatMenuModule } from '@angular/material/menu';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatChipsModule } from '@angular/material/chips';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatDividerModule } from '@angular/material/divider';

import {
  ApplicationStore,
  Application,
  ApplicationMember,
  ApplicationAddon,
  APPLICATION_STATUSES,
  UNDERWRITING_STATUSES,
  MEMBER_TYPES,
  RELATIONSHIPS,
  BILLING_FREQUENCIES,
  getLabelByValue,
} from 'medical-data';

import { MedicalApplicationDialog } from '../dialogs/medical-application-dialog/medical-application-dialog';
import { MedicalApplicationMemberDialog } from '../dialogs/medical-application-member-dialog/medical-application-member-dialog';
import { MedicalUnderwritingDialog } from '../dialogs/medical-underwriting-dialog/medical-underwriting-dialog';
import { MedicalApplicationAddonDialog } from '../dialogs/medical-application-addon-dialog/medical-application-addon-dialog';
import { MedicalApplicationReferralDialog } from '../dialogs/medical-application-referral-dialog/medical-application-referral-dialog';
import { MedicalQuoteActionsDialog } from '../dialogs/medical-quote-actions-dialog/medical-quote-actions-dialog';
import { ApplicationDocumentsComponent } from '../components/application-documents/application-documents';
import { FeedbackService } from 'shared';

@Component({
  selector: 'lib-application-detail',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    MatButtonModule,
    MatIconModule,
    MatTabsModule,
    MatTableModule,
    MatMenuModule,
    MatTooltipModule,
    MatChipsModule,
    MatDialogModule,
    MatSnackBarModule,
    MatExpansionModule,
    MatDividerModule,
    ApplicationDocumentsComponent,
  ],
  templateUrl: './medical-application-detail.html',
})
export class MedicalApplicationDetail implements OnInit {
  readonly store = inject(ApplicationStore);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly dialog = inject(MatDialog);
  private readonly snackBar = inject(MatSnackBar);
  private readonly feedback = inject(FeedbackService);

  readonly applicationId = signal<string>('');

  readonly application = computed(() => this.store.selectedApplication());
  readonly members = computed(() => this.store.members());
  readonly addons = computed(() => this.store.addons());
  readonly isLoading = computed(() => this.store.isLoading());
  readonly isSaving = computed(() => this.store.isSaving());

  // Computed for members table
  readonly principalMembers = computed(() => this.members().filter((m) => m.is_principal));
  readonly dependentMembers = computed(() => this.members().filter((m) => m.is_dependent));
  readonly pendingUwMembers = computed(() =>
    this.members().filter((m) => m.underwriting_status === 'pending')
  );

  memberDataSource = new MatTableDataSource<ApplicationMember>([]);
  memberDisplayedColumns = [
    'member_type',
    'name',
    'dob',
    'gender',
    'premium',
    'uw_status',
    'actions',
  ];

  addonDataSource = new MatTableDataSource<ApplicationAddon>([]);
  addonDisplayedColumns = ['addon', 'premium', 'actions'];

  ngOnInit() {
    this.route.params.subscribe((params) => {
      this.applicationId.set(params['id']);
      this.loadApplication();
    });
  }

  private loadApplication() {
    this.store.loadOne(this.applicationId()).subscribe({
      next: () => {
        this.memberDataSource.data = this.store.members();
        this.addonDataSource.data = this.store.addons();
      },
    });
  }

  goBack() {
    this.router.navigate(['/applications']);
  }

  editApplication() {
    const app = this.application();
    if (!app) return;

    const dialogRef = this.dialog.open(MedicalApplicationDialog, {
      width: '70vw',
      maxHeight: '90vh',
      disableClose: true,
      data: { application: app },
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.loadApplication();
        this.snackBar.open('Application updated successfully', 'Close', { duration: 3000 });
      }
    });
  }

  // =========================================================================
  // WORKFLOW ACTIONS
  // =========================================================================

  calculatePremium() {
    const app = this.application();
    if (!app) return;

    this.store.calculatePremium(app.id).subscribe({
      next: () => {
        this.feedback.success('Premium calculated successfully');
        this.loadApplication();
      },
      error: (err) => {
        this.feedback.error(err?.error?.message || 'Failed to calculate premium');
      },
    });
  }

  generateQuote() {
    const app = this.application();
    if (!app) return;

    this.store.markAsQuoted(app.id).subscribe({
      next: () => {
        this.feedback.success('Quote generated successfully');
        this.loadApplication();
      },
      error: (err) => {
        this.feedback.error(err?.error?.message || 'Failed to generate quote');
      },
    });
  }

  openQuoteActions() {
    const app = this.application();
    if (!app) return;

    this.dialog.open(MedicalQuoteActionsDialog, {
      width: '70vw',
      maxHeight: '90vh',
      data: {
        applicationId: app.id,
        applicationNumber: app.application_number,
        contactEmail: app.contact_email,
      },
    });
  }

  submitForUnderwriting() {
    const app = this.application();
    if (!app) return;

    this.store.submit(app.id).subscribe({
      next: () => {
        this.feedback.success('Application submitted for underwriting');
        this.loadApplication();
      },
      error: (err) => {
        this.feedback.error(err?.error?.message || 'Failed to submit application');
      },
    });
  }

  startUnderwriting() {
    const app = this.application();
    if (!app) return;

    this.store.startUnderwriting(app.id).subscribe({
      next: () => {
        this.feedback.success('Underwriting started');
        this.loadApplication();
      },
      error: (err) => {
        this.feedback.error(err?.error?.message || 'Failed to start underwriting');
      },
    });
  }

  async referApplication() {
    const app = this.application();
    if (!app) return;

    const dialogRef = this.dialog.open(MedicalApplicationReferralDialog, {
      width: '600px',
      maxHeight: '90vh',
      disableClose: true,
      data: { applicationNumber: app.application_number },
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      this.store.refer(app.id, result.reason, result.underwriter_id).subscribe({
        next: () => {
          this.feedback.success('Application referred for review');
          this.loadApplication();
        },
        error: (err) => {
          this.feedback.error(err?.error?.message || 'Failed to refer application');
        },
      });
    });
  }

  async approveApplication() {
    const app = this.application();
    if (!app) return;

    const confirmed = await this.feedback.confirm(
      'Approve Application?',
      'This application will be approved and ready for customer acceptance.'
    );

    if (!confirmed) return;

    this.store.approve(app.id).subscribe({
      next: () => {
        this.feedback.success('Application approved');
        this.loadApplication();
      },
      error: (err) => {
        this.feedback.error(err?.error?.message || 'Failed to approve application');
      },
    });
  }

  async declineApplication() {
    const app = this.application();
    if (!app) return;

    const confirmed = await this.feedback.confirm(
      'Decline Application?',
      'Please provide a reason for declining this application.'
    );

    if (!confirmed) return;

    const reason = prompt('Please provide a reason for declining:');
    if (!reason) return;

    this.store.decline(app.id, reason).subscribe({
      next: () => {
        this.feedback.success('Application declined');
        this.loadApplication();
      },
      error: (err) => {
        this.feedback.error(err?.error?.message || 'Failed to decline application');
      },
    });
  }

  async acceptApplication() {
    const app = this.application();
    if (!app) return;

    const confirmed = await this.feedback.confirm(
      'Record Customer Acceptance?',
      'Has the customer accepted this quote?'
    );

    if (!confirmed) return;

    this.store.accept(app.id).subscribe({
      next: () => {
        this.feedback.success('Application marked as accepted');
        this.loadApplication();
      },
      error: (err) => {
        this.feedback.error(err?.error?.message || 'Failed to accept application');
      },
    });
  }

  async convertToPolicy() {
    const app = this.application();
    if (!app) return;

    const confirmed = await this.feedback.confirm(
      'Convert to Policy?',
      'This application will be converted to an active policy. This action cannot be undone.'
    );

    if (!confirmed) return;

    this.store.convert(app.id).subscribe({
      next: (res) => {
        this.feedback.success('Policy created successfully!');
        // Navigate to the policy detail page
        if (res.data?.id) {
          this.router.navigate(['/medical/policies', res.data.id]);
        } else {
          this.loadApplication();
        }
      },
      error: (err) => {
        this.feedback.error(err?.error?.message || 'Failed to convert to policy');
      },
    });
  }

  // =========================================================================
  // MEMBER OPERATIONS
  // =========================================================================

  addMember() {
    const app = this.application();
    if (!app) return;

    const dialogRef = this.dialog.open(MedicalApplicationMemberDialog, {
      width: '70vw',
      maxHeight: '90vh',
      disableClose: true,
      data: { applicationId: app.id, principals: this.principalMembers() },
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.loadApplication();
        this.snackBar.open('Member added successfully', 'Close', { duration: 3000 });
      }
    });
  }

  editMember(member: ApplicationMember) {
    const app = this.application();
    if (!app) return;

    const dialogRef = this.dialog.open(MedicalApplicationMemberDialog, {
      width: '70vw',
      maxHeight: '90vh',
      disableClose: true,
      data: { applicationId: app.id, member, principals: this.principalMembers() },
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.loadApplication();
        this.snackBar.open('Member updated successfully', 'Close', { duration: 3000 });
      }
    });
  }

  removeMember(member: ApplicationMember) {
    const app = this.application();
    if (!app) return;

    if (confirm(`Remove ${member.full_name || member.first_name} from this application?`)) {
      this.store.removeMember(app.id, member.id).subscribe({
        next: () => {
          this.loadApplication();
          this.snackBar.open('Member removed', 'Close', { duration: 3000 });
        },
        error: (err) => {
          this.snackBar.open(err.error?.message || 'Failed to remove member', 'Close', {
            duration: 5000,
          });
        },
      });
    }
  }

  underwriteMember(member: ApplicationMember) {
    const app = this.application();
    if (!app) return;

    const dialogRef = this.dialog.open(MedicalUnderwritingDialog, {
      maxWidth: '70vw',
      maxHeight: '90vh',
      disableClose: true,
      data: { applicationId: app.id, member },
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.loadApplication();
        this.feedback.success('Underwriting decision recorded');
      }
    });
  }

  // =========================================================================
  // ADDON OPERATIONS
  // =========================================================================

  addAddon() {
    const app = this.application();
    if (!app) return;

    const existingAddonIds = app.addons?.map((a) => a.addon_id) || [];

    const dialogRef = this.dialog.open(MedicalApplicationAddonDialog, {
      maxWidth: '90vw',
      data: {
        planId: app.plan_id,
        memberCount: app.member_count || 0,
        basePremium: app.base_premium || 0,
        existingAddonIds,
      },
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.store.addAddon(app.id, result.addonId, result.addonRateId).subscribe({
          next: () => {
            this.loadApplication();
            this.snackBar.open('Addon added successfully', 'Close', { duration: 3000 });
          },
          error: (err) => {
            this.snackBar.open(err.error?.message || 'Failed to add addon', 'Close', {
              duration: 5000,
            });
          },
        });
      }
    });
  }

  removeAddon(addon: ApplicationAddon) {
    const app = this.application();
    if (!app) return;

    if (confirm(`Remove ${addon.addon_name || 'this addon'} from the application?`)) {
      this.store.removeAddon(app.id, addon.id).subscribe({
        next: () => {
          this.loadApplication();
          this.snackBar.open('Addon removed', 'Close', { duration: 3000 });
        },
        error: (err) => {
          this.snackBar.open(err.error?.message || 'Failed to remove addon', 'Close', {
            duration: 5000,
          });
        },
      });
    }
  }

  // =========================================================================
  // HELPER METHODS
  // =========================================================================

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

  getUwStatusClass(status: string | undefined): string {
    if (!status) return 'bg-slate-100 text-slate-700 border-slate-200';
    const colorMap: Record<string, string> = {
      pending: 'bg-slate-100 text-slate-600 border-slate-200',
      in_progress: 'bg-blue-50 text-blue-700 border-blue-200',
      approved: 'bg-green-50 text-green-700 border-green-200',
      declined: 'bg-red-50 text-red-700 border-red-200',
      referred: 'bg-orange-50 text-orange-700 border-orange-200',
      terms: 'bg-amber-50 text-amber-700 border-amber-200',
    };
    return colorMap[status] || 'bg-slate-100 text-slate-700 border-slate-200';
  }

  getStatusLabel(status: string): string {
    return getLabelByValue(APPLICATION_STATUSES, status);
  }

  getUwStatusLabel(status: string | undefined): string {
    if (!status) return 'Pending';
    return getLabelByValue(UNDERWRITING_STATUSES, status);
  }

  getMemberTypeLabel(type: string): string {
    return getLabelByValue(MEMBER_TYPES, type);
  }

  getRelationshipLabel(rel: string | undefined): string {
    if (!rel) return '-';
    return getLabelByValue(RELATIONSHIPS, rel);
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

  formatDate(date: string | undefined): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-ZM', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  }

  calculateAge(dob: string | undefined): number {
    if (!dob) return 0;
    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }
    return age;
  }

  canEdit(): boolean {
    const app = this.application();
    return app?.can_be_edited ?? app?.status === 'draft';
  }

  canSubmit(): boolean {
    const app = this.application();
    return app?.can_be_submitted ?? app?.status === 'quoted';
  }

  canUnderwrite(): boolean {
    const app = this.application();
    return (
      app?.can_be_underwritten ?? (app?.status === 'submitted' || app?.status === 'underwriting')
    );
  }

  canAccept(): boolean {
    const app = this.application();
    return app?.can_be_accepted ?? app?.status === 'approved';
  }

  canConvert(): boolean {
    const app = this.application();
    return app?.can_be_converted ?? app?.status === 'accepted';
  }

  trackById(index: number, item: { id: string }): string {
    return item.id;
  }
}
