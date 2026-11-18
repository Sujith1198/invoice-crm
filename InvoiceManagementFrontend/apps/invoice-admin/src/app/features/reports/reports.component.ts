import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CustomerService, ItemService, ReportService, ToastService } from '@invoice-mgmt/shared-services';
import { Customer, Item, PaginatedResponse } from '@invoice-mgmt/shared-models';
import { exportToCsv, formatCurrency, formatDate, getStatusBadgeClass } from '@invoice-mgmt/shared-utils';
import { EmptyStateComponent, LoadingSpinnerComponent, PaginationComponent } from '@invoice-mgmt/shared-ui';

interface ReportTab {
  id: string;
  name: string;
  icon: string;
}

@Component({
  selector: 'app-reports',
  standalone: true,
  imports: [CommonModule, FormsModule, LoadingSpinnerComponent, PaginationComponent, EmptyStateComponent],
  templateUrl: './reports.component.html',
  styleUrls: ['./reports.component.scss']
})
export class ReportsComponent implements OnInit {
  private reportService = inject(ReportService);
  private customerService = inject(CustomerService);
  private itemService = inject(ItemService);
  private toastService = inject(ToastService);
  private cdr = inject(ChangeDetectorRef);

  loading = true;
  activeReport = 'daily';
  pagination: PaginatedResponse<any> | null = null;
  summary: any = null;

  customers: Customer[] = [];
  items: Item[] = [];

  fromDate = '';
  toDate = '';
  selectedCustomerId = '';
  selectedItemId = '';
  selectedStatus = '';
  searchQuery = '';
  currentPage = 1;

  formatCurrency = formatCurrency;
  formatDate = formatDate;
  getBadgeClass = getStatusBadgeClass;

  reportTabs: ReportTab[] = [
    { id: 'daily', name: '1. Daily Invoices', icon: '📅' },
    { id: 'weekly', name: '2. Weekly Report', icon: '📆' },
    { id: 'monthly', name: '3. Monthly Report', icon: '📊' },
    { id: 'yearly', name: '4. Yearly Report', icon: '📈' },
    { id: 'revenue', name: '5. Revenue Collections', icon: '💰' },
    { id: 'outstanding', name: '6. Outstanding Payments', icon: '⏳' },
    { id: 'paid', name: '7. Paid Invoices', icon: '✅' },
    { id: 'unpaid', name: '8. Unpaid Invoices', icon: '❌' },
    { id: 'gst', name: '9. GST Tax Statement', icon: '📑' },
    { id: 'customers', name: '10. Customer Ledgers', icon: '👥' },
    { id: 'items', name: '11. Item Performance', icon: '📦' },
  ];

  get activeReportTitle(): string {
    const tab = this.reportTabs.find(t => t.id === this.activeReport);
    return tab ? tab.name : 'Report Overview';
  }

  ngOnInit() {
    this.loadFilterOptions();
    this.loadReport(1);
  }

  loadFilterOptions() {
    this.customerService.getCustomers({ per_page: 100 }).subscribe({
      next: (res) => {
        this.customers = res.data.data;
        this.cdr.detectChanges();
      },
      error: () => {}
    });
    this.itemService.getItems({ per_page: 100 }).subscribe({
      next: (res) => {
        this.items = res.data.data;
        this.cdr.detectChanges();
      },
      error: () => {}
    });
  }

  selectReport(id: string) {
    this.activeReport = id;
    this.loadReport(1);
  }

  loadReport(page = 1) {
    this.loading = true;
    this.currentPage = page;

    this.reportService.getReport(this.activeReport, {
      page: this.currentPage,
      per_page: 10,
      from_date: this.fromDate,
      to_date: this.toDate,
      customer_id: this.selectedCustomerId,
      item_id: this.selectedItemId,
      status: this.selectedStatus,
      search: this.searchQuery,
    }).subscribe({
      next: (res) => {
        this.pagination = res?.data || null;
        this.summary = res?.summary || null;
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.loading = false;
        this.toastService.error(`Could not compile ${this.activeReportTitle}.`);
        this.cdr.detectChanges();
      }
    });
  }

  resetFilters() {
    this.fromDate = '';
    this.toDate = '';
    this.selectedCustomerId = '';
    this.selectedItemId = '';
    this.selectedStatus = '';
    this.searchQuery = '';
    this.loadReport(1);
  }

  printReport() {
    window.print();
  }

  exportCsv() {
    if (!this.pagination || !this.pagination.data.length) {
      this.toastService.warning('No data available to export.');
      return;
    }

    const headers = [
      'Invoice #', 'Date', 'Customer Name', 'Company Name',
      'Subtotal', 'GST', 'Grand Total', 'Amount Paid', 'Balance Due', 'Status'
    ];

    const rows = this.pagination.data.map(item => [
      item.invoice_number || '',
      item.invoice_date || '',
      item.customer_name || '',
      item.company_name || '',
      item.subtotal || 0,
      item.total_gst || 0,
      item.grand_total || 0,
      item.amount_paid || 0,
      item.balance_due || 0,
      item.status || '',
    ]);

    exportToCsv(`Orange-Invoice-Report-${this.activeReport}-${new Date().toISOString().substring(0, 10)}`, headers, rows);
    this.toastService.success('Report exported to CSV successfully.');
  }
}
