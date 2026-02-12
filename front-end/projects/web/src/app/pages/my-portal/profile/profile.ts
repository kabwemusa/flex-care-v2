import { Component, signal, OnInit } from '@angular/core';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { DatePipe } from '@angular/common';
import { MemberPortalService, ProfileData } from '../../../services/member-portal.service';
import { MemberAuthService } from '../../../services/member-auth.service';

@Component({
  selector: 'app-portal-profile',
  standalone: true,
  imports: [RouterModule, FormsModule, DatePipe],
  templateUrl: './profile.html',
})
export class PortalProfile implements OnInit {
  profile = signal<ProfileData | null>(null);
  loading = signal(true);
  saving = signal(false);
  error = signal('');
  success = signal('');
  editMode = signal(false);

  // Password change
  showPasswordModal = signal(false);
  passwordForm = signal({
    current_password: '',
    new_password: '',
    confirm_password: '',
  });
  passwordError = signal('');
  passwordSaving = signal(false);

  constructor(
    private portalService: MemberPortalService,
    private authService: MemberAuthService
  ) {}

  ngOnInit() {
    this.loadProfile();
  }

  loadProfile() {
    this.loading.set(true);
    this.error.set('');

    this.portalService.getProfile().subscribe({
      next: (res) => {
        this.profile.set(res.data);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err.error?.message ?? 'Failed to load profile');
        this.loading.set(false);
      },
    });
  }

  updateField(field: string, value: string) {
    this.profile.update((p) => (p ? { ...p, [field]: value } : p));
  }

  saveProfile() {
    const p = this.profile();
    if (!p) return;

    this.saving.set(true);
    this.error.set('');
    this.success.set('');

    this.portalService
      .updateProfile({
        phone: p.phone,
        address: p.address,
        emergency_contact_name: p.emergency_contact_name,
        emergency_contact_phone: p.emergency_contact_phone,
      })
      .subscribe({
        next: () => {
          this.success.set('Profile updated successfully');
          this.saving.set(false);
          this.editMode.set(false);
        },
        error: (err) => {
          this.error.set(err.error?.message ?? 'Failed to update profile');
          this.saving.set(false);
        },
      });
  }

  cancelEdit() {
    this.editMode.set(false);
    this.loadProfile();
  }

  openPasswordModal() {
    this.passwordForm.set({
      current_password: '',
      new_password: '',
      confirm_password: '',
    });
    this.passwordError.set('');
    this.showPasswordModal.set(true);
  }

  closePasswordModal() {
    this.showPasswordModal.set(false);
  }

  updatePasswordField(field: string, value: string) {
    this.passwordForm.update((f) => ({ ...f, [field]: value }));
  }

  changePassword() {
    const form = this.passwordForm();

    if (form.new_password !== form.confirm_password) {
      this.passwordError.set('Passwords do not match');
      return;
    }

    if (form.new_password.length < 8) {
      this.passwordError.set('Password must be at least 8 characters');
      return;
    }

    this.passwordSaving.set(true);
    this.passwordError.set('');

    this.authService.setPassword(form.new_password, form.confirm_password).subscribe({
      next: () => {
        this.passwordSaving.set(false);
        this.closePasswordModal();
        this.success.set('Password updated successfully');
      },
      error: (err) => {
        this.passwordError.set(err.error?.message ?? 'Failed to update password');
        this.passwordSaving.set(false);
      },
    });
  }

  logout() {
    this.authService.logout();
  }
}
