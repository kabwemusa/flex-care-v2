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
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        @if (showBreadcrumb() && breadcrumbItems().length > 0) {
        <nav class="mb-1.5 flex items-center gap-0.5 text-xs text-slate-400">
          @for (item of breadcrumbItems(); track item.label; let last = $last) {
            @if (item.link && !last) {
              <a [routerLink]="item.link"
                 class="hover:text-slate-700 transition-colors rounded px-1 py-0.5 hover:bg-slate-100">
                {{ item.label }}
              </a>
            } @else {
              <span [class.text-slate-700]="last" [class.font-medium]="last"
                    class="px-1 py-0.5">{{ item.label }}</span>
            }
            @if (!last) {
              <span class="text-slate-300 select-none">/</span>
            }
          }
        </nav>
        }
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
          {{ title() }}
        </h1>
        @if (subtitle()) {
        <p class="mt-1 text-sm text-slate-500">
          {{ subtitle() }}
        </p>
        }
      </div>

      <div class="flex items-center gap-3 shrink-0">
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
