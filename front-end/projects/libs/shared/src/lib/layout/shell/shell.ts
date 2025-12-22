import { Component, Input, signal, Signal } from '@angular/core';
import { RouterModule } from '@angular/router';
import { NavItem, SideNav } from '../side-nav/side-nav';
import { TopNav } from '../top-nav/top-nav';

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
    `,
  ],
})
export class Shell {
  @Input({ required: true }) navItems!: Signal<NavItem[]>;
  sidebarCollapsed = signal(false);

  handleToggleSidebar(): void {
    this.sidebarCollapsed.update((value) => !value);
  }
}
