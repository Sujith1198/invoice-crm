import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { InvoiceService, ToastService } from '@invoice-mgmt/shared-services';
import { Invoice, PaginatedResponse } from '@invoice-mgmt/shared-models';
import { formatCurrency, formatDate, getStatusBadgeClass } from '@invoice-mgmt/shared-utils';
import { ConfirmDialogComponent, EmptyStateComponent, LoadingSpinnerComponent, PaginationComponent } from '@invoice-mgmt/shared-ui';

@Component({
  selector: 'app-invoice-list',
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
  templateUrl: './invoice-list.component.html',
  styleUrls: ['./invoice-list.component.scss']
})
export class InvoiceListComponent implements OnInit {
  private invoiceService = inject(InvoiceService);
  private toastService = inject(ToastService);
  private cdr = inject(ChangeDetectorRef);

  loading = true;
  pagination: PaginatedResponse<Invoice> | null = null;

  searchQuery = '';
  selectedStatus = '';
  fromDate = '';
  toDate = '';
  currentPage = 1;

  isDeleteModalOpen = false;
  invoiceToDelete: Invoice | null = null;

  sendingEmailId: number | null = null;

  // Record Payment Modal State
  isPaymentModalOpen = false;
  selectedInvoiceForPayment: Invoice | null = null;
  paymentAmount = 0;
  paymentDate = new Date().toISOString().substring(0, 10);
  paymentMethod = 'Bank Transfer';
  paymentReference = '';
  paymentNotes = '';
  submittingPayment = false;

  formatCurrency = formatCurrency;
  formatDate = formatDate;
  getBadgeClass = getStatusBadgeClass;

  ngOnInit() {
    this.loadInvoices();
  }

  loadInvoices(page = 1) {
    this.loading = true;
    this.currentPage = page;
    this.invoiceService.getInvoices({
      page: this.currentPage,
      per_page: 10,
      search: this.searchQuery,
      status: this.selectedStatus,
      from_date: this.fromDate,
      to_date: this.toDate,
    }).subscribe({
      next: (res) => {
        this.pagination = res?.data || null;
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.loading = false;
        this.toastService.error('Failed to load invoices.');
        this.cdr.detectChanges();
      }
    });
  }

  onSearch() {
    this.loadInvoices(1);
  }

  onFilterChange() {
    this.loadInvoices(1);
  }

  resetFilters() {
    this.searchQuery = '';
    this.selectedStatus = '';
    this.fromDate = '';
    this.toDate = '';
    this.loadInvoices(1);
  }

  onPageChange(page: number) {
    this.loadInvoices(page);
  }

  downloadPdf(invoice: Invoice) {
    const url = this.invoiceService.getPdfUrl(invoice.id);
    window.open(url, '_blank');
  }

  sendInvoiceEmail(invoice: Invoice) {
    if (this.sendingEmailId) return;

    const emailToSend = invoice.customer?.email || invoice.customer_email;
    this.sendingEmailId = invoice.id;
    this.invoiceService.sendInvoiceEmail(invoice.id, { email: emailToSend }).subscribe({
      next: (res: any) => {
        this.sendingEmailId = null;
        this.toastService.success(res.message || `Invoice sent to customer email!`, 'Email Dispatched');
        this.loadInvoices(this.currentPage);
      },
      error: (err: any) => {
        this.sendingEmailId = null;
        this.toastService.error(err?.error?.message || err?.message || 'Failed to dispatch invoice email.', 'Email Failed');
        this.cdr.detectChanges();
      }
    });
  }

  openPaymentModal(invoice: Invoice) {
    this.selectedInvoiceForPayment = invoice;
    this.paymentAmount = invoice.balance_due;
    this.paymentDate = new Date().toISOString().substring(0, 10);
    this.paymentMethod = 'Bank Transfer';
    this.paymentReference = '';
    this.paymentNotes = '';
    this.isPaymentModalOpen = true;
  }

  submitPayment() {
    if (!this.selectedInvoiceForPayment) return;

    if (this.paymentAmount <= 0 || this.paymentAmount > this.selectedInvoiceForPayment.balance_due) {
      this.toastService.error(`Amount must be between 1 and ${formatCurrency(this.selectedInvoiceForPayment.balance_due)}`);
      return;
    }

    this.submittingPayment = true;
    this.invoiceService.recordPayment(this.selectedInvoiceForPayment.id, {
      amount: this.paymentAmount,
      payment_date: this.paymentDate,
      payment_method: this.paymentMethod,
      reference_number: this.paymentReference,
      notes: this.paymentNotes,
    }).subscribe({
      next: () => {
        this.submittingPayment = false;
        this.isPaymentModalOpen = false;
        this.toastService.success(`Payment of ${formatCurrency(this.paymentAmount)} successfully registered!`);
        this.loadInvoices(this.currentPage);
      },
      error: (err) => {
        this.submittingPayment = false;
        this.toastService.error(err.error?.message || 'Failed to record payment transaction.');
      }
    });
  }

  openDeleteConfirm(invoice: Invoice) {
    this.invoiceToDelete = invoice;
    this.isDeleteModalOpen = true;
  }

  confirmDelete() {
    if (!this.invoiceToDelete) return;
    this.invoiceService.deleteInvoice(this.invoiceToDelete.id).subscribe({
      next: () => {
        this.toastService.success(`Invoice ${this.invoiceToDelete?.invoice_number} deleted.`);
        this.isDeleteModalOpen = false;
        this.invoiceToDelete = null;
        this.loadInvoices(this.currentPage);
      },
      error: () => {
        this.toastService.error('Could not delete invoice.');
        this.isDeleteModalOpen = false;
      }
    });
  }
}
