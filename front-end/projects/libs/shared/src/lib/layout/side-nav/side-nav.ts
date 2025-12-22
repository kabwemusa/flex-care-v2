import { Component, Input, Output, EventEmitter, input, Signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

export interface NavItem {
  href: string;
  label: string;
  icon: string;
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
    `,
  ],
})
export class SideNav {
  @Input() collapsed = false;
  @Input({ required: true }) items!: Signal<NavItem[]>;
  @Output() toggle = new EventEmitter<void>();
}
