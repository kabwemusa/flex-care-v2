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

export interface QuoteActionsDialogData {
  applicationId: string;
  applicationNumber: string;
  contactEmail?: string;
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

  downloadQuote() {
    this.isDownloading.set(true);

    this.store.downloadQuote(this.data.applicationId).subscribe({
      next: (quoteData) => {
        // Generate and download PDF using the quote data
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

  private generatePDF(quoteData: any) {
    // Create a simple HTML representation and convert to PDF
    const content = this.buildQuoteHTML(quoteData);

    // Create a new window and write content
    const printWindow = window.open('', '_blank');
    if (printWindow) {
      printWindow.document.write(content);
      printWindow.document.close();

      // Wait for content to load, then trigger print
      printWindow.onload = () => {
        printWindow.focus();

        // Trigger print dialog after a short delay to ensure rendering
        setTimeout(() => {
          printWindow.print();
        }, 500);
      };

      // Handle print completion - close window when user closes print dialog
      printWindow.onafterprint = () => {
        printWindow.close();
      };
    }
  }

  private buildQuoteHTML(data: any): string {
    return `
      <!DOCTYPE html>
      <html>
      <head>
        <title>Quote - ${data.application_number}</title>
        <style>
          body {
            font-family: Arial, sans-serif;
            padding: 40px;
            color: #333;
          }
          .header {
            border-bottom: 3px solid #3b82f6;
            padding-bottom: 20px;
            margin-bottom: 30px;
          }
          .header h1 {
            color: #1e40af;
            margin: 0;
          }
          .section {
            margin-bottom: 30px;
          }
          .section h2 {
            color: #1e40af;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px;
          }
          table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
          }
          th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
          }
          th {
            background-color: #f3f4f6;
            font-weight: 600;
          }
          .total-row {
            font-weight: bold;
            font-size: 1.1em;
            background-color: #f3f4f6;
          }
          .text-right {
            text-align: right;
          }
          .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 0.9em;
          }
        </style>
      </head>
      <body>
        <div class="header">
          <h1>Insurance Quote</h1>
          <p><strong>Quote Number:</strong> ${data.application_number}</p>
          <p><strong>Quote Date:</strong> ${this.formatDate(data.quote_date)}</p>
          <p><strong>Valid Until:</strong> ${this.formatDate(data.valid_until)}</p>
        </div>

        <div class="section">
          <h2>Customer Information</h2>
          <p><strong>Name:</strong> ${data.applicant_name || '-'}</p>
          <p><strong>Email:</strong> ${data.contact_email || '-'}</p>
          <p><strong>Phone:</strong> ${data.contact_phone || '-'}</p>
        </div>

        <div class="section">
          <h2>Plan Details</h2>
          <p><strong>Scheme:</strong> ${data.plan?.scheme || '-'}</p>
          <p><strong>Plan:</strong> ${data.plan?.name || '-'}</p>
          <p><strong>Tier Level:</strong> ${data.plan?.tier || '-'}</p>
        </div>

        <div class="section">
          <h2>Covered Members</h2>
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Age</th>
                <th>Gender</th>
                <th>Relationship</th>
                <th class="text-right">Premium</th>
              </tr>
            </thead>
            <tbody>
              ${
                data.members
                  ?.map(
                    (m: any) => `
                <tr>
                  <td>${m.name}</td>
                  <td>${m.age}</td>
                  <td>${m.gender === 'M' ? 'Male' : m.gender === 'F' ? 'Female' : '-'}</td>
                  <td>${m.relationship || 'Principal'}</td>
                  <td class="text-right">${this.formatCurrency(
                    m.premium,
                    data.premium_breakdown?.currency
                  )}</td>
                </tr>
              `
                  )
                  .join('') || ''
              }
            </tbody>
          </table>
        </div>

        ${
          data.addons?.length > 0
            ? `
        <div class="section">
          <h2>Add-ons</h2>
          <table>
            <thead>
              <tr>
                <th>Add-on</th>
                <th class="text-right">Premium</th>
              </tr>
            </thead>
            <tbody>
              ${data.addons
                .map(
                  (a: any) => `
                <tr>
                  <td>${a.name}</td>
                  <td class="text-right">${this.formatCurrency(
                    a.premium,
                    data.premium_breakdown?.currency
                  )}</td>
                </tr>
              `
                )
                .join('')}
            </tbody>
          </table>
        </div>
        `
            : ''
        }

        <div class="section">
          <h2>Premium Breakdown</h2>
          <table>
            <tr>
              <td>Base Premium</td>
              <td class="text-right">${this.formatCurrency(
                data.premium_breakdown?.base_premium,
                data.premium_breakdown?.currency
              )}</td>
            </tr>
            ${
              data.premium_breakdown?.addon_premium > 0
                ? `
            <tr>
              <td>Add-on Premium</td>
              <td class="text-right">${this.formatCurrency(
                data.premium_breakdown?.addon_premium,
                data.premium_breakdown?.currency
              )}</td>
            </tr>
            `
                : ''
            }
            ${
              data.premium_breakdown?.loading_amount > 0
                ? `
            <tr>
              <td>Loadings</td>
              <td class="text-right">${this.formatCurrency(
                data.premium_breakdown?.loading_amount,
                data.premium_breakdown?.currency
              )}</td>
            </tr>
            `
                : ''
            }
            ${
              data.premium_breakdown?.discount_amount > 0
                ? `
            <tr>
              <td>Discounts</td>
              <td class="text-right">-${this.formatCurrency(
                data.premium_breakdown?.discount_amount,
                data.premium_breakdown?.currency
              )}</td>
            </tr>
            `
                : ''
            }
            <tr>
              <td>Subtotal</td>
              <td class="text-right">${this.formatCurrency(
                data.premium_breakdown?.total_premium,
                data.premium_breakdown?.currency
              )}</td>
            </tr>
            ${
              data.premium_breakdown?.tax_amount > 0
                ? `
            <tr>
              <td>Tax</td>
              <td class="text-right">${this.formatCurrency(
                data.premium_breakdown?.tax_amount,
                data.premium_breakdown?.currency
              )}</td>
            </tr>
            `
                : ''
            }
            <tr class="total-row">
              <td>Total Premium</td>
              <td class="text-right">${this.formatCurrency(
                data.premium_breakdown?.gross_premium,
                data.premium_breakdown?.currency
              )}</td>
            </tr>
            <tr>
              <td colspan="2"><em>Billing Frequency: ${this.formatBillingFrequency(
                data.premium_breakdown?.billing_frequency
              )}</em></td>
            </tr>
          </table>
        </div>

        <div class="section">
          <h2>Policy Period</h2>
          <p><strong>Term:</strong> ${data.policy_details?.policy_term_months} months</p>
          <p><strong>Start Date:</strong> ${this.formatDate(
            data.policy_details?.proposed_start_date
          )}</p>
          <p><strong>End Date:</strong> ${this.formatDate(
            data.policy_details?.proposed_end_date
          )}</p>
        </div>

        <div class="footer">
          <p>This quote is valid until ${this.formatDate(
            data.valid_until
          )}. Terms and conditions apply.</p>
          <p>Generated on ${new Date().toLocaleString()}</p>
        </div>
      </body>
      </html>
    `;
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

  private formatBillingFrequency(frequency: string): string {
    const map: Record<string, string> = {
      monthly: 'Monthly',
      quarterly: 'Quarterly',
      semi_annual: 'Semi-Annual',
      annual: 'Annual',
    };
    return map[frequency] || frequency;
  }
}
