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
import { CdkNoDataRow } from '@angular/cdk/table';

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
    // 1. Defined Colors & Style Variables for consistency
    const colors = {
      primary: '#1e3a8a', // Deep Blue
      secondary: '#3b82f6', // Bright Blue
      light: '#f8fafc', // Very Light Gray
      border: '#e2e8f0', // Border Gray
      text: '#334155', // Slate Text
      textLight: '#64748b', // Muted Text
    };

    return `
      <!DOCTYPE html>
      <html>
      <head>
        <title>Quote - ${data.application_number}</title>
        <meta charset="utf-8">
        <style>
          /* RESET & BASE */
          * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
          body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 0; background: #fff; color: ${
            colors.text
          }; font-size: 12px; line-height: 1.4; }
          
          /* PAGE LAYOUT (A4 Aspect Ratio Optimized) */
          .page { max-width: 210mm; margin: 0 auto; padding: 15mm; background: white; min-height: 297mm; position: relative; }
          
          /* UTILITIES */
          .row { display: flex; justify-content: space-between; align-items: flex-start; }
          .col { flex: 1; }
          .text-right { text-align: right; }
          .text-center { text-align: center; }
          .font-bold { font-weight: 700; }
          .text-sm { font-size: 11px; color: ${colors.textLight}; }
          .mb-4 { margin-bottom: 1rem; }
          .mb-8 { margin-bottom: 2rem; }
          .uppercase { text-transform: uppercase; letter-spacing: 0.05em; }

          /* HEADER */
          .header { border-bottom: 2px solid ${
            colors.primary
          }; padding-bottom: 20px; margin-bottom: 30px; }
          .brand-title { color: ${colors.primary}; font-size: 24px; font-weight: 800; margin: 0; }
          .quote-badge { background: ${colors.light}; color: ${
      colors.primary
    }; padding: 8px 16px; border-radius: 4px; font-weight: bold; border: 1px solid ${
      colors.border
    }; display: inline-block; }

          /* INFO GRID */
          .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; background: ${
            colors.light
          }; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid ${
      colors.border
    }; }
          .info-label { color: ${
            colors.textLight
          }; font-size: 10px; text-transform: uppercase; margin-bottom: 4px; font-weight: 600; }
          .info-value { font-size: 14px; font-weight: 600; color: ${colors.primary}; }
          
          /* TABLES */
          table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
          th { background: ${
            colors.primary
          }; color: white; text-align: left; padding: 10px 12px; font-weight: 600; text-transform: uppercase; font-size: 10px; }
          td { padding: 10px 12px; border-bottom: 1px solid ${colors.border}; }
          tr:last-child td { border-bottom: none; }
          tr:nth-child(even) { background-color: ${colors.light}; }

          /* FINANCIALS CARD */
          .financials-container { display: flex; justify-content: flex-end; margin-top: 20px; }
          .financials-box { width: 350px; background: ${
            colors.light
          }; border-radius: 8px; overflow: hidden; border: 1px solid ${colors.border}; }
          .fin-row { display: flex; justify-content: space-between; padding: 10px 15px; border-bottom: 1px solid ${
            colors.border
          }; }
          .fin-row:last-child { border-bottom: none; }
          .fin-row.total { background: ${
            colors.primary
          }; color: white; font-size: 16px; font-weight: bold; padding: 15px; }
          .fin-label { font-size: 12px; }
          
          /* FOOTER */
          .footer { position: absolute; bottom: 15mm; left: 15mm; right: 15mm; text-align: center; border-top: 1px solid ${
            colors.border
          }; padding-top: 15px; font-size: 10px; color: ${colors.textLight}; }
        </style>
      </head>
      <body>
        <div class="page">
          
          <div class="header row">
            <div class="col">
              <h1 class="brand-title">MEDICAL INSURANCE</h1>
              <div class="text-sm" style="margin-top: 5px;">Comprehensive Health Solutions</div>
            </div>
            <div class="col text-right">
              <div class="quote-badge">
                <div class="info-label" style="text-align: right;">QUOTE REFERENCE</div>
                <div style="font-size: 16px;">${data.application_number}</div>
              </div>
            </div>
          </div>

          <div class="info-grid">
            <div>
              <div class="mb-4">
                <div class="info-label">PREPARED FOR</div>
                <div class="info-value">${data.applicant_name || 'Valued Customer'}</div>
                <div class="text-sm">${data.contact_email || ''}</div>
                <div class="text-sm">${data.contact_phone || ''}</div>
              </div>
              <div>
                <div class="info-label">SCHEME & PLAN</div>
                <div class="info-value">${data.plan?.scheme || ''} — ${data.plan?.name || ''}</div>
                <div class="text-sm">Tier: ${data.plan?.tier || 'Standard'}</div>
              </div>
            </div>
            <div class="text-right">
               <div class="mb-4">
                <div class="info-label">DATE ISSUED</div>
                <div class="info-value">${this.formatDate(data.quote_date)}</div>
              </div>
              <div class="mb-4">
                <div class="info-label">VALID UNTIL</div>
                <div class="info-value" style="color: #ef4444;">${this.formatDate(
                  data.valid_until
                )}</div>
              </div>
              <div>
                <div class="info-label">POLICY TERM</div>
                <div class="info-value">${
                  data.policy_details?.policy_term_months || 12
                } Months</div>
                <div class="text-sm">
                  ${this.formatDate(data.policy_details?.proposed_start_date)} to ${this.formatDate(
      data.policy_details?.proposed_end_date
    )}
                </div>
              </div>
            </div>
          </div>

          <div class="mb-8">
            <h3 class="uppercase text-sm mb-4" style="color: ${
              colors.primary
            }; border-bottom: 1px solid ${colors.border}; padding-bottom: 5px;">Covered Members</h3>
            <table>
              <thead>
                <tr>
                  <th style="width: 30%">Member Name</th>
                  <th style="width: 15%">Relation</th>
                  <th style="width: 15%">Age / Gender</th>
                  <th style="width: 20%">Status</th>
                  <th class="text-right" style="width: 20%">Premium</th>
                </tr>
              </thead>
              <tbody>
                ${
                  data.members
                    ?.map(
                      (m: any) => `
                  <tr>
                    <td><span class="font-bold">${m.name}</span></td>
                    <td>${m.relationship || 'Principal'}</td>
                    <td>${m.age} Yrs / ${m.gender}</td>
                    <td><span style="background: #dcfce7; color: #166534; padding: 2px 6px; border-radius: 4px; font-size: 10px;">Covered</span></td>
                    <td class="text-right font-bold">${this.formatCurrency(
                      m.premium,
                      data.premium_breakdown?.currency
                    )}</td>
                  </tr>
                `
                    )
                    .join('') || '<tr><td colspan="5">No members defined</td></tr>'
                }
              </tbody>
            </table>
          </div>

          ${
            data.addons?.length > 0
              ? `
            <div class="mb-8">
               <h3 class="uppercase text-sm mb-4" style="color: ${
                 colors.primary
               }; border-bottom: 1px solid ${
                  colors.border
                }; padding-bottom: 5px;">Selected Add-ons</h3>
              <table>
                <thead>
                  <tr>
                    <th>Add-on Description</th>
                    <th class="text-right" style="width: 20%">Premium</th>
                  </tr>
                </thead>
                <tbody>
                  ${data.addons
                    .map(
                      (a: any) => `
                    <tr>
                      <td>${a.name}</td>
                      <td class="text-right font-bold">${this.formatCurrency(
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

          <div class="financials-container">
            <div class="financials-box">
              <div class="fin-row">
                <span class="fin-label">Billing Frequency</span>
                <span class="font-bold">${this.formatBillingFrequency(
                  data.premium_breakdown?.billing_frequency
                )}</span>
              </div>
              <div class="fin-row">
                <span class="fin-label">Base Premium</span>
                <span>${this.formatCurrency(
                  data.premium_breakdown?.base_premium,
                  data.premium_breakdown?.currency
                )}</span>
              </div>
              
              ${
                data.premium_breakdown?.addon_premium > 0
                  ? `
              <div class="fin-row">
                <span class="fin-label">Add-ons</span>
                <span>${this.formatCurrency(
                  data.premium_breakdown?.addon_premium,
                  data.premium_breakdown?.currency
                )}</span>
              </div>`
                  : ''
              }

              ${
                data.premium_breakdown?.loading_amount > 0
                  ? `
              <div class="fin-row">
                <span class="fin-label">Risk Loadings</span>
                <span>${this.formatCurrency(
                  data.premium_breakdown?.loading_amount,
                  data.premium_breakdown?.currency
                )}</span>
              </div>`
                  : ''
              }

              ${
                data.premium_breakdown?.discount_amount > 0
                  ? `
              <div class="fin-row" style="color: #166534;">
                <span class="fin-label">Discounts Applied</span>
                <span>-${this.formatCurrency(
                  data.premium_breakdown?.discount_amount,
                  data.premium_breakdown?.currency
                )}</span>
              </div>`
                  : ''
              }

              ${
                data.premium_breakdown?.tax_amount > 0
                  ? `
              <div class="fin-row">
                <span class="fin-label">Tax / VAT</span>
                <span>${this.formatCurrency(
                  data.premium_breakdown?.tax_amount,
                  data.premium_breakdown?.currency
                )}</span>
              </div>`
                  : ''
              }

              <div class="fin-row total">
                <span>TOTAL PAYABLE</span>
                <span>${this.formatCurrency(
                  data.premium_breakdown?.gross_premium,
                  data.premium_breakdown?.currency
                )}</span>
              </div>
            </div>
          </div>

          <div class="footer">
            <p><strong>Terms & Conditions:</strong> This quote is subject to the standard terms and conditions of the Medical Insurance Policy. Final acceptance is subject to underwriting.</p>
            <p>Generated via Medical Portal on ${new Date().toLocaleString()} | Page 1 of 1</p>
          </div>

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
