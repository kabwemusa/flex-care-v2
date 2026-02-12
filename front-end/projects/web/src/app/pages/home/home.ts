import { Component, signal, OnInit } from '@angular/core';
import { RouterModule } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { SliderComponent } from '../../components/slider/slider';

interface FeaturedPlan {
  id: string;
  name: string;
  plan_type: string;
  tier_level: number;
  description: string;
  plan_benefits_count: number;
  plan_addons_count: number;
}

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [RouterModule, SliderComponent],
  templateUrl: './home.html',
})
export class HomePage implements OnInit {
  plans = signal<FeaturedPlan[]>([]);
  plansLoading = signal(true);

  testimonials = [
    {
      name: 'Mary Tembo',
      role: 'Family Plan Member',
      initials: 'MT',
      stars: 5,
      text: 'FlexCare made it so easy to find the right plan for my family. The online claims process saved me hours of paperwork.',
    },
    {
      name: 'James Mwanza',
      role: 'Corporate HR Manager',
      initials: 'JM',
      stars: 5,
      text: 'We switched our company health plan to FlexCare and our employees love the digital ID cards and quick claims turnaround.',
    },
    {
      name: 'Linda Banda',
      role: 'Individual Plan Member',
      initials: 'LB',
      stars: 4,
      text: 'The quote process took less than 2 minutes. I knew exactly what I was paying before I applied. Truly transparent.',
    },
    {
      name: 'David Phiri',
      role: 'Small Business Owner',
      initials: 'DP',
      stars: 5,
      text: 'As a small business owner, covering my team was always complicated. FlexCare simplified everything with their online platform.',
    },
  ];

  healthTips = [
    {
      image: 'https://images.unsplash.com/photo-1498837167922-ddd27525d352?w=600&q=75',
      category: 'Nutrition',
      title: 'Eat Well, Live Well',
      excerpt: 'A balanced diet rich in fruits, vegetables, and whole grains is the foundation of good health and disease prevention.',
    },
    {
      image: 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=600&q=75',
      category: 'Fitness',
      title: 'Stay Active Every Day',
      excerpt: 'Just 30 minutes of moderate exercise daily can significantly reduce your risk of chronic diseases and improve mental wellbeing.',
    },
    {
      image: 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=600&q=75',
      category: 'Wellness',
      title: 'Mind & Body Balance',
      excerpt: 'Practicing mindfulness and stress management techniques can lower blood pressure and boost your immune system.',
    },
  ];

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadPlans();
  }

  loadPlans() {
    this.http
      .get<{ data: FeaturedPlan[] }>('/v1/medical/public/plans', {
        params: { active_only: 'true', per_page: '6' },
      })
      .subscribe({
        next: (res) => {
          this.plans.set(res.data);
          this.plansLoading.set(false);
        },
        error: () => this.plansLoading.set(false),
      });
  }

  tierLabel(level: number): string {
    const labels: Record<number, string> = { 1: 'Basic', 2: 'Standard', 3: 'Premium', 4: 'Elite' };
    return labels[level] ?? `Tier ${level}`;
  }

  tierColor(level: number): string {
    const colors: Record<number, string> = {
      1: 'bg-gray-100 text-gray-600',
      2: 'bg-teal-50 text-teal-700',
      3: 'bg-violet-50 text-violet-700',
      4: 'bg-amber-50 text-amber-700',
    };
    return colors[level] ?? 'bg-gray-100 text-gray-600';
  }

  planTypeIcon(type: string): string {
    const icons: Record<string, string> = {
      individual: 'person',
      family: 'family_restroom',
      corporate: 'business',
    };
    return icons[type] ?? 'health_and_safety';
  }

  starsArray(count: number): number[] {
    return Array(count).fill(0);
  }
}
