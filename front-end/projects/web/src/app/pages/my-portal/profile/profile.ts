import { Component, signal, OnInit, inject, DestroyRef } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { DatePipe } from '@angular/common';
import { MemberPortalService, ProfileData } from '../../../services/member-portal.service';
import { MemberAuthService } from '../../../services/member-auth.service';
import { ToastService } from '../../../services/toast.service';

@Component({
  selector: 'app-portal-profile',
  standalone: true,
  imports: [RouterModule, FormsModule, DatePipe],
  templateUrl: './profile.html',
})
export class PortalProfile implements OnInit {
  private readonly portalService = inject(MemberPortalService);
  private readonly authService   = inject(MemberAuthService);
  private readonly toast         = inject(ToastService);
  private readonly destroyRef    = inject(DestroyRef);

  profile  = signal<ProfileData | null>(null);
  loading  = signal(true);
  saving   = signal(false);
  editMode = signal(false);

  // Password change modal
  showPasswordModal = signal(false);
  passwordForm = signal({
    current_password: '',
    new_password:     '',
    confirm_password: '',
  });
  /** Inline error only — kept in the modal so context is immediate */
  passwordError  = signal('');
  passwordSaving = signal(false);

  ngOnInit(): void {
    this.loadProfile();
  }

  loadProfile(): void {
    this.loading.set(true);

    this.portalService.getProfile()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.profile.set(res.data);
          this.loading.set(false);
        },
        error: (err) => {
          this.loading.set(false);
          this.toast.error(err.error?.message ?? 'Failed to load profile. Please refresh.');
        },
      });
  }

  updateField(field: keyof ProfileData, value: string): void {
    this.profile.update((p) => (p ? { ...p, [field]: value } : p));
  }

  saveProfile(): void {
    const p = this.profile();
    if (!p) return;

    this.saving.set(true);

    this.portalService
      .updateProfile({
        phone:                    p.phone,
        address:                  p.address,
        emergency_contact_name:   p.emergency_contact_name,
        emergency_contact_phone:  p.emergency_contact_phone,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.saving.set(false);
          this.editMode.set(false);
          this.toast.success('Profile updated successfully.');
        },
        error: (err) => {
          this.saving.set(false);
          this.toast.error(err.error?.message ?? 'Failed to update profile. Please try again.');
        },
      });
  }

  cancelEdit(): void {
    this.editMode.set(false);
    this.loadProfile();
  }

  // ── Password Modal ────────────────────────────────────────────────────────

  openPasswordModal(): void {
    this.passwordForm.set({ current_password: '', new_password: '', confirm_password: '' });
    this.passwordError.set('');
    this.showPasswordModal.set(true);
  }

  closePasswordModal(): void {
    this.showPasswordModal.set(false);
  }

  updatePasswordField(field: string, value: string): void {
    this.passwordForm.update((f) => ({ ...f, [field]: value }));
  }

  changePassword(): void {
    const form = this.passwordForm();

    if (form.new_password !== form.confirm_password) {
      this.passwordError.set('Passwords do not match.');
      return;
    }
    if (form.new_password.length < 8) {
      this.passwordError.set('Password must be at least 8 characters.');
      return;
    }

    this.passwordSaving.set(true);
    this.passwordError.set('');

    this.authService.setPassword(form.new_password, form.confirm_password)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.passwordSaving.set(false);
          this.closePasswordModal();
          this.toast.success('Password updated successfully.');
        },
        error: (err) => {
          this.passwordSaving.set(false);
          this.passwordError.set(err.error?.message ?? 'Failed to update password.');
        },
      });
  }

  logout(): void {
    this.authService.logout();
  }
}
