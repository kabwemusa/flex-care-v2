import { Component, OnInit, inject } from '@angular/core';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { AddonStore, MedicalAddon } from 'medical-data';
import { MedicalAddonDialog } from 'medical-ui';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { CommonModule } from '@angular/common';
import { FeedbackService } from 'shared';

@Component({
  selector: 'lib-medical-addons',
  standalone: true,
  imports: [CommonModule, MatDialogModule, MatButtonModule, MatIconModule, MatMenuModule],
  templateUrl: './medical-addons.html',
})
export class MedicalAddons implements OnInit {
  public store = inject(AddonStore);
  private dialog = inject(MatDialog);
  private feedback = inject(FeedbackService);

  ngOnInit() {
    this.store.loadAll();
  }

  openDialog(addon?: MedicalAddon) {
    const ref = this.dialog.open(MedicalAddonDialog, {
      width: '450px',
      data: addon,
    });

    ref.afterClosed().subscribe((result) => {
      if (result) {
        this.store.upsert({ ...result, id: addon?.id }).subscribe({
          next: () => this.feedback.success(`Addon ${result ? 'updated' : 'created'} successfully`),
          error: (err) => this.feedback.error('Operation failed'),
        });
      }
    });
  }

  async delete(id: number) {
    if (await this.feedback.confirm('Delete Addon?', 'This will remove the addon.')) {
      this.store.delete(id).subscribe({
        next: () => this.feedback.success('Addon deleted successfully'),
        error: () => this.feedback.error('Failed to delete Addon'),
      });
    }
  }
}
