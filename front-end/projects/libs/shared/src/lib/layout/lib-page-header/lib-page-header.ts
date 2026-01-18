import { Component, input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

export interface BreadcrumbItem {
  label: string;
  link?: string;
}

@Component({
  selector: 'lib-page-header',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        @if (showBreadcrumb() && breadcrumbItems().length > 0) {
        <nav class="mb-2 flex items-center text-sm text-muted-foreground">
          @for (item of breadcrumbItems(); track item.label; let last = $last) {
            @if (item.link && !last) {
              <a [routerLink]="item.link" class="hover:text-foreground transition-colors">{{ item.label }}</a>
            } @else {
              <span [class.text-foreground]="last" [class.font-medium]="last">{{ item.label }}</span>
            }
            @if (!last) {
              <span class="mx-2">/</span>
            }
          }
        </nav>
        }
        <h1 class="text-2xl font-semibold tracking-tight text-foreground">
          {{ title() }}
        </h1>
        @if (subtitle()) {
        <p class="mt-1 text-sm text-muted-foreground">
          {{ subtitle() }}
        </p>
        }
      </div>

      <div class="flex items-center gap-3">
        <ng-content select="[actions]"></ng-content>
      </div>
    </div>
  `,
})
export class PageHeaderComponent {
  title = input.required<string>();
  subtitle = input<string>();
  showBreadcrumb = input<boolean>(false);
  breadcrumbItems = input<BreadcrumbItem[]>([]);
}
