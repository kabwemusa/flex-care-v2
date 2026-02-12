import {
  Component,
  Input,
  signal,
  OnInit,
  OnDestroy,
  PLATFORM_ID,
  inject,
  HostListener,
} from '@angular/core';
import { isPlatformBrowser } from '@angular/common';

@Component({
  selector: 'app-slider',
  standalone: true,
  templateUrl: './slider.html',
  styles: [`
    :host { display: block; position: relative; }

    .slider-track {
      display: flex;
      transition: transform 400ms cubic-bezier(0.4, 0, 0.2, 1);
      will-change: transform;
    }

    .slider-slide {
      flex: 0 0 100%;
      min-width: 0;
    }

    .slider-dot {
      width: 8px;
      height: 8px;
      border-radius: 9999px;
      background: var(--fc-gray-300);
      transition: all 200ms ease;
      cursor: pointer;
      border: none;
      padding: 0;
    }

    .slider-dot.active {
      background: var(--fc-primary-600);
      width: 24px;
    }

    .slider-arrow {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: white;
      border: 1px solid var(--fc-gray-200);
      box-shadow: var(--fc-shadow-md);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 200ms ease;
      z-index: 10;
      color: var(--fc-gray-600);
    }

    .slider-arrow:hover {
      background: var(--fc-gray-50);
      box-shadow: var(--fc-shadow-lg);
    }

    .slider-arrow.prev { left: -20px; }
    .slider-arrow.next { right: -20px; }

    @media (max-width: 768px) {
      .slider-arrow { display: none; }
    }
  `],
})
export class SliderComponent implements OnInit, OnDestroy {
  @Input() autoPlayMs = 5000;
  @Input() showDots = true;
  @Input() showArrows = true;
  @Input() slideCount = 1;

  activeIndex = signal(0);
  totalSlides = signal(0);

  private intervalId: any;
  private paused = false;
  private touchStartX = 0;
  private platformId = inject(PLATFORM_ID);

  @HostListener('mouseenter') onMouseEnter() { this.pause(); }
  @HostListener('mouseleave') onMouseLeave() { this.resume(); }

  setTotal(count: number) {
    this.totalSlides.set(count);
  }

  ngOnInit() {
    if (this.autoPlayMs > 0 && isPlatformBrowser(this.platformId)) {
      this.startAutoPlay();
    }
  }

  ngOnDestroy() {
    this.stopAutoPlay();
  }

  next() {
    const total = this.totalSlides();
    if (total === 0) return;
    this.activeIndex.set((this.activeIndex() + 1) % total);
  }

  prev() {
    const total = this.totalSlides();
    if (total === 0) return;
    this.activeIndex.set((this.activeIndex() - 1 + total) % total);
  }

  goTo(index: number) {
    this.activeIndex.set(index);
  }

  onTouchStart(event: TouchEvent) {
    this.touchStartX = event.changedTouches[0]?.clientX ?? 0;
  }

  onTouchEnd(event: TouchEvent) {
    const endX = event.changedTouches[0]?.clientX ?? 0;
    const diff = this.touchStartX - endX;
    if (Math.abs(diff) > 50) {
      diff > 0 ? this.next() : this.prev();
    }
  }

  getTransform(): string {
    return `translateX(-${this.activeIndex() * 100}%)`;
  }

  private pause() { this.paused = true; }
  private resume() {
    this.paused = false;
  }

  private startAutoPlay() {
    this.intervalId = setInterval(() => {
      if (!this.paused) this.next();
    }, this.autoPlayMs);
  }

  private stopAutoPlay() {
    if (this.intervalId) clearInterval(this.intervalId);
  }
}
