// @medical/shared/services/feedback.service.ts
import { inject, Injectable } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatDialog } from '@angular/material/dialog';
import { ConfirmationDialog } from 'shared';
import { firstValueFrom } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class FeedbackService {
  private snackbar = inject(MatSnackBar);
  private dialog = inject(MatDialog);

  success(message: string) {
    this.snackbar.open(message, 'Close', {
      duration: 4000,
      panelClass: ['success-snackbar'], // Styled via Tailwind @layer
      horizontalPosition: 'right',
      verticalPosition: 'top',
    });
  }

  error(message: string) {
    this.snackbar.open(message, 'Close', {
      duration: 6000,
      panelClass: ['error-snackbar'],
      horizontalPosition: 'right',
      verticalPosition: 'top',
    });
  }

  async confirm(title: string, message: string): Promise<boolean> {
    const dialogRef = this.dialog.open(ConfirmationDialog, {
      width: '400px',
      data: { title, message },
    });
    return (await firstValueFrom(dialogRef.afterClosed())) || false;
  }
}
