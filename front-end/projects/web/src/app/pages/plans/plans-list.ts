import { Component, signal, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
interface WebPlan {
  id: string;
  code: string;
  name: string;
  plan_type: string;
  tier_level: number;
  description: string;
  highlights: string;
  is_active: boolean;
  scheme: { id: string; name: string; code: string };
  plan_benefits_count: number;
  plan_addons_count: number;
}

@Component({
  selector: 'app-plans-list',
  standalone: true,
  imports: [RouterModule],
  templateUrl: './plans-list.html',
})
export class PlansListPage implements OnInit {
  plans = signal<WebPlan[]>([]);
  filteredPlans = signal<WebPlan[]>([]);
  loading = signal(true);
  activeFilter = signal('all'); // all | individual | family | corporate

  constructor(private http: HttpClient) {}

  ngOnInit() {
    this.loadPlans();
  }

  loadPlans() {
    this.loading.set(true);
    this.http
      .get<{ data: WebPlan[] }>('/v1/medical/public/plans', {
        params: { active_only: 'true', per_page: '50' },
      })
      .subscribe({
        next: (res) => {
          this.plans.set(res.data);
          this.applyFilter();
          this.loading.set(false);
        },
        error: () => this.loading.set(false),
      });
  }

  setFilter(filter: string) {
    this.activeFilter.set(filter);
    this.applyFilter();
  }

  private applyFilter() {
    const filter = this.activeFilter();
    const all = this.plans();
    this.filteredPlans.set(filter === 'all' ? all : all.filter((p) => p.plan_type === filter));
  }

  tierLabel(level: number): string {
    const labels: Record<number, string> = { 1: 'Basic', 2: 'Standard', 3: 'Premium', 4: 'Elite' };
    return labels[level] ?? `Tier ${level}`;
  }

  planTypeIcon(type: string): string {
    const icons: Record<string, string> = {
      individual: 'person',
      family: 'family_restroom',
      corporate: 'business',
      sme: 'store',
      group: 'group',
    };
    return icons[type] ?? 'health_and_safety';
  }
}
