import { Component, signal, OnInit, HostListener, inject, DestroyRef } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router, RouterModule, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs';
import { MemberAuthService } from '../../services/member-auth.service';
import { ToastComponent } from '../../components/toast/toast.component';

interface NavItem {
  label: string;
  icon: string;
  route: string;
}

@Component({
  selector: 'app-portal-layout',
  standalone: true,
  imports: [RouterModule, ToastComponent],
  templateUrl: './portal-layout.html',
})
export class PortalLayout implements OnInit {
  private readonly destroyRef = inject(DestroyRef);

  mobileMenuOpen = signal(false);
  currentRoute   = signal('');
  scrolled       = signal(false);

  navItems: NavItem[] = [
    { label: 'Dashboard', icon: 'dashboard',    route: '/portal/dashboard' },
    { label: 'My Policy', icon: 'shield',        route: '/portal/policy' },
    { label: 'Claims',    icon: 'receipt_long',  route: '/portal/claims' },
    { label: 'ID Cards',  icon: 'badge',         route: '/portal/id-cards' },
    { label: 'Profile',   icon: 'person',        route: '/portal/profile' },
  ];

  constructor(
    public auth: MemberAuthService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.currentRoute.set(this.router.url);

    // Auto-close mobile menu and track active route on navigation.
    this.router.events
      .pipe(
        filter((e) => e instanceof NavigationEnd),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe((e) => {
        this.currentRoute.set((e as NavigationEnd).url);
        this.mobileMenuOpen.set(false);
      });

    if (!this.auth.isAuthenticated()) {
      this.router.navigate(['/login']);
    }
  }

  isActive(route: string): boolean {
    const current = this.currentRoute();
    if (route === '/portal/dashboard') {
      return current === '/portal' || current === '/portal/' || current === '/portal/dashboard';
    }
    return current.startsWith(route);
  }

  toggleMobileMenu(): void {
    this.mobileMenuOpen.update((v) => !v);
  }

  logout(): void {
    this.auth.logout();
  }

  closeMobileMenu(): void {
    this.mobileMenuOpen.set(false);
  }

  @HostListener('window:scroll')
  onScroll(): void {
    this.scrolled.set(window.scrollY > 10);
  }
}
