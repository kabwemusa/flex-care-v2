import { Component, inject } from '@angular/core';
import { NgClass } from '@angular/common';
import { ToastService, Toast, ToastType } from '../../services/toast.service';

@Component({
  selector: 'app-toast',
  standalone: true,
  imports: [NgClass],
  templateUrl: './toast.component.html',
})
export class ToastComponent {
  readonly toast = inject(ToastService);

  dismiss(id: string): void {
    this.toast.dismiss(id);
  }

  iconFor(type: ToastType): string {
    switch (type) {
      case 'success': return 'check_circle';
      case 'error':   return 'error';
      case 'warning': return 'warning';
      default:        return 'info';
    }
  }

  containerClasses(type: ToastType): string {
    switch (type) {
      case 'success': return 'bg-white border-l-4 border-emerald-500';
      case 'error':   return 'bg-white border-l-4 border-red-500';
      case 'warning': return 'bg-white border-l-4 border-amber-500';
      default:        return 'bg-white border-l-4 border-blue-500';
    }
  }

  iconClasses(type: ToastType): string {
    switch (type) {
      case 'success': return 'text-emerald-500';
      case 'error':   return 'text-red-500';
      case 'warning': return 'text-amber-500';
      default:        return 'text-blue-500';
    }
  }

  trackById(_: number, t: Toast): string {
    return t.id;
  }
}
