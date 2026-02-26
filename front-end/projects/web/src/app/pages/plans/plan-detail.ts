import { Component, signal, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { TitleCasePipe, DecimalPipe } from '@angular/common';

interface PlanBenefit {
  id: string;
  // benefit: { code: string; name: string; category?: { name: string } };
  name: string;
  category?: string;
  limit_display: string;
  limit_amount: number;
  limit_type: string;
  limit_frequency: string;
  is_covered: boolean;
}

interface PlanAddon {
  id: string;
  name: string;
  description: string;
  availability: string;
  availability_label: string;
  pricing_type: string;
  amount: number | null;
  percentage: number | null;
}

interface FullPlan {
  id: string;
  code: string;
  name: string;
  plan_type: string;
  tier_level: number;
  description: string;
  highlights: string;
  network_type: string;
  scheme: { id: string; name: string };
  benefits: PlanBenefit[];
  addons: PlanAddon[];
}

@Component({
  selector: 'app-plan-detail',
  standalone: true,
  imports: [RouterModule, TitleCasePipe, DecimalPipe],
  templateUrl: './plan-detail.html',
})
export class PlanDetailPage implements OnInit {
  plan = signal<FullPlan | null>(null);
  loading = signal(true);
  activeTab = signal<'benefits' | 'addons'>('benefits');

  constructor(private http: HttpClient, private route: ActivatedRoute) {}

  ngOnInit() {
    this.route.params.subscribe((params) => this.loadPlan(params['id']));
  }

  loadPlan(id: string) {
    this.loading.set(true);
    this.http.get<{ data: FullPlan }>(`/v1/medical/public/plans/${id}`).subscribe({
      next: (res) => {
        this.plan.set(res.data);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  tierLabel(level: number): string {
    const labels: Record<number, string> = { 1: 'Basic', 2: 'Standard', 3: 'Premium', 4: 'Elite' };
    return labels[level] ?? `Tier ${level}`;
  }

  formatLimit(benefit: PlanBenefit): string {
    if (!benefit.is_covered) return 'Not Covered';
    if (benefit.limit_display) {
      // limit_display already contains the formatted value (e.g. "K9,000.00")
      // limit_frequency may be absent from the API response
      const freq = benefit.limit_frequency ? ` / ${benefit.limit_frequency}` : '';
      return `${benefit.limit_display}${freq}`;
    }
    return 'Covered';
  }
}
