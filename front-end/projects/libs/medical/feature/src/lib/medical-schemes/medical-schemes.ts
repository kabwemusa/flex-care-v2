// @medical/feature/medical-schemes.component.ts
import { Component, OnInit, inject } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { SchemeStore } from 'medical-data';
import { MedicalSchemeDialog } from 'medical-ui';
import { MatTableModule } from '@angular/material/table';
import { MatMenuModule } from '@angular/material/menu';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { CommonModule } from '@angular/common';
import { LibSkeleton } from 'shared';
import { FeedbackService } from 'shared';

export interface MedicalScheme {
  id?: number;
  name: string;
  slug: string;
  is_active: boolean;
  description?: string;
  plans_count?: number;
}

@Component({
  selector: 'lib-medical-schemes',
  standalone: true,
  imports: [
    CommonModule,
    MatTableModule,
    MatMenuModule,
    MatIconModule,
    MatButtonModule,
    LibSkeleton,
  ],
  templateUrl: './medical-schemes.html',
})
export class MedicalSchemes implements OnInit {
  public store = inject(SchemeStore);
  private dialog = inject(MatDialog);
  private feedback = inject(FeedbackService);

  displayedColumns: string[] = ['status', 'name', 'plans', 'actions'];

  ngOnInit() {
    this.store.loadAll();
  }

  openCreateModal(scheme?: any) {
    const dialogRef = this.dialog.open(MedicalSchemeDialog, {
      width: '100%',
      maxWidth: '500px',
      data: scheme,
      panelClass: 'responsive-dialog',
    });

    dialogRef.afterClosed().subscribe(async (result) => {
      if (result) {
        if (scheme) {
          this.store.updateScheme(scheme.id, result).subscribe({
            next: () => this.feedback.success('Scheme update successfully'),
            error: () => this.feedback.error('Failed to update scheme'),
          });
        } else {
          this.store.addScheme(result).subscribe({
            next: () => this.feedback.success('Scheme created successfully'),
            error: () => this.feedback.error('Failed to create scheme'),
          });
        }
      }
    });
  }

  toggleStatus(scheme: any) {
    this.store.updateScheme(scheme.id, { is_active: !scheme.is_active }).subscribe();
  }

  // deleteScheme(id: number) {
  //   if (confirm('Are you sure?')) {
  //     this.store.deleteScheme(id).subscribe();
  //   }
  // }

  async deleteScheme(id: number) {
    const confirmed = await this.feedback.confirm(
      'Delete Scheme?',
      'This will permanently remove this umbrella product and all associated plans.'
    );

    if (confirmed) {
      this.store.deleteScheme(id).subscribe({
        next: () => this.feedback.success('Scheme deleted successfully'),
        error: () => this.feedback.error('Failed to delete scheme'),
      });
    }
  }
}
