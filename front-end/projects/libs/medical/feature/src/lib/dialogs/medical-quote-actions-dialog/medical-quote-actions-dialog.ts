// libs/medical/feature/src/lib/dialogs/medical-quote-actions-dialog/medical-quote-actions-dialog.ts

import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { MatDialogModule, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatIconModule } from '@angular/material/icon';
import { MatTabsModule } from '@angular/material/tabs';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

import { ApplicationStore } from 'medical-data';
import { FeedbackService } from 'shared';

import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';

export interface QuoteActionsDialogData {
  applicationId: string;
  applicationNumber: string;
  contactEmail?: string;
}

// Color scheme for corporate professional look
const COLORS = {
  primary: [30, 58, 138] as [number, number, number],      // Deep Blue #1e3a8a
  secondary: [59, 130, 246] as [number, number, number],   // Bright Blue #3b82f6
  accent: [16, 185, 129] as [number, number, number],      // Emerald Green #10b981
  dark: [15, 23, 42] as [number, number, number],          // Slate 900
  text: [51, 65, 85] as [number, number, number],          // Slate 700
  textLight: [100, 116, 139] as [number, number, number],  // Slate 500
  border: [226, 232, 240] as [number, number, number],     // Slate 200
  light: [248, 250, 252] as [number, number, number],      // Slate 50
  white: [255, 255, 255] as [number, number, number],
  success: [22, 101, 52] as [number, number, number],      // Green 800
  successLight: [220, 252, 231] as [number, number, number], // Green 100
};

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

  downloadQuote() {
    this.isDownloading.set(true);

    this.store.downloadQuote(this.data.applicationId).subscribe({
      next: (quoteData) => {
        this.generatePDF(quoteData);
        this.isDownloading.set(false);
        this.feedback.success('Quote downloaded successfully');
      },
      error: (err) => {
        this.isDownloading.set(false);
        this.feedback.error(err?.error?.message || 'Failed to download quote');
      },
    });
  }

  sendEmail() {
    if (this.emailForm.invalid) {
      this.emailForm.markAllAsTouched();
      return;
    }

    const formValue = this.emailForm.value;
    this.isSending.set(true);

    const email = formValue.email ?? undefined;
    const message = formValue.message ?? undefined;

    this.store.emailQuote(this.data.applicationId, email!, message).subscribe({
      next: () => {
        this.isSending.set(false);
        this.feedback.success(`Quote sent to ${email}`);
        this.dialogRef.close(true);
      },
      error: (err) => {
        this.isSending.set(false);
        this.feedback.error(err?.error?.message || 'Failed to send quote email');
      },
    });
  }

  private generatePDF(data: any) {
    const doc = new jsPDF({
      orientation: 'portrait',
      unit: 'mm',
      format: 'a4',
    });

    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const margin = 15;
    let yPos = margin;

    // ===== HEADER SECTION =====
    yPos = this.drawHeader(doc, data, pageWidth, margin, yPos);

    // ===== QUOTE INFO CARDS =====
    yPos = this.drawQuoteInfoSection(doc, data, pageWidth, margin, yPos);

    // ===== COVERED MEMBERS TABLE =====
    yPos = this.drawMembersTable(doc, data, pageWidth, margin, yPos);

    // ===== ADD-ONS TABLE (if any) =====
    if (data.addons?.length > 0) {
      yPos = this.drawAddonsTable(doc, data, pageWidth, margin, yPos);
    }

    // ===== PREMIUM SUMMARY BOX =====
    yPos = this.drawPremiumSummary(doc, data, pageWidth, margin, yPos);

    // ===== TERMS & CONDITIONS =====
    this.drawTermsSection(doc, data, pageWidth, margin, yPos);

    // ===== FOOTER =====
    this.drawFooter(doc, data, pageWidth, pageHeight, margin);

    // Open PDF in new tab
    const pdfBlob = doc.output('blob');
    const pdfUrl = URL.createObjectURL(pdfBlob);
    window.open(pdfUrl, '_blank');
  }

  private drawHeader(doc: jsPDF, data: any, pageWidth: number, margin: number, _yPos: number): number {
    // Draw header background stripe
    doc.setFillColor(...COLORS.primary);
    doc.rect(0, 0, pageWidth, 35, 'F');

    // Company branding
    doc.setTextColor(...COLORS.white);
    doc.setFontSize(22);
    doc.setFont('helvetica', 'bold');
    doc.text('FLEXCARE', margin, 15);

    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.text('Medical Insurance Solutions', margin, 22);

    // Quote badge on right
    const badgeX = pageWidth - margin - 55;
    doc.setFillColor(...COLORS.white);
    doc.roundedRect(badgeX, 6, 55, 23, 2, 2, 'F');

    doc.setTextColor(...COLORS.textLight);
    doc.setFontSize(7);
    doc.setFont('helvetica', 'bold');
    doc.text('QUOTE REFERENCE', badgeX + 27.5, 13, { align: 'center' });

    doc.setTextColor(...COLORS.primary);
    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    doc.text(data.application_number || 'N/A', badgeX + 27.5, 22, { align: 'center' });

    // Decorative accent line below header
    doc.setFillColor(...COLORS.accent);
    doc.rect(0, 35, pageWidth, 2, 'F');

    return 45;
  }

  private drawQuoteInfoSection(doc: jsPDF, data: any, pageWidth: number, margin: number, yPos: number): number {
    const colWidth = (pageWidth - margin * 2 - 10) / 2;

    // Left card - Client Info
    this.drawInfoCard(doc, margin, yPos, colWidth, 45, [
      { label: 'PREPARED FOR', value: data.applicant_name || 'Valued Customer', isMain: true },
      { label: 'EMAIL', value: data.contact_email || '-' },
      { label: 'PHONE', value: data.contact_phone || '-' },
      { label: 'PLAN', value: `${data.plan?.scheme || ''} - ${data.plan?.name || ''}` },
    ]);

    // Right card - Quote Details
    this.drawInfoCard(doc, margin + colWidth + 10, yPos, colWidth, 45, [
      { label: 'DATE ISSUED', value: this.formatDate(data.quote_date), isMain: true },
      { label: 'VALID UNTIL', value: this.formatDate(data.valid_until), highlight: true },
      { label: 'POLICY TERM', value: `${data.policy_details?.policy_term_months || 12} Months` },
      {
        label: 'COVERAGE PERIOD',
        value: `${this.formatDateShort(data.policy_details?.proposed_start_date)} - ${this.formatDateShort(data.policy_details?.proposed_end_date)}`,
      },
    ]);

    return yPos + 52;
  }

  private drawInfoCard(
    doc: jsPDF,
    x: number,
    y: number,
    width: number,
    height: number,
    items: { label: string; value: string; isMain?: boolean; highlight?: boolean }[]
  ) {
    // Card background
    doc.setFillColor(...COLORS.light);
    doc.setDrawColor(...COLORS.border);
    doc.roundedRect(x, y, width, height, 2, 2, 'FD');

    let itemY = y + 6;
    items.forEach((item) => {
      // Label
      doc.setTextColor(...COLORS.textLight);
      doc.setFontSize(7);
      doc.setFont('helvetica', 'bold');
      doc.text(item.label, x + 5, itemY);

      // Value
      if (item.highlight) {
        doc.setTextColor(239, 68, 68); // Red for expiry
      } else if (item.isMain) {
        doc.setTextColor(...COLORS.primary);
      } else {
        doc.setTextColor(...COLORS.text);
      }
      doc.setFontSize(item.isMain ? 11 : 9);
      doc.setFont('helvetica', item.isMain ? 'bold' : 'normal');
      doc.text(item.value, x + 5, itemY + 5);

      itemY += item.isMain ? 13 : 10;
    });
  }

  private drawMembersTable(doc: jsPDF, data: any, _pageWidth: number, margin: number, yPos: number): number {
    // Section title
    doc.setTextColor(...COLORS.primary);
    doc.setFontSize(11);
    doc.setFont('helvetica', 'bold');
    doc.text('COVERED MEMBERS', margin, yPos);

    // Underline
    doc.setDrawColor(...COLORS.primary);
    doc.setLineWidth(0.5);
    doc.line(margin, yPos + 2, margin + 40, yPos + 2);

    yPos += 6;

    const members = data.members || [];
    const tableData = members.map((m: any) => [
      m.name || '-',
      m.relationship || 'Principal',
      `${m.age || '-'} / ${m.gender || '-'}`,
      'Covered',
      this.formatCurrency(m.premium, data.premium_breakdown?.currency),
    ]);

    autoTable(doc, {
      startY: yPos,
      head: [['Member Name', 'Relationship', 'Age / Gender', 'Status', 'Premium']],
      body: tableData.length > 0 ? tableData : [['No members defined', '', '', '', '']],
      margin: { left: margin, right: margin },
      theme: 'plain',
      headStyles: {
        fillColor: COLORS.primary,
        textColor: COLORS.white,
        fontStyle: 'bold',
        fontSize: 8,
        cellPadding: 4,
      },
      bodyStyles: {
        textColor: COLORS.text,
        fontSize: 9,
        cellPadding: 4,
      },
      alternateRowStyles: {
        fillColor: COLORS.light,
      },
      columnStyles: {
        0: { fontStyle: 'bold', cellWidth: 50 },
        1: { cellWidth: 30 },
        2: { cellWidth: 30 },
        3: { cellWidth: 25 },
        4: { halign: 'right', fontStyle: 'bold', cellWidth: 35 },
      },
      didParseCell: (hookData) => {
        // Style "Covered" status cell
        if (hookData.section === 'body' && hookData.column.index === 3) {
          hookData.cell.styles.textColor = COLORS.success;
        }
      },
    });

    return (doc as any).lastAutoTable.finalY + 8;
  }

  private drawAddonsTable(doc: jsPDF, data: any, _pageWidth: number, margin: number, yPos: number): number {
    // Section title
    doc.setTextColor(...COLORS.primary);
    doc.setFontSize(11);
    doc.setFont('helvetica', 'bold');
    doc.text('SELECTED ADD-ONS', margin, yPos);

    doc.setDrawColor(...COLORS.primary);
    doc.setLineWidth(0.5);
    doc.line(margin, yPos + 2, margin + 38, yPos + 2);

    yPos += 6;

    const addons = data.addons || [];
    const tableData = addons.map((a: any) => [
      a.name || '-',
      this.formatCurrency(a.premium, data.premium_breakdown?.currency),
    ]);

    autoTable(doc, {
      startY: yPos,
      head: [['Add-on Description', 'Premium']],
      body: tableData,
      margin: { left: margin, right: margin },
      theme: 'plain',
      headStyles: {
        fillColor: COLORS.primary,
        textColor: COLORS.white,
        fontStyle: 'bold',
        fontSize: 8,
        cellPadding: 4,
      },
      bodyStyles: {
        textColor: COLORS.text,
        fontSize: 9,
        cellPadding: 4,
      },
      alternateRowStyles: {
        fillColor: COLORS.light,
      },
      columnStyles: {
        0: { cellWidth: 'auto' },
        1: { halign: 'right', fontStyle: 'bold', cellWidth: 40 },
      },
    });

    return (doc as any).lastAutoTable.finalY + 8;
  }

  private drawPremiumSummary(doc: jsPDF, data: any, pageWidth: number, margin: number, yPos: number): number {
    const boxWidth = 85;
    const boxX = pageWidth - margin - boxWidth;
    const pb = data.premium_breakdown || {};

    // Build summary items
    const items: { label: string; value: string; isDiscount?: boolean; isTotal?: boolean }[] = [];

    items.push({ label: 'Billing Frequency', value: this.formatBillingFrequency(pb.billing_frequency) });
    items.push({ label: 'Base Premium', value: this.formatCurrency(pb.base_premium, pb.currency) });

    if (pb.addon_premium > 0) {
      items.push({ label: 'Add-ons', value: this.formatCurrency(pb.addon_premium, pb.currency) });
    }
    if (pb.loading_amount > 0) {
      items.push({ label: 'Risk Loadings', value: this.formatCurrency(pb.loading_amount, pb.currency) });
    }
    if (pb.discount_amount > 0) {
      items.push({
        label: 'Discounts',
        value: `-${this.formatCurrency(pb.discount_amount, pb.currency)}`,
        isDiscount: true,
      });
    }
    if (pb.tax_amount > 0) {
      items.push({ label: 'Tax / VAT', value: this.formatCurrency(pb.tax_amount, pb.currency) });
    }

    items.push({ label: 'TOTAL PAYABLE', value: this.formatCurrency(pb.gross_premium, pb.currency), isTotal: true });

    const rowHeight = 9;
    const totalRowHeight = 14;
    const boxHeight = (items.length - 1) * rowHeight + totalRowHeight + 4;

    // Card background
    doc.setFillColor(...COLORS.light);
    doc.setDrawColor(...COLORS.border);
    doc.roundedRect(boxX, yPos, boxWidth, boxHeight, 2, 2, 'FD');

    let itemY = yPos + 7;
    items.forEach((item) => {
      if (item.isTotal) {
        // Total row with primary background
        doc.setFillColor(...COLORS.primary);
        doc.roundedRect(boxX, itemY - 4, boxWidth, totalRowHeight, 0, 0, 'F');
        // Round bottom corners
        doc.roundedRect(boxX, itemY + 2, boxWidth, totalRowHeight - 6, 2, 2, 'F');

        doc.setTextColor(...COLORS.white);
        doc.setFontSize(10);
        doc.setFont('helvetica', 'bold');
        doc.text(item.label, boxX + 5, itemY + 4);
        doc.text(item.value, boxX + boxWidth - 5, itemY + 4, { align: 'right' });
      } else {
        // Regular row
        doc.setTextColor(item.isDiscount ? COLORS.success[0] : COLORS.textLight[0], item.isDiscount ? COLORS.success[1] : COLORS.textLight[1], item.isDiscount ? COLORS.success[2] : COLORS.textLight[2]);
        doc.setFontSize(8);
        doc.setFont('helvetica', 'normal');
        doc.text(item.label, boxX + 5, itemY);

        doc.setTextColor(item.isDiscount ? COLORS.success[0] : COLORS.text[0], item.isDiscount ? COLORS.success[1] : COLORS.text[1], item.isDiscount ? COLORS.success[2] : COLORS.text[2]);
        doc.setFont('helvetica', 'bold');
        doc.text(item.value, boxX + boxWidth - 5, itemY, { align: 'right' });

        itemY += rowHeight;
      }
    });

    return yPos + boxHeight + 10;
  }

  private drawTermsSection(doc: jsPDF, _data: any, pageWidth: number, margin: number, yPos: number) {
    // Only draw if there's space
    if (yPos > 240) return;

    doc.setTextColor(...COLORS.textLight);
    doc.setFontSize(7);
    doc.setFont('helvetica', 'italic');

    const termsText =
      'Terms & Conditions: This quote is subject to the standard terms and conditions of the Medical Insurance Policy. ' +
      'Final acceptance is subject to underwriting approval. Premium rates are subject to change based on underwriting assessment. ' +
      'This quote does not constitute a contract of insurance until accepted and payment is received.';

    const splitText = doc.splitTextToSize(termsText, pageWidth - margin * 2);
    doc.text(splitText, margin, yPos);
  }

  private drawFooter(doc: jsPDF, _data: any, pageWidth: number, pageHeight: number, margin: number) {
    const footerY = pageHeight - 12;

    // Footer line
    doc.setDrawColor(...COLORS.border);
    doc.setLineWidth(0.3);
    doc.line(margin, footerY - 5, pageWidth - margin, footerY - 5);

    // Footer text
    doc.setTextColor(...COLORS.textLight);
    doc.setFontSize(7);
    doc.setFont('helvetica', 'normal');

    doc.text(`Generated on ${new Date().toLocaleString()}`, margin, footerY);
    doc.text('FlexCare Medical Insurance Portal', pageWidth / 2, footerY, { align: 'center' });
    doc.text('Page 1 of 1', pageWidth - margin, footerY, { align: 'right' });
  }

  private formatCurrency(amount: number, currency: string = 'KES'): string {
    return `${currency} ${(amount || 0).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  }

  private formatDate(date: string): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  }

  private formatDateShort(date: string): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  }

  private formatBillingFrequency(frequency: string): string {
    const map: Record<string, string> = {
      monthly: 'Monthly',
      quarterly: 'Quarterly',
      semi_annual: 'Semi-Annual',
      annual: 'Annual',
    };
    return map[frequency] || frequency || '-';
  }
}
