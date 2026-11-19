import { Component, OnInit, inject, ChangeDetectorRef, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule, Router } from '@angular/router';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import {
  CustomerService,
  DocumentUploadService,
  InvoiceService,
  ItemService,
  ToastService
} from '@invoice-mgmt/shared-services';
import {
  Customer,
  DocumentProcessingStatus,
  DocumentUpload,
  ExtractedCustomer,
  ExtractedData,
  ExtractedInvoiceDetails,
  ExtractedItem,
  ExtractedTotals,
  Invoice,
  Item
} from '@invoice-mgmt/shared-models';
import { formatCurrency, formatDate, getDocumentStatusBadgeClass, getStatusBadgeClass } from '@invoice-mgmt/shared-utils';

@Component({
  selector: 'app-upload-document',
  standalone: true,
  imports: [CommonModule, FormsModule, ReactiveFormsModule, RouterModule],
  templateUrl: './upload-document.component.html',
  styleUrls: ['./upload-document.component.scss']
})
export class UploadDocumentComponent implements OnInit {
  private documentService = inject(DocumentUploadService);
  private customerService = inject(CustomerService);
  private itemService = inject(ItemService);
  private invoiceService = inject(InvoiceService);
  private toastService = inject(ToastService);
  private router = inject(Router);
  private cdr = inject(ChangeDetectorRef);
  private sanitizer = inject(DomSanitizer);

  @ViewChild('fileInput') fileInputRef!: ElementRef<HTMLInputElement>;

  // Tab State
  activeTab: 'upload' | 'history' = 'upload';

  // Upload State
  isDragging = false;
  selectedFile: File | null = null;
  uploading = false;
  processingStep: 'idle' | 'uploading' | 'reading' | 'extracting' | 'ready' | 'failed' = 'idle';

  // Current Working Document (Review / Edit Mode)
  currentDocument: DocumentUpload | null = null;
  safePreviewUrl: SafeResourceUrl | null = null;

  // Review Form Model
  reviewCustomer: ExtractedCustomer = {
    first_name: '',
    last_name: '',
    company_name: '',
    email: '',
    phone: '',
    address_line1: '',
    address_line2: '',
    city: '',
    country: 'India',
    payment_method: 'Monthly Pay',
  };

  reviewInvoice: ExtractedInvoiceDetails = {
    document_number: '',
    invoice_date: new Date().toISOString().substring(0, 10),
    due_date: new Date(Date.now() + 15 * 86400000).toISOString().substring(0, 10),
    payment_terms: 'Net 15 days',
    payment_method: 'Monthly Pay',
    currency: 'INR',
    notes: '',
  };

  reviewItems: ExtractedItem[] = [];

  reviewTotals: ExtractedTotals = {
    subtotal: 0,
    total_gst: 0,
    discount: 0,
    additional_charges: 0,
    grand_total: 0,
  };

  // Customer Matching selection
  customerMatchMode: 'matched' | 'existing' | 'new' = 'matched';
  selectedCustomerId: number | null = null;
  matchedCustomerInfo: any = null;

  // Database Autocomplete Cache
  existingCustomers: Customer[] = [];
  existingItems: Item[] = [];

  // Operation States
  savingEdits = false;
  generatingDraft = false;
  retrying = false;
  finalizing = false;

  // Modals
  duplicateWarningOpen = false;
  duplicateData: any = null;
  createdInvoice: Invoice | null = null;
  successModalOpen = false;
  finalizeConfirmModalOpen = false;
  deleteConfirmModalOpen = false;
  targetDeleteDoc: DocumentUpload | null = null;

  // History State
  historyList: DocumentUpload[] = [];
  historyLoading = false;
  historyPage = 1;
  historyPerPage = 10;
  historyTotal = 0;
  historyLastPage = 1;
  historySearch = '';
  historyStatus = '';

  // Utilities
  formatCurrency = formatCurrency;
  formatDate = formatDate;
  getDocumentStatusBadgeClass = getDocumentStatusBadgeClass;
  getStatusBadgeClass = getStatusBadgeClass;

  ngOnInit() {
    this.loadDatabaseLookups();
    this.loadHistory();
  }

  loadDatabaseLookups() {
    this.customerService.getCustomers({ per_page: 100 }).subscribe({
      next: (res) => {
        this.existingCustomers = res.data.data || [];
      },
      error: () => {}
    });

    this.itemService.getItems({ per_page: 100 }).subscribe({
      next: (res) => {
        this.existingItems = res.data.data || [];
      },
      error: () => {}
    });
  }

  setTab(tab: 'upload' | 'history') {
    this.activeTab = tab;
    if (tab === 'history') {
      this.loadHistory();
    }
  }

  // ==========================================
  // DRAG & DROP / FILE SELECTION
  // ==========================================
  onDragOver(event: DragEvent) {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging = true;
  }

  onDragLeave(event: DragEvent) {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging = false;
  }

  onDrop(event: DragEvent) {
    event.preventDefault();
    event.stopPropagation();
    this.isDragging = false;
    const files = event.dataTransfer?.files;
    if (files && files.length > 0) {
      this.handleFileSelected(files[0]);
    }
  }

  onFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      this.handleFileSelected(input.files[0]);
    }
  }

  handleFileSelected(file: File) {
    if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
      this.toastService.error('Please upload a valid PDF document.', 'Invalid File');
      return;
    }

    if (file.size > 20 * 1024 * 1024) {
      this.toastService.error('File size exceeds the 20MB limit.', 'File Too Large');
      return;
    }

    this.selectedFile = file;
    this.startUpload(false);
  }

  triggerBrowse() {
    this.fileInputRef?.nativeElement.click();
  }

  clearSelectedFile() {
    this.selectedFile = null;
    this.currentDocument = null;
    this.safePreviewUrl = null;
    this.processingStep = 'idle';
    if (this.fileInputRef) {
      this.fileInputRef.nativeElement.value = '';
    }
  }

  // ==========================================
  // UPLOAD & EXTRACTION PIPELINE
  // ==========================================
  startUpload(confirmDuplicate = false) {
    if (!this.selectedFile) return;

    this.uploading = true;
    this.duplicateWarningOpen = false;
    this.processingStep = 'uploading';

    // Simulated step transitions for transparent UI feedback
    setTimeout(() => {
      if (this.uploading) this.processingStep = 'reading';
    }, 900);

    setTimeout(() => {
      if (this.uploading) this.processingStep = 'extracting';
    }, 2200);

    this.documentService.uploadDocument(this.selectedFile, confirmDuplicate).subscribe({
      next: (res: any) => {
        this.uploading = false;
        this.processingStep = 'ready';
        this.currentDocument = res.data;
        this.toastService.success('PDF parsed and data extracted successfully!', 'Extraction Complete');
        this.populateReviewForm(res.data);
        this.cdr.detectChanges();
      },
      error: (err: any) => {
        this.uploading = false;
        if (err.status === 409 && err.error?.status === 'duplicate_warning') {
          this.duplicateData = err.error;
          this.duplicateWarningOpen = true;
          this.processingStep = 'idle';
          this.toastService.warning(err.error.message, 'Duplicate Warning');
        } else {
          this.processingStep = 'failed';
          const msg = err.error?.message || 'Failed to process document.';
          this.toastService.error(msg, 'Upload Failed');
        }
        this.cdr.detectChanges();
      }
    });
  }

  proceedDuplicateUpload() {
    this.startUpload(true);
  }

  // ==========================================
  // REVIEW & EDIT FORM DATA
  // ==========================================
  populateReviewForm(doc: DocumentUpload) {
    this.currentDocument = doc;
    const extracted: ExtractedData = doc.extracted_data || ({} as any);

    // Setup Safe URL for inline preview
    const rawUrl = this.documentService.getPreviewUrl(doc.id);
    this.safePreviewUrl = this.sanitizer.bypassSecurityTrustResourceUrl(rawUrl);

    // Customer setup
    this.reviewCustomer = extracted.customer ? { ...extracted.customer } : {
      first_name: '',
      last_name: '',
      company_name: '',
      email: '',
      phone: '',
      address_line1: '',
      address_line2: '',
      city: '',
      country: 'India',
      payment_method: 'Monthly Pay',
    };

    if (extracted.customer_match) {
      this.matchedCustomerInfo = extracted.customer_match;
      this.selectedCustomerId = extracted.customer_match.id;
      this.customerMatchMode = 'matched';
    } else {
      this.matchedCustomerInfo = null;
      this.selectedCustomerId = null;
      this.customerMatchMode = this.reviewCustomer.company_name || this.reviewCustomer.first_name ? 'new' : 'existing';
    }

    // Invoice setup
    this.reviewInvoice = extracted.invoice ? { ...extracted.invoice } : {
      document_number: '',
      invoice_date: new Date().toISOString().substring(0, 10),
      due_date: new Date(Date.now() + 15 * 86400000).toISOString().substring(0, 10),
      payment_terms: 'Net 15 days',
      payment_method: 'Monthly Pay',
      currency: 'INR',
      notes: '',
    };

    // Items setup
    this.reviewItems = (extracted.items || []).map(item => ({ ...item }));

    // Totals setup
    this.reviewTotals = extracted.totals ? { ...extracted.totals } : {
      subtotal: 0,
      total_gst: 0,
      discount: 0,
      additional_charges: 0,
      grand_total: 0,
    };

    this.recalculateTotals();
  }

  // Item Table Methods
  addItemRow() {
    this.reviewItems.push({
      item_name: 'New Item',
      description: '',
      quantity: 1,
      unit_price: 0,
      gst_percentage: 18,
      amount: 0,
      matched_item_id: null,
      matched_item_name: null,
      is_matched: false,
    });
    this.recalculateTotals();
  }

  removeItemRow(index: number) {
    this.reviewItems.splice(index, 1);
    this.recalculateTotals();
  }

  onItemMatchSelect(item: ExtractedItem, dbItemId: any) {
    const id = parseInt(dbItemId, 10);
    if (!id) {
      item.matched_item_id = null;
      item.matched_item_name = null;
      item.is_matched = false;
      return;
    }
    const found = this.existingItems.find(i => i.id === id);
    if (found) {
      item.matched_item_id = found.id;
      item.matched_item_name = found.item_name;
      item.item_name = found.item_name;
      item.unit_price = found.item_cost || item.unit_price;
      item.gst_percentage = found.gst_percentage || item.gst_percentage;
      item.is_matched = true;
      this.onItemPriceOrQtyChange(item);
    }
  }

  onItemPriceOrQtyChange(item: ExtractedItem) {
    const qty = Number(item.quantity) || 0;
    const rate = Number(item.unit_price) || 0;
    item.amount = Math.round(qty * rate * 100) / 100;
    this.recalculateTotals();
  }

  recalculateTotals() {
    let subtotal = 0;
    let totalGst = 0;

    for (const it of this.reviewItems) {
      const lineTotal = Math.round((Number(it.quantity) || 0) * (Number(it.unit_price) || 0) * 100) / 100;
      it.amount = lineTotal;
      const gstPct = Number(it.gst_percentage) || 0;
      const gstAmt = Math.round((lineTotal * gstPct / 100) * 100) / 100;
      subtotal += lineTotal;
      totalGst += gstAmt;
    }

    const discount = Number(this.reviewTotals.discount) || 0;
    const charges = Number(this.reviewTotals.additional_charges) || 0;

    this.reviewTotals.subtotal = Math.round(subtotal * 100) / 100;
    this.reviewTotals.total_gst = Math.round(totalGst * 100) / 100;
    this.reviewTotals.grand_total = Math.max(0, Math.round((subtotal + totalGst - discount + charges) * 100) / 100);
  }

  onExistingCustomerSelect(customerId: any) {
    const id = parseInt(customerId, 10);
    this.selectedCustomerId = id || null;
    const found = this.existingCustomers.find(c => c.id === id);
    if (found) {
      this.reviewCustomer.first_name = found.first_name;
      this.reviewCustomer.last_name = found.last_name;
      this.reviewCustomer.company_name = found.company_name || '';
      this.reviewCustomer.email = found.email;
      this.reviewCustomer.phone = found.primary_phone || (found.phones?.[0]?.phone_number || '');
      this.reviewCustomer.address_line1 = found.address_line1;
      this.reviewCustomer.address_line2 = found.address_line2 || '';
      this.reviewCustomer.city = found.city;
      this.reviewCustomer.country = found.country;
      this.reviewCustomer.payment_method = found.payment_method;
    }
  }

  // ==========================================
  // SAVE EDITS
  // ==========================================
  saveEdits() {
    if (!this.currentDocument) return;

    this.savingEdits = true;
    const payload = {
      customer: this.reviewCustomer,
      invoice: this.reviewInvoice,
      items: this.reviewItems,
      totals: this.reviewTotals,
      customer_match: this.customerMatchMode === 'matched' ? this.matchedCustomerInfo : null,
    };

    this.documentService.updateExtractedData(this.currentDocument.id, payload).subscribe({
      next: (res) => {
        this.savingEdits = false;
        this.currentDocument = res.data;
        this.toastService.success('Extracted review data saved successfully.');
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.savingEdits = false;
        this.toastService.error(err.error?.message || 'Failed to save review data.');
        this.cdr.detectChanges();
      }
    });
  }

  retryCurrentDocument() {
    if (!this.currentDocument) return;
    this.retrying = true;
    this.documentService.retryExtraction(this.currentDocument.id).subscribe({
      next: (res) => {
        this.retrying = false;
        this.currentDocument = res.data;
        this.populateReviewForm(res.data);
        this.toastService.success('Document re-parsed successfully.');
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.retrying = false;
        this.toastService.error(err.error?.message || 'Re-extraction failed.');
        this.cdr.detectChanges();
      }
    });
  }

  // ==========================================
  // DRAFT INVOICE GENERATION
  // ==========================================
  generateDraftInvoice() {
    if (!this.currentDocument) return;

    if (!this.reviewCustomer.first_name && !this.reviewCustomer.company_name) {
      this.toastService.error('Please enter a Customer Name or Company Name before generating a draft.');
      return;
    }

    if (this.reviewItems.length === 0) {
      this.toastService.error('At least one line item is required.');
      return;
    }

    this.generatingDraft = true;

    const payload = {
      customer: this.reviewCustomer,
      invoice: this.reviewInvoice,
      items: this.reviewItems,
      totals: this.reviewTotals,
      customer_match: this.customerMatchMode === 'matched' ? this.matchedCustomerInfo : (
        this.customerMatchMode === 'existing' && this.selectedCustomerId ? { id: this.selectedCustomerId } : null
      ),
      create_new_customer: this.customerMatchMode === 'new',
    };

    this.documentService.generateDraftInvoice(this.currentDocument.id, payload).subscribe({
      next: (res) => {
        this.generatingDraft = false;
        this.createdInvoice = res.data.invoice;
        this.currentDocument = res.data.document;
        this.successModalOpen = true;
        this.toastService.success(res.message || 'Draft invoice generated successfully!', 'Draft Created');
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.generatingDraft = false;
        if (err.status === 409 && err.error?.status === 'duplicate_warning') {
          this.toastService.warning(err.error.message, 'Existing Draft');
          this.createdInvoice = err.error.invoice;
          this.successModalOpen = true;
        } else {
          this.toastService.error(err.error?.message || 'Failed to create draft invoice.');
        }
        this.cdr.detectChanges();
      }
    });
  }

  // ==========================================
  // INVOICE ACTIONS
  // ==========================================
  openViewDraft() {
    if (!this.createdInvoice) return;
    this.successModalOpen = false;
    this.router.navigate(['/invoices', this.createdInvoice.id]);
  }

  openEditDraft() {
    if (!this.createdInvoice) return;
    this.successModalOpen = false;
    this.router.navigate(['/invoices/edit', this.createdInvoice.id]);
  }

  downloadInvoicePdf() {
    if (!this.createdInvoice) return;
    window.open(`/api/invoices/${this.createdInvoice.id}/pdf`, '_blank');
  }

  downloadOriginalDocument() {
    if (!this.currentDocument) return;
    window.open(this.documentService.getDownloadUrl(this.currentDocument.id), '_blank');
  }

  openFinalizeConfirm() {
    this.finalizeConfirmModalOpen = true;
  }

  confirmFinalizeInvoice() {
    if (!this.createdInvoice) return;

    this.finalizing = true;
    this.documentService.finalizeInvoice(this.createdInvoice.id).subscribe({
      next: (res) => {
        this.finalizing = false;
        this.finalizeConfirmModalOpen = false;
        this.createdInvoice = res.data;
        this.toastService.success(`Invoice #${res.data.invoice_number} finalized and ready for client delivery!`, 'Invoice Issued');
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.finalizing = false;
        this.toastService.error(err.error?.message || 'Failed to finalize invoice.');
        this.cdr.detectChanges();
      }
    });
  }

  confirmDeleteDraft() {
    if (!this.createdInvoice) return;

    this.invoiceService.deleteInvoice(this.createdInvoice.id).subscribe({
      next: () => {
        this.toastService.success('Draft invoice deleted.');
        this.createdInvoice = null;
        this.successModalOpen = false;
        this.deleteConfirmModalOpen = false;
        if (this.currentDocument) {
          this.currentDocument.status = 'Review Required';
          this.currentDocument.invoice_id = null;
        }
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.toastService.error(err.error?.message || 'Failed to delete draft invoice.');
      }
    });
  }

  // ==========================================
  // UPLOAD HISTORY METHODS
  // ==========================================
  loadHistory(page = 1) {
    this.historyPage = page;
    this.historyLoading = true;

    this.documentService.getDocuments({
      page: this.historyPage,
      per_page: this.historyPerPage,
      search: this.historySearch,
      status: this.historyStatus,
    }).subscribe({
      next: (res) => {
        this.historyLoading = false;
        this.historyList = res.data.data || [];
        this.historyTotal = res.data.total;
        this.historyLastPage = res.data.last_page;
        this.cdr.detectChanges();
      },
      error: () => {
        this.historyLoading = false;
        this.toastService.error('Failed to load upload history.');
        this.cdr.detectChanges();
      }
    });
  }

  openReviewFromHistory(doc: DocumentUpload) {
    this.activeTab = 'upload';
    this.populateReviewForm(doc);
    if (doc.invoice) {
      this.createdInvoice = doc.invoice;
    }
  }

  deleteDocumentFromHistory(doc: DocumentUpload) {
    if (!confirm(`Are you sure you want to delete '${doc.original_name}'?`)) return;

    this.documentService.deleteDocument(doc.id).subscribe({
      next: () => {
        this.toastService.success('Document upload deleted.');
        if (this.currentDocument?.id === doc.id) {
          this.clearSelectedFile();
        }
        this.loadHistory(this.historyPage);
      },
      error: (err) => {
        this.toastService.error(err.error?.message || 'Failed to delete document.');
      }
    });
  }

  formatFileSize(bytes: number): string {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }
}
