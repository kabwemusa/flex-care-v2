import { Component, signal, OnInit, OnDestroy, HostListener } from '@angular/core';
import { Router, RouterModule, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs';
import { MemberAuthService } from '../../services/member-auth.service';

interface NavItem {
  label: string;
  icon: string;
  route: string;
}

@Component({
  selector: 'app-portal-layout',
  standalone: true,
  imports: [RouterModule],
  templateUrl: './portal-layout.html',
})
export class PortalLayout implements OnInit, OnDestroy {
  mobileMenuOpen = signal(false);
  currentRoute = signal('');
  scrolled = signal(false);

  navItems: NavItem[] = [
    { label: 'Dashboard', icon: 'dashboard', route: '/portal/dashboard' },
    { label: 'My Policy', icon: 'shield', route: '/portal/policy' },
    { label: 'Claims', icon: 'receipt_long', route: '/portal/claims' },
    { label: 'ID Cards', icon: 'badge', route: '/portal/id-cards' },
    { label: 'Profile', icon: 'person', route: '/portal/profile' },
  ];

  constructor(
    public auth: MemberAuthService,
    private router: Router
  ) {}

  ngOnInit() {
    // Track current route
    this.currentRoute.set(this.router.url);
    this.router.events
      .pipe(filter((e) => e instanceof NavigationEnd))
      .subscribe((e) => {
        this.currentRoute.set((e as NavigationEnd).url);
        this.mobileMenuOpen.set(false);
      });

    // Redirect if not authenticated
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

  toggleMobileMenu() {
    this.mobileMenuOpen.update((v) => !v);
  }

  logout() {
    this.auth.logout();
  }

  closeMobileMenu() {
    this.mobileMenuOpen.set(false);
  }

  @HostListener('window:scroll')
  onScroll() {
    this.scrolled.set(window.scrollY > 10);
  }

  ngOnDestroy() {
    // Cleanup if needed
  }
}
