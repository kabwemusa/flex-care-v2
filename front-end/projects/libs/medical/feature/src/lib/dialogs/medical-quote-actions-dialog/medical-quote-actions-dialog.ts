// libs/medical/feature/src/lib/dialogs/medical-quote-actions-dialog/medical-quote-actions-dialog.ts

import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

// Material Imports
import { MatDialogModule, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatIconModule } from '@angular/material/icon';
import { MatTabsModule } from '@angular/material/tabs';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

// Domain Imports
import { ApplicationStore } from 'medical-data';
import { FeedbackService } from 'shared';

// PDF Imports
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

export interface QuoteActionsDialogData {
  applicationId: string;
  applicationNumber: string;
  contactEmail?: string;
}

// === DESIGN CONFIGURATION ===
// Modern Navy & Slate Palette
const COLORS = {
  primary: '#1e3a8a', // Deep Navy
  secondary: '#3b82f6', // Bright Blue (Accents)
  text: '#1e293b', // Slate 800
  textLight: '#64748b', // Slate 500
  textMuted: '#94a3b8', // Slate 400
  border: '#e2e8f0', // Light Gray
  bgLight: '#f8fafc', // Off-white backgrounds
  white: '#ffffff',
  red: '#dc2626', // Risk/Alerts
  green: '#16a34a', // Success/Discounts
};

// Helper: Convert Hex to RGB for jsPDF
function hexToRgb(hex: string): [number, number, number] {
  const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
  return result
    ? [parseInt(result[1], 16), parseInt(result[2], 16), parseInt(result[3], 16)]
    : [0, 0, 0];
}

@Component({
  selector: 'lib-medical-quote-actions-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatButtonModule,
    MatFormFieldModule,
    MatInputModule,
    MatIconModule,
    MatTabsModule,
    MatProgressSpinnerModule,
  ],
  templateUrl: './medical-quote-actions-dialog.html',
})
export class MedicalQuoteActionsDialog {
  private readonly fb = inject(FormBuilder);
  private readonly store = inject(ApplicationStore);
  private readonly feedback = inject(FeedbackService);
  readonly dialogRef = inject(MatDialogRef<MedicalQuoteActionsDialog>);
  readonly data = inject<QuoteActionsDialogData>(MAT_DIALOG_DATA);

  readonly isDownloading = signal(false);
  readonly isSending = signal(false);

  readonly emailForm = this.fb.group({
    email: [this.data.contactEmail || '', [Validators.required, Validators.email]],
    message: ['', [Validators.maxLength(1000)]],
  });

  cancel() {
    this.dialogRef.close();
  }

  // ==========================================
  // ACTION: DOWNLOAD
  // ==========================================
  downloadQuote() {
    this.isDownloading.set(true);

    this.store.downloadQuote(this.data.applicationId).subscribe({
      next: (quoteData) => {
        try {
          this.generatePDF(quoteData);
          this.feedback.success('Quote downloaded successfully');
        } catch (e) {
          console.error(e);
          this.feedback.error('Error generating PDF');
        } finally {
          this.isDownloading.set(false);
        }
      },
      error: (err) => {
        this.isDownloading.set(false);
        this.feedback.error(err?.error?.message || 'Failed to download quote data');
      },
    });
  }

  // ==========================================
  // ACTION: EMAIL
  // ==========================================
  sendEmail() {
    if (this.emailForm.invalid) {
      this.emailForm.markAllAsTouched();
      return;
    }

    this.isSending.set(true);
    const { email, message } = this.emailForm.value;

    this.store.emailQuote(this.data.applicationId, email!, message || undefined).subscribe({
      next: () => {
        this.isSending.set(false);
        this.feedback.success(`Quote sent to ${email}`);
        this.dialogRef.close(true);
      },
      error: (err) => {
        this.isSending.set(false);
        this.feedback.error(err?.error?.message || 'Failed to send email');
      },
    });
  }

  // ==========================================
  // PDF GENERATION ENGINE
  // ==========================================
  private generatePDF(data: any) {
    const doc = new jsPDF('portrait', 'mm', 'a4');
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const margin = 18;
    let y = margin;

    const currency = data.premium_breakdown?.currency || 'ZMW';

    // --- 1. HEADER SECTION ---

    doc.setTextColor(...hexToRgb(COLORS.primary));
    doc.setFontSize(22);
    doc.setFont('helvetica', 'bold');
    doc.text('FLEXCARE', margin, y + 5);

    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(...hexToRgb(COLORS.textLight));
    doc.text('Medical Insurance Solutions', margin, y + 11);

    doc.setFontSize(8);
    doc.text('Plot 123, Cairo Road, Lusaka', margin, y + 16);
    doc.text('+260 211 123 456 | info@flexcare.co.zm', margin, y + 20);

    const rightX = pageWidth - margin;
    doc.setFontSize(9);
    doc.setTextColor(...hexToRgb(COLORS.textMuted));
    doc.text('QUOTE REFERENCE', rightX, y + 5, { align: 'right' });

    doc.setFontSize(14);
    doc.setTextColor(...hexToRgb(COLORS.text));
    doc.setFont('helvetica', 'bold');
    doc.text(data.application_number || 'DRAFT', rightX, y + 11, { align: 'right' });

    doc.setFontSize(9);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(...hexToRgb(COLORS.textLight));
    doc.text(`Issued: ${this.formatDate(data.quote_date)}`, rightX, y + 17, { align: 'right' });

    doc.setTextColor(...hexToRgb(COLORS.red));
    doc.text(`Valid Until: ${this.formatDate(data.valid_until)}`, rightX, y + 22, {
      align: 'right',
    });

    y += 30;

    doc.setDrawColor(...hexToRgb(COLORS.border));
    doc.setLineWidth(0.5);
    doc.line(margin, y, pageWidth - margin, y);
    y += 10;

    // --- 2. CLIENT & PLAN SUMMARY ---

    doc.setFontSize(8);
    doc.setTextColor(...hexToRgb(COLORS.textMuted));
    doc.text('PREPARED FOR', margin, y);

    doc.setFontSize(12);
    doc.setTextColor(...hexToRgb(COLORS.text));
    doc.setFont('helvetica', 'bold');
    doc.text(data.applicant_name || 'Valued Client', margin, y + 6);

    if (data.group) {
      doc.setFontSize(8);
      doc.setFont('helvetica', 'normal');
      doc.setTextColor(...hexToRgb(COLORS.textLight));
      doc.text(`Group: ${data.group.name} (${data.group.code})`, margin, y + 11);
    }

    doc.setFontSize(8);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(...hexToRgb(COLORS.textMuted));
    doc.text('SELECTED PLAN', rightX, y, { align: 'right' });

    doc.setFontSize(12);
    doc.setTextColor(...hexToRgb(COLORS.text));
    doc.setFont('helvetica', 'bold');
    doc.text(`${data.plan?.scheme || ''} - ${data.plan?.name || ''}`, rightX, y + 6, {
      align: 'right',
    });

    doc.setFontSize(9);
    doc.setTextColor(...hexToRgb(COLORS.textLight));
    doc.setFont('helvetica', 'normal');
    const period = `${this.formatDateShort(
      data.policy_details?.proposed_start_date
    )} to ${this.formatDateShort(data.policy_details?.proposed_end_date)}`;
    doc.text(period, rightX, y + 11, { align: 'right' });

    y += 20;

    // --- 3. CENSUS CARDS (from backend member_summary) ---

    doc.setFontSize(10);
    doc.setTextColor(...hexToRgb(COLORS.primary));
    doc.setFont('helvetica', 'bold');
    doc.text('COVERED LIVES BREAKDOWN', margin, y);
    y += 5;

    const summary = data.member_summary || this.calculateCensus(data.members || []);

    const cardData: { label: string; value: number; isTotal?: boolean }[] = [
      { label: 'Principals', value: summary.principals || 0 },
      { label: 'Spouses', value: summary.spouses || 0 },
      { label: 'Children', value: summary.children || 0 },
      { label: 'Parents', value: summary.parents || 0 },
      { label: 'Total', value: summary.total || 0, isTotal: true },
    ];

    const gap = 4;
    const cardWidth = (pageWidth - margin * 2 - gap * (cardData.length - 1)) / cardData.length;
    const cardHeight = 22;

    cardData.forEach((card, i) => {
      const xPos = margin + i * (cardWidth + gap);

      if (card.isTotal) {
        doc.setFillColor(...hexToRgb(COLORS.primary));
        doc.setTextColor(255, 255, 255);
      } else {
        doc.setFillColor(...hexToRgb(COLORS.bgLight));
        doc.setTextColor(...hexToRgb(COLORS.textLight));
      }
      doc.roundedRect(xPos, y, cardWidth, cardHeight, 2, 2, 'F');

      doc.setFontSize(7);
      doc.setFont('helvetica', 'normal');
      doc.text(card.label.toUpperCase(), xPos + cardWidth / 2, y + 7, { align: 'center' });

      doc.setFontSize(12);
      doc.setFont('helvetica', 'bold');
      if (!card.isTotal) doc.setTextColor(...hexToRgb(COLORS.text));
      doc.text(card.value.toString(), xPos + cardWidth / 2, y + 16, { align: 'center' });
    });

    y += cardHeight + 12;

    // --- 4. MEMBERS TABLE ---

    // const members = data.members || [];
    // if (members.length > 0) {
    //   doc.setFontSize(10);
    //   doc.setTextColor(...hexToRgb(COLORS.primary));
    //   doc.setFont('helvetica', 'bold');
    //   doc.text('MEMBER DETAILS', margin, y);
    //   y += 4;

    //   const memberRows = members.map((m: any) => [
    //     m.name || '-',
    //     m.member_type_label || m.member_type || '-',
    //     m.age?.toString() || '-',
    //     m.gender === 'M' ? 'Male' : m.gender === 'F' ? 'Female' : m.gender || '-',
    //     this.formatCurrency(m.base_premium, currency),
    //     m.loading_amount > 0
    //       ? { content: `+ ${this.formatCurrency(m.loading_amount, currency)}`, styles: { textColor: hexToRgb(COLORS.red) } }
    //       : '-',
    //     this.formatCurrency(m.total_premium, currency),
    //   ]);

    //   autoTable(doc, {
    //     startY: y,
    //     head: [['Name', 'Type', 'Age', 'Gender', 'Base', 'Loading', 'Total']],
    //     body: memberRows,
    //     theme: 'striped',
    //     styles: {
    //       fontSize: 7.5,
    //       cellPadding: 3,
    //       textColor: hexToRgb(COLORS.text),
    //     },
    //     headStyles: {
    //       fillColor: hexToRgb(COLORS.bgLight),
    //       textColor: hexToRgb(COLORS.textLight),
    //       fontStyle: 'bold',
    //       fontSize: 7,
    //     },
    //     columnStyles: {
    //       0: { cellWidth: 'auto' },
    //       4: { halign: 'right', cellWidth: 28 },
    //       5: { halign: 'right', cellWidth: 28 },
    //       6: { halign: 'right', fontStyle: 'bold', cellWidth: 28 },
    //     },
    //     margin: { left: margin, right: margin },
    //   });

    //   y = (doc as any).lastAutoTable.finalY + 10;
    // }

    // // Check if we need a new page for the premium breakdown
    // if (y > pageHeight - 100) {
    //   doc.addPage();
    //   y = margin;
    // }

    // --- 5. PREMIUM BREAKDOWN ---

    doc.setFontSize(10);
    doc.setTextColor(...hexToRgb(COLORS.primary));
    doc.setFont('helvetica', 'bold');
    doc.text('PREMIUM BREAKDOWN', margin, y);
    y += 5;

    const pb = data.premium_breakdown || {};
    const tableBody: any[] = [];

    tableBody.push(['Base Premium', this.formatCurrency(pb.base_premium, currency)]);

    if (pb.addon_premium > 0) {
      tableBody.push(['Add-On Benefits', this.formatCurrency(pb.addon_premium, currency)]);

      // Itemize addons
      const addons = data.addons || [];
      addons.forEach((a: any) => {
        if (a.premium > 0) {
          tableBody.push([
            {
              content: `   ${a.name}${a.is_mandatory ? ' (Mandatory)' : ''}`,
              styles: { textColor: hexToRgb(COLORS.textLight), fontSize: 8 },
            },
            {
              content: this.formatCurrency(a.premium, currency),
              styles: { textColor: hexToRgb(COLORS.textLight), fontSize: 8 },
            },
          ]);
        }
      });
    }

    if (pb.loading_amount > 0) {
      tableBody.push([
        'Risk Adjustments (Loadings)',
        {
          content: `+ ${this.formatCurrency(pb.loading_amount, currency)}`,
          styles: { textColor: hexToRgb(COLORS.red) },
        },
      ]);
    }

    if (pb.discount_amount > 0) {
      tableBody.push([
        'Discounts Applied',
        {
          content: `- ${this.formatCurrency(pb.discount_amount, currency)}`,
          styles: { textColor: hexToRgb(COLORS.green) },
        },
      ]);
    }

    if (pb.tax_amount > 0) {
      const taxPct = pb.tax_rate ? ` (${(pb.tax_rate * 100).toFixed(0)}%)` : '';
      tableBody.push([`Tax / Levies${taxPct}`, this.formatCurrency(pb.tax_amount, currency)]);
    }

    autoTable(doc, {
      startY: y,
      body: tableBody,
      theme: 'plain',
      styles: {
        fontSize: 10,
        cellPadding: 5,
        textColor: hexToRgb(COLORS.text),
        lineWidth: { bottom: 0.1 },
        lineColor: hexToRgb(COLORS.border),
      },
      columnStyles: {
        0: { cellWidth: 'auto' },
        1: { halign: 'right', fontStyle: 'bold', cellWidth: 60 },
      },
      margin: { left: margin, right: margin },
    });

    // --- 6. TOTAL BOX ---

    y = (doc as any).lastAutoTable.finalY + 8;

    doc.setFillColor(...hexToRgb(COLORS.primary));
    doc.roundedRect(margin, y, pageWidth - margin * 2, 24, 2, 2, 'F');

    doc.setTextColor(255, 255, 255);
    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    doc.text('TOTAL PAYABLE', margin + 10, y + 15);

    doc.setFontSize(16);
    doc.text(this.formatCurrency(pb.gross_premium, currency), pageWidth - margin - 10, y + 15, {
      align: 'right',
    });

    doc.setFontSize(8);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(200, 200, 200);
    doc.text(
      this.formatBillingFrequency(pb.billing_frequency) + ' Payment',
      pageWidth - margin - 10,
      y + 21,
      { align: 'right' }
    );

    // --- 7. FOOTER ---

    const totalPages = doc.getNumberOfPages();
    for (let page = 1; page <= totalPages; page++) {
      doc.setPage(page);
      const footerY = pageHeight - 15;

      doc.setDrawColor(...hexToRgb(COLORS.border));
      doc.setLineWidth(0.3);
      doc.line(margin, footerY - 5, pageWidth - margin, footerY - 5);

      doc.setFontSize(7);
      doc.setTextColor(...hexToRgb(COLORS.textMuted));
      doc.text(
        'Terms & Conditions Apply. Valid for 30 days. Underwriting required.',
        margin,
        footerY
      );
      doc.text(
        `Generated on ${new Date().toLocaleDateString(
          'en-GB'
        )} \u2022 Page ${page} of ${totalPages}`,
        pageWidth - margin,
        footerY,
        { align: 'right' }
      );
    }

    // Save/Open
    const pdfBlob = doc.output('blob');
    const pdfUrl = URL.createObjectURL(pdfBlob);
    window.open(pdfUrl, '_blank');
  }

  // ==========================================
  // HELPERS
  // ==========================================

  /** Fallback census calculation using member_type field (used when member_summary is absent). */
  private calculateCensus(members: any[]) {
    const census = { principals: 0, spouses: 0, children: 0, parents: 0, total: 0 };
    members.forEach((m) => {
      const type = (m.member_type || '').toLowerCase();
      if (type === 'principal') census.principals++;
      else if (type === 'spouse') census.spouses++;
      else if (type === 'child') census.children++;
      else if (type === 'parent') census.parents++;
    });
    census.total = members.length;
    return census;
  }

  private formatCurrency(amount: any, currency = 'ZMW'): string {
    const value = Number(amount) || 0;
    return `${currency} ${value.toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  }

  private formatDate(date: string | undefined): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-GB', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    });
  }

  private formatDateShort(date: string | undefined): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  private formatBillingFrequency(freq: string | undefined): string {
    if (!freq) return 'Annual';
    return freq.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  }
}
