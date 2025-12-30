import { Component, Input, Output, EventEmitter, input, Signal, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

// 1. Updated Interface to support nesting
export interface NavItem {
  href?: string; // Optional because a parent might just be a container
  label: string;
  icon?: string;
  children?: NavItem[];
}

@Component({
  selector: 'lib-side-nav',
  standalone: true,
  imports: [CommonModule, RouterModule, MatTooltipModule, MatButtonModule, MatIconModule],
  templateUrl: './side-nav.html',
  styles: [
    `
      :host {
        display: contents;
      }
      /* Smooth rotation for the expansion chevron */
      .rotate-180 {
        transform: rotate(180deg);
      }
    `,
  ],
})
export class SideNav {
  @Input() collapsed = false;
  @Input({ required: true }) items!: Signal<NavItem[]>;
  @Output() toggle = new EventEmitter<void>();
  constructor(private router: Router) {}

  // Track which parent items are expanded
  expandedItems = signal<Set<string>>(new Set());

  toggleExpand(label: string) {
    if (this.collapsed) {
      this.toggle.emit(); // Auto-expand sidebar if user tries to open a submenu
      // Give it a tick to expand, then open the menu
      setTimeout(() => this.updateSet(label), 100);
    } else {
      this.updateSet(label);
    }
  }

  private updateSet(label: string) {
    this.expandedItems.update((set) => {
      const newSet = new Set(set);
      if (newSet.has(label)) {
        newSet.delete(label);
      } else {
        newSet.add(label);
      }
      return newSet;
    });
  }

  isExpanded(label: string): boolean {
    return this.expandedItems().has(label);
  }

  hasActiveChild(item: any): boolean {
    if (!item.children || item.children.length === 0) {
      return false;
    }

    // Check if any child route is currently active
    return item.children.some((child: any) => {
      // You'll need to inject Router in your constructor
      // constructor(private router: Router) {}
      return this.router.isActive(child.href, {
        paths: 'exact',
        queryParams: 'exact',
        fragment: 'ignored',
        matrixParams: 'ignored',
      });
    });
  }
}
