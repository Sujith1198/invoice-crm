import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { DashboardService, ToastService } from '@invoice-mgmt/shared-services';
import { DashboardData } from '@invoice-mgmt/shared-models';
import { formatCurrency, formatDate, getStatusBadgeClass } from '@invoice-mgmt/shared-utils';
import { LoadingSpinnerComponent, StatCardComponent } from '@invoice-mgmt/shared-ui';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, RouterModule, LoadingSpinnerComponent, StatCardComponent],
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.scss']
})
export class DashboardComponent implements OnInit {
  private dashboardService = inject(DashboardService);
  private toastService = inject(ToastService);
  private cdr = inject(ChangeDetectorRef);

  loading = true;
  data: DashboardData | null = null;

  private defaultEmptyData: DashboardData = {
    statistics: {
      total_customers: { value: 0, growth_pct: 0, comparison_text: 'vs last month' },
      today_invoices: { value: 0, diff: 0, comparison_text: '0 vs yesterday' },
      total_revenue: { value: 0, formatted: '₹0.00', comparison_text: 'Lifetime verified collections' },
      monthly_revenue: { value: 0, formatted: '₹0.00', growth_pct: 0, comparison_text: 'vs previous month' },
    },
    charts: {
      daily_invoices: [],
      monthly_revenue: [],
      status_distribution: {
        'Paid': 0,
        'Partially Paid': 0,
        'Generated': 0,
        'Draft': 0,
        'Overdue': 0,
        'Cancelled': 0,
      },
    },
    recent_invoices: [],
    recent_customers: [],
  };

  formatCurrency = formatCurrency;
  formatDate = formatDate;
  getBadgeClass = getStatusBadgeClass;

  ngOnInit() {
    this.loadData();
  }

  loadData() {
    this.loading = true;
    this.dashboardService.getDashboardData().subscribe({
      next: (res) => {
        this.data = res?.data || this.defaultEmptyData;
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.warn('Dashboard load fallback to zero state:', err);
        this.data = this.defaultEmptyData;
        this.loading = false;
        this.cdr.detectChanges();
      }
    });
  }

  getMaxDailyCount(): number {
    if (!this.data?.charts.daily_invoices.length) return 5;
    const max = Math.max(...this.data.charts.daily_invoices.map(d => d.count));
    return max > 0 ? max : 5;
  }

  formatNumber(val: number): string {
    return (val || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  get statusList(): Array<{ label: string; count: number; percent: number; color: string }> {
    if (!this.data) return [];
    const dist = this.data.charts.status_distribution || {};
    const total = Object.values(dist).reduce((a, b) => a + b, 0) || 1;
    const colors: Record<string, string> = {
      'Paid': '#2E7D32',
      'Partially Paid': '#FF9D00',
      'Generated': '#0288D1',
      'Draft': '#757575',
      'Overdue': '#D32F2F',
      'Cancelled': '#424242',
    };

    return Object.keys(dist).map(key => {
      const count = dist[key] || 0;
      return {
        label: key,
        count: count,
        percent: Math.round((count / total) * 100),
        color: colors[key] || '#FFAB00',
      };
    });
  }
}
