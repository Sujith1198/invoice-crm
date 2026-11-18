import { Component, OnInit, inject, ChangeDetectorRef, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormGroup, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { CustomerService, InvoiceService, ItemService, SettingService, ToastService } from '@invoice-mgmt/shared-services';
import { CompanySetting, Customer, Item } from '@invoice-mgmt/shared-models';
import { formatCurrency } from '@invoice-mgmt/shared-utils';
import { LoadingSpinnerComponent } from '@invoice-mgmt/shared-ui';

@Component({
  selector: 'app-invoice-generate',
  standalone: true,
  imports: [CommonModule, RouterModule, ReactiveFormsModule, FormsModule, LoadingSpinnerComponent],
  templateUrl: './invoice-generate.component.html',
  styleUrls: ['./invoice-generate.component.scss']
})
export class InvoiceGenerateComponent implements OnInit {
  private fb = inject(FormBuilder);
  private invoiceService = inject(InvoiceService);
  private customerService = inject(CustomerService);
  private itemService = inject(ItemService);
  private settingService = inject(SettingService);
  private toastService = inject(ToastService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private cdr = inject(ChangeDetectorRef);

  isEditMode = false;
  invoiceId: number | null = null;
  loadingInitial = true;
  saving = false;
  submitted = false;

  companySetting: CompanySetting | null = null;

  // Customer Autocomplete state
  customerSearchQuery = '';
  customerSearchResults: Customer[] = [];
  showCustomerDropdown = false;

  // Items Autocomplete state
  activeItemSearchRow: number | null = null;
  itemSearchResults: Item[] = [];

  // Preview Modal
  isPreviewModalOpen = false;

  calculatedSubtotal = 0;
  calculatedTotalGst = 0;
  calculatedGrandTotal = 0;
  calculatedBalanceDue = 0;

  formatCurrency = formatCurrency;

  invoiceForm: FormGroup = this.fb.group({
    invoice_number: ['', Validators.required],
    invoice_date: [new Date().toISOString().substring(0, 10), Validators.required],
    due_date: [this.getDefaultDueDate(), Validators.required],
    customer_id: [null],
    customer_name: ['', Validators.required],
    company_name: [''],
    customer_address: [''],
    customer_phone: [''],
    customer_email: [''],
    payment_method: ['Monthly Pay'],
    discount: [0.00],
    additional_charges: [0.00],
    payment_terms: ['Net 15 days from issuance date'],
    notes: [''],
    terms_and_conditions: ['Standard terms and conditions apply. Overdue balance attracts 1.5% monthly interest.'],
    status: ['Generated'],
    amount_paid: [0.00],
    items: this.fb.array([]),
  });

  get f() {
    return this.invoiceForm.controls;
  }

  get itemsArray(): FormArray {
    return this.invoiceForm.get('items') as FormArray;
  }

  getDefaultDueDate(): string {
    const d = new Date();
    d.setDate(d.getDate() + 15);
    return d.toISOString().substring(0, 10);
  }

  ngOnInit() {
    this.loadCompanySettings();

    const idParam = this.route.snapshot.paramMap.get('id');
    const customerIdParam = this.route.snapshot.queryParamMap.get('customer_id');

    if (idParam) {
      this.isEditMode = true;
      this.invoiceId = parseInt(idParam, 10);
      this.loadInvoice(this.invoiceId);
    } else {
      this.fetchNextNumber();
      this.addItem(); // Start with 1 empty line item
      if (customerIdParam) {
        this.loadCustomerById(parseInt(customerIdParam, 10));
      }
      this.loadingInitial = false;
      this.cdr.detectChanges();
    }

    // Auto-update invoice sequence number when invoice date changes in create mode
    this.invoiceForm.get('invoice_date')?.valueChanges.subscribe(val => {
      if (!this.isEditMode && val) {
        this.fetchNextNumber(val);
      }
    });
  }

  loadCompanySettings() {
    this.settingService.getSettings().subscribe({
      next: (res) => {
        this.companySetting = res.data;
        if (!this.isEditMode) {
          if (res.data.default_payment_terms) {
            this.invoiceForm.patchValue({ payment_terms: res.data.default_payment_terms });
          }
          if (res.data.terms_and_conditions) {
            this.invoiceForm.patchValue({ terms_and_conditions: res.data.terms_and_conditions });
          }
        }
        this.cdr.detectChanges();
      }
    });
  }

  fetchNextNumber(date?: string) {
    const selectedDate = date || this.invoiceForm.get('invoice_date')?.value;
    this.invoiceService.getNextNumber(selectedDate).subscribe({
      next: (res) => {
        this.invoiceForm.patchValue({ invoice_number: res.data.next_invoice_number });
        this.cdr.detectChanges();
      }
    });
  }

  createItemGroup(item?: Partial<Item>): FormGroup {
    const price = item?.item_cost || 0.00;
    const qty = 1.00;
    const gstPct = item?.gst_percentage || 18.00;
    const lineTotal = price * qty;
    const gstAmt = (lineTotal * gstPct) / 100.0;

    return this.fb.group({
      item_id: [item?.id || null],
      item_name: [item?.item_name || '', Validators.required],
      description: [''],
      price: [price, [Validators.required, Validators.min(0)]],
      quantity: [qty, [Validators.required, Validators.min(0.01)]],
      gst_percentage: [gstPct, [Validators.min(0), Validators.max(100)]],
      gst_amount: [gstAmt],
      line_total: [lineTotal],
      sort_order: [this.itemsArray ? this.itemsArray.length : 0],
    });
  }

  addItem(item?: Partial<Item>) {
    this.itemsArray.push(this.createItemGroup(item));
    this.recalculateTotals();
  }

  removeItem(index: number) {
    if (this.itemsArray.length > 1) {
      this.itemsArray.removeAt(index);
      this.recalculateTotals();
    }
  }

  onCustomerSearchInput() {
    if (this.customerSearchQuery.trim().length > 0) {
      this.customerService.searchCustomers(this.customerSearchQuery).subscribe({
        next: (res) => {
          this.customerSearchResults = res.data;
          this.showCustomerDropdown = true;
        }
      });
    } else {
      this.customerSearchResults = [];
      this.showCustomerDropdown = false;
    }
  }

  selectCustomer(cust: Customer) {
    const fullName = cust.full_name || `${cust.first_name || ''} ${cust.last_name || ''}`.trim();
    this.invoiceForm.patchValue({
      customer_id: cust.id,
      customer_name: fullName,
      company_name: cust.company_name || '',
      customer_address: `${cust.address_line1 || ''}${cust.address_line2 ? ', ' + cust.address_line2 : ''}, ${cust.city || ''}, ${cust.country || ''}`,
      customer_phone: cust.primary_phone || '',
      customer_email: cust.email || '',
      payment_method: cust.payment_method || 'Monthly Pay',
    });
    this.showCustomerDropdown = false;
    this.customerSearchQuery = fullName || cust.email || '';
    this.toastService.info(`Customer details autofilled for ${fullName}. Items filtered for this customer.`);

    // Pre-fetch items dedicated to this customer
    this.itemService.searchItems('', cust.id).subscribe({
      next: (res) => {
        this.itemSearchResults = res.data;
        this.cdr.detectChanges();
      }
    });
  }

  loadCustomerById(id: number) {
    this.customerService.getCustomer(id).subscribe({
      next: (res) => {
        this.selectCustomer(res.data.customer);
      }
    });
  }

  onItemFocus(rowIndex: number) {
    this.activeItemSearchRow = rowIndex;
    const val = this.itemsArray.at(rowIndex).get('item_name')?.value || '';
    const customerId = this.invoiceForm.get('customer_id')?.value;
    this.itemService.searchItems(val, customerId).subscribe({
      next: (res) => {
        this.itemSearchResults = res.data;
        this.cdr.detectChanges();
      }
    });
  }

  onItemSearchInput(rowIndex: number) {
    const val = this.itemsArray.at(rowIndex).get('item_name')?.value || '';
    const customerId = this.invoiceForm.get('customer_id')?.value;
    this.itemService.searchItems(val, customerId).subscribe({
      next: (res) => {
        this.itemSearchResults = res.data;
        this.activeItemSearchRow = rowIndex;
        this.cdr.detectChanges();
      }
    });
  }

  @HostListener('document:click', ['$event'])
  onDocumentClick(event: MouseEvent) {
    const target = event.target as HTMLElement;
    if (!target.closest('.autocomplete-dropdown') && !target.closest('.autocomplete-trigger')) {
      this.activeItemSearchRow = null;
      this.showCustomerDropdown = false;
    }
  }

  selectItemForRow(rowIndex: number, item: Item) {
    const group = this.itemsArray.at(rowIndex);
    group.patchValue({
      item_id: item.id,
      item_name: item.item_name,
      description: item.import_declaration || item.export_declaration || '',
      price: item.item_cost,
      gst_percentage: item.gst_percentage,
    });
    this.activeItemSearchRow = null;
    this.itemSearchResults = [];
    this.recalculateTotals();
  }

  recalculateTotals() {
    let subtotal = 0;
    let totalGst = 0;

    this.itemsArray.controls.forEach(control => {
      const price = parseFloat(control.get('price')?.value || 0);
      const qty = parseFloat(control.get('quantity')?.value || 0);
      const gstPct = parseFloat(control.get('gst_percentage')?.value || 0);

      const lineTotal = Math.round(price * qty * 100) / 100;
      const gstAmount = Math.round((lineTotal * gstPct / 100) * 100) / 100;

      control.patchValue({
        line_total: lineTotal,
        gst_amount: gstAmount,
      }, { emitEvent: false });

      subtotal += lineTotal;
      totalGst += gstAmount;
    });

    const discount = parseFloat(this.invoiceForm.get('discount')?.value || 0);
    const charges = parseFloat(this.invoiceForm.get('additional_charges')?.value || 0);
    const amountPaid = parseFloat(this.invoiceForm.get('amount_paid')?.value || 0);

    this.calculatedSubtotal = Math.round(subtotal * 100) / 100;
    this.calculatedTotalGst = Math.round(totalGst * 100) / 100;
    this.calculatedGrandTotal = Math.round(Math.max(0, subtotal + totalGst - discount + charges) * 100) / 100;
    this.calculatedBalanceDue = Math.round(Math.max(0, this.calculatedGrandTotal - amountPaid) * 100) / 100;
  }

  loadInvoice(id: number) {
    this.loadingInitial = true;
    this.invoiceService.getInvoice(id).subscribe({
      next: (res) => {
        try {
          const inv = res?.data;
          if (inv) {
            this.invoiceForm.patchValue({
              invoice_number: inv.invoice_number,
              invoice_date: inv.invoice_date,
              due_date: inv.due_date,
              customer_id: inv.customer_id,
              customer_name: inv.customer_name,
              company_name: inv.company_name,
              customer_address: inv.customer_address,
              customer_phone: inv.customer_phone,
              customer_email: inv.customer_email,
              payment_method: inv.payment_method,
              discount: inv.discount,
              additional_charges: inv.additional_charges,
              payment_terms: inv.payment_terms,
              notes: inv.notes,
              terms_and_conditions: inv.terms_and_conditions,
              status: inv.status,
              amount_paid: inv.amount_paid,
            });

            this.itemsArray.clear();
            if (inv.items && inv.items.length > 0) {
              inv.items.forEach(it => {
                const grp = this.createItemGroup();
                grp.patchValue(it);
                this.itemsArray.push(grp);
              });
            } else {
              this.addItem();
            }

            this.recalculateTotals();
          }
        } finally {
          this.loadingInitial = false;
          this.cdr.detectChanges();
        }
      },
      error: () => {
        this.loadingInitial = false;
        this.toastService.error('Failed to load invoice.');
        this.router.navigate(['/invoices']);
        this.cdr.detectChanges();
      }
    });
  }

  openPreviewModal() {
    this.recalculateTotals();
    this.isPreviewModalOpen = true;
  }

  printPreview() {
    window.print();
  }

  saveAs(targetStatus: 'Draft' | 'Generated') {
    this.submitted = true;
    this.invoiceForm.patchValue({ status: targetStatus });

    if (this.invoiceForm.invalid) {
      this.toastService.error('Please complete all required invoice fields marked with an asterisk.');
      return;
    }

    if (this.itemsArray.length === 0) {
      this.toastService.error('At least one line item is required.');
      return;
    }

    this.saving = true;
    const payload = this.invoiceForm.value;

    if (this.isEditMode && this.invoiceId) {
      this.invoiceService.updateInvoice(this.invoiceId, payload).subscribe({
        next: () => {
          this.saving = false;
          this.toastService.success(`Invoice ${payload.invoice_number} successfully updated!`);
          this.router.navigate(['/invoices']);
        },
        error: (err) => {
          this.saving = false;
          this.toastService.error(err.error?.message || 'Failed to update invoice.');
        }
      });
    } else {
      this.invoiceService.createInvoice(payload).subscribe({
        next: () => {
          this.saving = false;
          this.toastService.success(`Invoice ${payload.invoice_number} successfully created!`);
          this.router.navigate(['/invoices']);
        },
        error: (err) => {
          this.saving = false;
          this.toastService.error(err.error?.message || 'Failed to create invoice.');
        }
      });
    }
  }
}
