// @medical/shared/components/skeleton.component.ts
import { Component, input } from '@angular/core';

@Component({
  selector: 'lib-skeleton',
  standalone: true,
  template: `
    <div
      [class]="classes()"
      class="overflow-hidden bg-slate-200 relative before:absolute before:inset-0 before:-translate-x-full before:animate-[shimmer_2s_infinite] before:bg-gradient-to-r before:from-transparent before:via-white/20 before:to-transparent"
    ></div>
  `,
  styles: `
    @keyframes shimmer {
      100% { transform: translateX(100%); }
    }
  `,
})
export class LibSkeleton {
  // Input signal for custom tailwind classes (height, width, rounded-ness)
  classes = input<string>('h-4 w-full rounded-md');
}
