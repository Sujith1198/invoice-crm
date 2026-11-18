import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { CustomerService, ItemService, ToastService } from '@invoice-mgmt/shared-services';
import { Customer } from '@invoice-mgmt/shared-models';
import { LoadingSpinnerComponent } from '@invoice-mgmt/shared-ui';

@Component({
  selector: 'app-item-form',
  standalone: true,
  imports: [CommonModule, RouterModule, ReactiveFormsModule, LoadingSpinnerComponent],
  templateUrl: './item-form.component.html',
  styleUrls: ['./item-form.component.scss']
})
export class ItemFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private itemService = inject(ItemService);
  private customerService = inject(CustomerService);
  private toastService = inject(ToastService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private cdr = inject(ChangeDetectorRef);

  isEditMode = false;
  itemId: number | null = null;
  loadingItem = false;
  saving = false;
  submitted = false;
  customers: Customer[] = [];

  itemForm: FormGroup = this.fb.group({
    customer_id: [null],
    item_name: ['', Validators.required],
    item_cost: [0.00, [Validators.required, Validators.min(0)]],
    gst_percentage: [18.00, [Validators.required, Validators.min(0), Validators.max(100)]],
    status: ['active', Validators.required],
    import_declaration: [''],
    export_declaration: [''],
    certificate_of_origin: [''],
    xo_permit: [''],
    amendment: [''],
  });

  get f() {
    return this.itemForm.controls;
  }

  ngOnInit() {
    this.loadCustomers();
    const idParam = this.route.snapshot.paramMap.get('id');
    if (idParam) {
      this.isEditMode = true;
      this.itemId = parseInt(idParam, 10);
      this.loadItem(this.itemId);
    }
  }

  loadCustomers() {
    this.customerService.getCustomers({ per_page: 100 }).subscribe({
      next: (res) => {
        this.customers = res?.data?.data || [];
        this.cdr.detectChanges();
      },
      error: () => {}
    });
  }

  loadItem(id: number) {
    this.loadingItem = true;
    this.itemService.getItem(id).subscribe({
      next: (res) => {
        try {
          if (res?.data) {
            this.itemForm.patchValue(res.data);
          }
        } finally {
          this.loadingItem = false;
          this.cdr.detectChanges();
        }
      },
      error: () => {
        this.loadingItem = false;
        this.toastService.error('Failed to load catalog item specifications.');
        this.router.navigate(['/items']);
        this.cdr.detectChanges();
      }
    });
  }

  onSubmit() {
    this.submitted = true;
    if (this.itemForm.invalid) {
      this.toastService.error('Please enter all required product specifications.');
      return;
    }

    this.saving = true;
    const payload = this.itemForm.value;

    if (this.isEditMode && this.itemId) {
      this.itemService.updateItem(this.itemId, payload).subscribe({
        next: () => {
          this.saving = false;
          this.toastService.success('Catalog item updated successfully.');
          this.router.navigate(['/items']);
        },
        error: (err) => {
          this.saving = false;
          this.toastService.error(err.error?.message || 'Failed to update item.');
        }
      });
    } else {
      this.itemService.createItem(payload).subscribe({
        next: () => {
          this.saving = false;
          this.toastService.success('Catalog item registered successfully.');
          this.router.navigate(['/items']);
        },
        error: (err) => {
          this.saving = false;
          this.toastService.error(err.error?.message || 'Failed to create item.');
        }
      });
    }
  }
}
