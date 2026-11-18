import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { CustomerService, ToastService } from '@invoice-mgmt/shared-services';
import { Customer, PaginatedResponse } from '@invoice-mgmt/shared-models';
import { getStatusBadgeClass } from '@invoice-mgmt/shared-utils';
import { ConfirmDialogComponent, EmptyStateComponent, LoadingSpinnerComponent, PaginationComponent } from '@invoice-mgmt/shared-ui';

@Component({
  selector: 'app-customer-list',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,
    FormsModule,
    LoadingSpinnerComponent,
    PaginationComponent,
    EmptyStateComponent,
    ConfirmDialogComponent,
  ],
  templateUrl: './customer-list.component.html',
  styleUrls: ['./customer-list.component.scss']
})
export class CustomerListComponent implements OnInit {
  private customerService = inject(CustomerService);
  private toastService = inject(ToastService);
  private cdr = inject(ChangeDetectorRef);

  loading = true;
  pagination: PaginatedResponse<Customer> | null = null;
  cities: string[] = [];

  searchQuery = '';
  selectedCity = '';
  selectedPaymentMethod = '';
  currentPage = 1;

  isDeleteModalOpen = false;
  customerToDelete: Customer | null = null;

  getBadgeClass = getStatusBadgeClass;

  ngOnInit() {
    this.loadCities();
    this.loadCustomers();
  }

  loadCities() {
    this.customerService.getCities().subscribe({
      next: (res) => {
        this.cities = res.data;
        this.cdr.detectChanges();
      },
      error: () => {}
    });
  }

  loadCustomers(page = 1) {
    this.loading = true;
    this.currentPage = page;
    this.customerService.getCustomers({
      page: this.currentPage,
      per_page: 10,
      search: this.searchQuery,
      city: this.selectedCity,
      payment_method: this.selectedPaymentMethod,
    }).subscribe({
      next: (res) => {
        this.pagination = res?.data || null;
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.loading = false;
        this.toastService.error('Failed to load customers.');
        this.cdr.detectChanges();
      }
    });
  }

  onSearch() {
    this.loadCustomers(1);
  }

  onFilterChange() {
    this.loadCustomers(1);
  }

  resetFilters() {
    this.searchQuery = '';
    this.selectedCity = '';
    this.selectedPaymentMethod = '';
    this.loadCustomers(1);
  }

  onPageChange(page: number) {
    this.loadCustomers(page);
  }

  openDeleteConfirm(customer: Customer) {
    this.customerToDelete = customer;
    this.isDeleteModalOpen = true;
  }

  confirmDelete() {
    if (!this.customerToDelete) return;
    this.customerService.deleteCustomer(this.customerToDelete.id).subscribe({
      next: () => {
        this.toastService.success('Customer deleted successfully.');
        this.isDeleteModalOpen = false;
        this.customerToDelete = null;
        this.loadCustomers(this.currentPage);
      },
      error: () => {
        this.toastService.error('Could not delete customer.');
        this.isDeleteModalOpen = false;
      }
    });
  }
}
