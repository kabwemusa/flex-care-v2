import {
  Directive,
  Input,
  TemplateRef,
  ViewContainerRef,
  inject,
  OnInit,
  OnDestroy,
} from '@angular/core';
import { AuthService } from 'core-auth';
import { Subscription, of } from 'rxjs';

/**
 * Permission directive to conditionally show/hide elements based on user permissions.
 *
 * Usage:
 * ```html
 * <!-- Single permission -->
 * <button *libPermission="'medical.schemes.create'">Create</button>
 *
 * <!-- Multiple permissions (ANY) -->
 * <button *libPermission="['medical.schemes.update', 'medical.schemes.delete']; mode: 'any'">Modify</button>
 *
 * <!-- Multiple permissions (ALL) -->
 * <div *libPermission="['medical.policies.view', 'medical.policies.update']; mode: 'all'">...</div>
 * ```
 */
@Directive({
  selector: '[libPermission]',
  standalone: true,
})
export class PermissionDirective implements OnInit, OnDestroy {
  private authService = inject(AuthService);
  private templateRef = inject(TemplateRef<any>);
  private viewContainer = inject(ViewContainerRef);
  private subscription?: Subscription;

  @Input('libPermission') permission!: string | string[];
  @Input('libPermissionMode') mode: 'any' | 'all' = 'all';

  ngOnInit() {
    this.updateView();

    // Subscribe to auth changes to update view when permissions change
    // (e.g., after login, logout, or permission updates)
    // permissions() returns a synchronous PermissionsByGuard; wrap it in an observable
    this.subscription = of(this.authService.permissions()).subscribe(() => {
      this.updateView();
    });

    // TODO: if AuthService exposes a real permissions observable, replace the of(...) wrapper with that observable
  }

  ngOnDestroy() {
    this.subscription?.unsubscribe();
  }

  private updateView() {
    const hasPermission = this.checkPermission();

    if (hasPermission) {
      // Show element
      if (this.viewContainer.length === 0) {
        this.viewContainer.createEmbeddedView(this.templateRef);
      }
    } else {
      // Hide element
      this.viewContainer.clear();
    }
  }

  private checkPermission(): boolean {
    if (Array.isArray(this.permission)) {
      return this.mode === 'any'
        ? this.authService.isAllowedAny(this.permission)
        : this.authService.isAllowedAll(this.permission);
    }
    return this.authService.isAllowed(this.permission);
  }
}
