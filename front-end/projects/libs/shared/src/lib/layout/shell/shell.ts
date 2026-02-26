import { Component, Input, signal, Signal, inject, OnInit, OnDestroy } from '@angular/core';
import {
  RouterModule,
  Router,
  NavigationStart,
  NavigationEnd,
  NavigationCancel,
  NavigationError,
} from '@angular/router';
import { NavItem, SideNav } from '../side-nav/side-nav';
import { TopNav } from '../top-nav/top-nav';
import { Subscription } from 'rxjs';

@Component({
  selector: 'lib-shell',
  standalone: true,
  imports: [RouterModule, SideNav, TopNav],
  templateUrl: './shell.html',
  styles: [
    `
      :host {
        display: contents;
      }

      @keyframes nav-progress {
        0% {
          transform: translateX(-100%) scaleX(0.4);
        }
        60% {
          transform: translateX(20%) scaleX(0.6);
        }
        100% {
          transform: translateX(110%) scaleX(0.4);
        }
      }

      .nav-progress-bar {
        animation: nav-progress 1.2s ease-in-out infinite;
        transform-origin: center;
      }
    `,
  ],
})
export class Shell implements OnInit, OnDestroy {
  @Input({ required: true }) navItems!: Signal<NavItem[]>;
  sidebarCollapsed = signal(false);
  navigating = signal(false);

  private readonly router = inject(Router);
  private routerSub?: Subscription;

  ngOnInit(): void {
    this.routerSub = this.router.events.subscribe((event) => {
      if (event instanceof NavigationStart) {
        this.navigating.set(true);
      } else if (
        event instanceof NavigationEnd ||
        event instanceof NavigationCancel ||
        event instanceof NavigationError
      ) {
        this.navigating.set(false);
      }
    });
  }

  ngOnDestroy(): void {
    this.routerSub?.unsubscribe();
  }

  handleToggleSidebar(): void {
    this.sidebarCollapsed.update((value) => !value);
  }
}
