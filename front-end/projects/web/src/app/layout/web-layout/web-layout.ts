import { Component, signal, HostListener, PLATFORM_ID, inject } from '@angular/core';
import { RouterModule } from '@angular/router';
import { isPlatformBrowser } from '@angular/common';
import { ToastComponent } from '../../components/toast/toast.component';

@Component({
  selector: 'app-web-layout',
  standalone: true,
  imports: [RouterModule, ToastComponent],
  templateUrl: './web-layout.html',
  styles: [
    `
      :host {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
      }
      main {
        flex: 1;
      }
    `,
  ],
})
export class WebLayout {
  scrolled = signal(false);
  mobileMenuOpen = signal(false);

  private platformId = inject(PLATFORM_ID);

  @HostListener('window:scroll')
  onScroll() {
    if (isPlatformBrowser(this.platformId)) {
      this.scrolled.set(window.scrollY > 40);
    }
  }

  toggleMobileMenu() {
    this.mobileMenuOpen.update(v => !v);
  }

  closeMobileMenu() {
    this.mobileMenuOpen.set(false);
  }
}
