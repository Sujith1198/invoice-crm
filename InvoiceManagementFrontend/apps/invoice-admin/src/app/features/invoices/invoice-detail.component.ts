import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { InvoiceService, SettingService, ToastService } from '@invoice-mgmt/shared-services';
import { CompanySetting, Invoice } from '@invoice-mgmt/shared-models';
import { formatCurrency, formatDate, getStatusBadgeClass } from '@invoice-mgmt/shared-utils';
import { LoadingSpinnerComponent } from '@invoice-mgmt/shared-ui';

@Component({
  selector: 'app-invoice-detail',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, LoadingSpinnerComponent],
  templateUrl: './invoice-detail.component.html',
  styleUrls: ['./invoice-detail.component.scss']
})
export class InvoiceDetailComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private invoiceService = inject(InvoiceService);
  private settingService = inject(SettingService);
  private toastService = inject(ToastService);
  private cdr = inject(ChangeDetectorRef);

  loading = true;
  invoice: Invoice | null = null;
  companySetting: CompanySetting | null = null;

  // Payment Modal
  isPaymentModalOpen = false;
  paymentAmount = 0;
  paymentDate = new Date().toISOString().substring(0, 10);
  paymentMethod = 'Bank Transfer';
  paymentRef = '';
  paymentNotes = '';
  submittingPayment = false;

  // Email Modal
  isEmailModalOpen = false;
  emailRecipient = '';
  detectedEmails: string[] = [];
  emailSubject = '';
  emailMessage = '';
  sendingEmail = false;

  formatCurrency = formatCurrency;
  formatDate = formatDate;
  getBadgeClass = getStatusBadgeClass;

  ngOnInit() {
    this.loadCompanySettings();
    const id = parseInt(this.route.snapshot.paramMap.get('id') || '0', 10);
    if (id) {
      this.loadInvoice(id);
    }
  }

  loadCompanySettings() {
    this.settingService.getSettings().subscribe({
      next: (res) => {
        this.companySetting = res.data;
        this.cdr.detectChanges();
      },
      error: () => {}
    });
  }

  loadInvoice(id: number) {
    this.loading = true;
    this.invoiceService.getInvoice(id).subscribe({
      next: (res) => {
        this.invoice = res?.data || null;
        if (res?.data?.balance_due !== undefined) {
          this.paymentAmount = res.data.balance_due;
        }
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.loading = false;
        this.toastService.error('Failed to load invoice details.');
        this.cdr.detectChanges();
      }
    });
  }

  downloadPdf() {
    if (!this.invoice) return;
    const url = this.invoiceService.getPdfUrl(this.invoice.id);
    window.open(url, '_blank');
  }

  printInvoice() {
    window.print();
  }

  sendEmailPrompt() {
    if (!this.invoice) return;

    // Prioritize customer's live profile email, then invoice email
    const liveEmail = this.invoice.customer?.email;
    const rawEmail = (liveEmail || this.invoice.customer_email || '').trim();
    const matches = rawEmail.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g) || [];
    this.detectedEmails = Array.from(new Set(matches));

    // Automatically select the first email if available
    this.emailRecipient = this.detectedEmails.length > 0 ? this.detectedEmails[0] : rawEmail;

    const cleanNum = (this.invoice.invoice_number || '').replace(/^(INV|INVOICE)[-_#\s]*/i, '').replace(/^#\s*/, '');
    const company = this.companySetting?.company_name || 'Orange Invoicing & Logistics Ltd';

    this.emailSubject = `Tax Invoice #${cleanNum} from ${company}`;
    this.emailMessage = `Dear ${this.invoice.customer_name},\n\nPlease find attached your tax invoice #${cleanNum} in PDF format.\n\nInvoice Date: ${formatDate(this.invoice.invoice_date)}\nDue Date: ${formatDate(this.invoice.due_date)}\nGrand Total: ₹${this.invoice.grand_total}\nPayment Terms: ${this.invoice.payment_terms || 'Standard Terms'}\n\nThank you for your business!`;

    this.isEmailModalOpen = true;
    this.cdr.detectChanges();
  }

  selectEmail(email: string) {
    this.emailRecipient = email;
  }

  submitSendEmail() {
    if (!this.invoice) return;
    if (!this.emailRecipient || !this.emailRecipient.trim()) {
      this.toastService.error('Please enter a valid recipient email address.');
      return;
    }

    this.sendingEmail = true;
    this.invoiceService.sendInvoiceEmail(this.invoice.id, {
      email: this.emailRecipient.trim(),
      subject: this.emailSubject.trim(),
      message: this.emailMessage.trim(),
    }).subscribe({
      next: (res: any) => {
        this.sendingEmail = false;
        this.isEmailModalOpen = false;
        this.toastService.success(
          res.message || `Invoice sent successfully to ${this.emailRecipient}!`,
          'Email Sent'
        );
        this.cdr.detectChanges();
      },
      error: (err: any) => {
        this.sendingEmail = false;
        const msg = err?.error?.message || err?.message || 'Failed to dispatch invoice email.';
        this.toastService.error(msg, 'Email Failed');
        this.cdr.detectChanges();
      }
    });
  }

  submitPayment() {
    if (!this.invoice || this.paymentAmount <= 0) return;

    this.submittingPayment = true;
    this.invoiceService.recordPayment(this.invoice.id, {
      amount: this.paymentAmount,
      payment_date: this.paymentDate,
      payment_method: this.paymentMethod,
      reference_number: this.paymentRef,
      notes: this.paymentNotes,
    }).subscribe({
      next: (res) => {
        this.submittingPayment = false;
        this.isPaymentModalOpen = false;
        this.toastService.success(`Payment of ₹${this.paymentAmount} recorded.`);
        this.loadInvoice(this.invoice!.id);
      },
      error: (err) => {
        this.submittingPayment = false;
        this.toastService.error(err.error?.message || 'Failed to record payment.');
      }
    });
  }
}
