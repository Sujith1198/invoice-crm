import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { CustomerService, ToastService } from '@invoice-mgmt/shared-services';
import { Customer } from '@invoice-mgmt/shared-models';
import { formatCurrency, formatDate, getStatusBadgeClass } from '@invoice-mgmt/shared-utils';
import { LoadingSpinnerComponent } from '@invoice-mgmt/shared-ui';

@Component({
  selector: 'app-customer-detail',
  standalone: true,
  imports: [CommonModule, RouterModule, LoadingSpinnerComponent],
  templateUrl: './customer-detail.component.html',
  styleUrls: ['./customer-detail.component.scss']
})
export class CustomerDetailComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private customerService = inject(CustomerService);
  private toastService = inject(ToastService);
  private cdr = inject(ChangeDetectorRef);

  loading = true;
  customer: Customer | null = null;
  metrics: any = null;

  formatCurrency = formatCurrency;
  formatDate = formatDate;
  getBadgeClass = getStatusBadgeClass;

  ngOnInit() {
    const id = parseInt(this.route.snapshot.paramMap.get('id') || '0', 10);
    if (id) {
      this.loadCustomer(id);
    }
  }

  loadCustomer(id: number) {
    this.loading = true;
    this.customerService.getCustomer(id).subscribe({
      next: (res) => {
        this.customer = res?.data?.customer || null;
        this.metrics = res?.data?.metrics || null;
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.loading = false;
        this.toastService.error('Failed to load customer profile.');
        this.cdr.detectChanges();
      }
    });
  }
}
