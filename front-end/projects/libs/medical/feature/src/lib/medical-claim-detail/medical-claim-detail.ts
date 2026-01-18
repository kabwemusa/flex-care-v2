// libs/medical/feature/src/lib/medical-claim-detail/medical-claim-detail.ts

import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import {
  FormsModule,
  ReactiveFormsModule,
  FormBuilder,
  FormGroup,
  Validators,
} from '@angular/forms';

// Material Imports
import { MatTabsModule } from '@angular/material/tabs';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatChipsModule } from '@angular/material/chips';
import { MatTableModule } from '@angular/material/table';
import { MatMenuModule } from '@angular/material/menu';
import { MatDividerModule } from '@angular/material/divider';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatDialog } from '@angular/material/dialog';
import { MatExpansionModule } from '@angular/material/expansion';

// Domain Imports
import { ClaimStore, Claim, ClaimLine, MemberStore } from 'medical-data';
import { FeedbackService, PageHeaderComponent, ConfirmationDialog } from 'shared';
import { MedicalMemberBenefitsDialog } from '../dialogs/medical-member-benefits-dialog/medical-member-benefits-dialog';

@Component({
  selector: 'lib-medical-claim-detail',
  standalone: true,
  imports: [
    CommonModule,
    RouterLink,
    FormsModule,
    ReactiveFormsModule,
    MatTabsModule,
    MatCardModule,
    MatIconModule,
    MatButtonModule,
    MatChipsModule,
    MatTableModule,
    MatMenuModule,
    MatDividerModule,
    MatProgressSpinnerModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatTooltipModule,
    MatExpansionModule,
    PageHeaderComponent,
  ],
  templateUrl: './medical-claim-detail.html',
  styleUrl: './medical-claim-detail.css',
})
export class MedicalClaimDetail implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly fb = inject(FormBuilder);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);
  readonly store = inject(ClaimStore);
  private readonly memberStore = inject(MemberStore);

  claimId = signal<string | null>(null);
  activeTabIndex = signal(0);
  noteForm!: FormGroup;
  showNoteForm = signal(false);

  // Claim status configs
  readonly CLAIM_STATUSES: Record<string, { label: string; color: string; bgColor: string }> = {
    submitted: { label: 'Submitted', color: 'text-blue-700', bgColor: 'bg-blue-100' },
    pending_documents: {
      label: 'Pending Documents',
      color: 'text-yellow-700',
      bgColor: 'bg-yellow-100',
    },
    in_review: { label: 'In Review', color: 'text-purple-700', bgColor: 'bg-purple-100' },
    pending_approval: {
      label: 'Pending Approval',
      color: 'text-orange-700',
      bgColor: 'bg-orange-100',
    },
    approved: { label: 'Approved', color: 'text-green-700', bgColor: 'bg-green-100' },
    partially_approved: {
      label: 'Partially Approved',
      color: 'text-teal-700',
      bgColor: 'bg-teal-100',
    },
    rejected: { label: 'Rejected', color: 'text-red-700', bgColor: 'bg-red-100' },
    paid: { label: 'Paid', color: 'text-emerald-700', bgColor: 'bg-emerald-100' },
    closed: { label: 'Closed', color: 'text-slate-700', bgColor: 'bg-slate-100' },
  };

  readonly CLAIM_TYPES: Record<string, { label: string; icon: string }> = {
    in_patient: { label: 'In-Patient', icon: 'local_hospital' },
    out_patient: { label: 'Out-Patient', icon: 'medical_services' },
    dental: { label: 'Dental', icon: 'dentistry' },
    optical: { label: 'Optical', icon: 'visibility' },
    maternity: { label: 'Maternity', icon: 'pregnant_woman' },
    chronic: { label: 'Chronic', icon: 'medication' },
    wellness: { label: 'Wellness', icon: 'spa' },
    emergency: { label: 'Emergency', icon: 'emergency' },
  };

  readonly REJECTION_REASONS = [
    { value: 'benefit_exhausted', label: 'Benefit Exhausted' },
    { value: 'not_covered', label: 'Not Covered' },
    { value: 'waiting_period', label: 'Within Waiting Period' },
    { value: 'pre_existing', label: 'Pre-existing Condition' },
    { value: 'policy_inactive', label: 'Policy Not Active' },
    { value: 'member_inactive', label: 'Member Not Active' },
    { value: 'duplicate', label: 'Duplicate Claim' },
    { value: 'documentation', label: 'Missing Documentation' },
    { value: 'fraud', label: 'Suspected Fraud' },
    { value: 'exclusion', label: 'Excluded Condition' },
    { value: 'other', label: 'Other' },
  ];

  readonly linesColumns = ['line', 'description', 'quantity', 'claimed', 'approved', 'status'];

  // Computed
  claim = computed(() => this.store.selectedClaim());

  ngOnInit() {
    this.initForms();

    this.route.paramMap.subscribe((params) => {
      const id = params.get('id');
      if (id) {
        this.claimId.set(id);
        this.loadClaim(id);
      }
    });
  }

  private initForms() {
    this.noteForm = this.fb.group({
      content: ['', [Validators.required, Validators.maxLength(2000)]],
      is_internal: [true],
    });
  }

  loadClaim(id: string) {
    this.store.loadOne(id).subscribe({
      error: () => {
        this.feedback.error('Failed to load claim');
        this.router.navigate(['/claims']);
      },
    });
  }

  // Status helpers
  getStatusConfig(status: string) {
    return (
      this.CLAIM_STATUSES[status] || {
        label: status,
        color: 'text-slate-700',
        bgColor: 'bg-slate-100',
      }
    );
  }

  getClaimTypeConfig(type: string) {
    return this.CLAIM_TYPES[type] || { label: type, icon: 'receipt' };
  }

  getLineStatusClass(status: string): string {
    switch (status) {
      case 'approved':
        return 'bg-green-100 text-green-700';
      case 'partially_approved':
        return 'bg-teal-100 text-teal-700';
      case 'rejected':
        return 'bg-red-100 text-red-700';
      default:
        return 'bg-slate-100 text-slate-600';
    }
  }

  // Format helpers
  formatCurrency(amount: number | undefined, currency = 'ZMW'): string {
    if (amount === undefined || amount === null) return '-';
    return new Intl.NumberFormat('en-ZM', {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: 2,
    }).format(amount);
  }

  // Workflow actions
  startReview() {
    const claim = this.claim();
    if (!claim) return;

    this.store.startReview(claim.id).subscribe({
      next: () => this.feedback.success('Review started'),
      error: (err) => this.feedback.error(err.error?.message || 'Failed to start review'),
    });
  }

  approveClaim() {
    const claim = this.claim();
    if (!claim) return;

    const dialogRef = this.dialog.open(ConfirmationDialog, {
      data: {
        title: 'Approve Claim',
        message: `Are you sure you want to approve claim ${
          claim.claim_number
        } for ${this.formatCurrency(claim.claimed_amount, claim.currency)}?`,
        confirmText: 'Approve',
        confirmColor: 'primary',
      },
    });

    dialogRef.afterClosed().subscribe((confirmed) => {
      if (confirmed) {
        this.store.approve(claim.id).subscribe({
          next: () => this.feedback.success('Claim approved successfully'),
          error: (err) => this.feedback.error(err.error?.message || 'Failed to approve claim'),
        });
      }
    });
  }

  rejectClaim() {
    const claim = this.claim();
    if (!claim) return;

    // Simple prompt for rejection reason - in production, use a proper dialog
    const reason = prompt('Enter rejection reason:');
    if (!reason) return;

    this.store.reject(claim.id, reason).subscribe({
      next: () => this.feedback.success('Claim rejected'),
      error: (err) => this.feedback.error(err.error?.message || 'Failed to reject claim'),
    });
  }

  closeClaim() {
    const claim = this.claim();
    if (!claim) return;

    this.store.close(claim.id).subscribe({
      next: () => this.feedback.success('Claim closed'),
      error: (err) => this.feedback.error(err.error?.message || 'Failed to close claim'),
    });
  }

  // Notes
  toggleNoteForm() {
    this.showNoteForm.update((v) => !v);
    if (!this.showNoteForm()) {
      this.noteForm.reset({ is_internal: true });
    }
  }

  submitNote() {
    if (this.noteForm.invalid) return;

    const claim = this.claim();
    if (!claim) return;

    const { content, is_internal } = this.noteForm.value;
    this.store.addNote(claim.id, content, is_internal).subscribe({
      next: () => {
        this.feedback.success('Note added');
        this.toggleNoteForm();
      },
      error: (err) => this.feedback.error(err.error?.message || 'Failed to add note'),
    });
  }

  // View member benefits
  openMemberBenefits() {
    const claim = this.claim();
    if (!claim?.member?.id) return;

    // Build member name from available fields
    const memberName =
      claim.member.full_name ||
      `${claim.member.first_name || ''} ${claim.member.last_name || ''}`.trim();

    // Load member benefits and open dialog
    this.memberStore.loadBenefitBalances(claim.member.id);

    this.dialog.open(MedicalMemberBenefitsDialog, {
      maxWidth: '75vw',
      maxHeight: '90vh',
      data: {
        member_id: claim.member.id,
        member_name: memberName,
        policy_number: claim.member.member_number || '',
      },
    });
  }
}
