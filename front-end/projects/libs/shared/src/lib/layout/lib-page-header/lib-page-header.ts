import { Component, input } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'lib-page-header',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
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
}
