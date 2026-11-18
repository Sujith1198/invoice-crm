import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { CustomerService, ToastService } from '@invoice-mgmt/shared-services';
import { Customer } from '@invoice-mgmt/shared-models';
import { LoadingSpinnerComponent } from '@invoice-mgmt/shared-ui';

@Component({
  selector: 'app-customer-form',
  standalone: true,
  imports: [CommonModule, RouterModule, ReactiveFormsModule, LoadingSpinnerComponent],
  templateUrl: './customer-form.component.html',
  styleUrls: ['./customer-form.component.scss']
})
export class CustomerFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private customerService = inject(CustomerService);
  private toastService = inject(ToastService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private cdr = inject(ChangeDetectorRef);

  isEditMode = false;
  customerId: number | null = null;
  loadingCustomer = false;
  saving = false;
  submitted = false;

  duplicateWarning: { name: string; email: string; company_name?: string } | null = null;

  customerForm: FormGroup = this.fb.group({
    first_name: ['', Validators.required],
    last_name: ['', Validators.required],
    company_name: [''],
    email: ['', [Validators.required, Validators.email]],
    address_line1: ['', Validators.required],
    address_line2: [''],
    city: ['', Validators.required],
    country: ['India', Validators.required],
    payment_method: ['Monthly Pay', Validators.required],
    status: ['active', Validators.required],
    notes: [''],
    phones: this.fb.array([]),
  });

  get f() {
    return this.customerForm.controls;
  }

  get phonesArray(): FormArray {
    return this.customerForm.get('phones') as FormArray;
  }

  ngOnInit() {
    const idParam = this.route.snapshot.paramMap.get('id');
    if (idParam) {
      this.isEditMode = true;
      this.customerId = parseInt(idParam, 10);
      this.loadCustomer(this.customerId);
    } else {
      // Default with one primary phone
      this.addPhone('', true, 'Mobile');
    }
  }

  createPhoneGroup(phoneNumber = '', isPrimary = false, type = 'Mobile'): FormGroup {
    return this.fb.group({
      phone_number: [phoneNumber, [Validators.required, Validators.minLength(7)]],
      is_primary: [isPrimary],
      type: [type],
    });
  }

  addPhone(phoneNumber = '', isPrimary = false, type = 'Mobile') {
    const isFirst = this.phonesArray.length === 0;
    this.phonesArray.push(this.createPhoneGroup(phoneNumber, isPrimary || isFirst, type));
  }

  removePhone(index: number) {
    if (this.phonesArray.length > 1) {
      const wasPrimary = this.phonesArray.at(index).get('is_primary')?.value;
      this.phonesArray.removeAt(index);
      if (wasPrimary && this.phonesArray.length > 0) {
        this.phonesArray.at(0).patchValue({ is_primary: true });
      }
    }
  }

  setPrimaryPhone(selectedIndex: number) {
    this.phonesArray.controls.forEach((group, idx) => {
      group.patchValue({ is_primary: idx === selectedIndex });
    });
  }

  loadCustomer(id: number) {
    this.loadingCustomer = true;
    this.customerService.getCustomer(id).subscribe({
      next: (res) => {
        try {
          const cust = res?.data?.customer;
          if (cust) {
            this.customerForm.patchValue({
              first_name: cust.first_name,
              last_name: cust.last_name,
              company_name: cust.company_name,
              email: cust.email,
              address_line1: cust.address_line1,
              address_line2: cust.address_line2,
              city: cust.city,
              country: cust.country,
              payment_method: cust.payment_method,
              status: cust.status,
              notes: cust.notes,
            });

            this.phonesArray.clear();
            if (cust.phones && cust.phones.length > 0) {
              cust.phones.forEach(p => {
                this.addPhone(p.phone_number, p.is_primary, p.type);
              });
            } else {
              this.addPhone('', true, 'Mobile');
            }
          }
        } finally {
          this.loadingCustomer = false;
          this.cdr.detectChanges();
        }
      },
      error: () => {
        this.loadingCustomer = false;
        this.toastService.error('Failed to load customer profile.');
        this.router.navigate(['/customers']);
        this.cdr.detectChanges();
      }
    });
  }

  checkDuplicate() {
    const email = this.customerForm.get('email')?.value;
    const primaryPhone = this.phonesArray.at(0)?.get('phone_number')?.value;

    if (!email && !primaryPhone) return;

    this.customerService.checkDuplicate(email, primaryPhone, this.customerId || undefined).subscribe({
      next: (res) => {
        if (res.is_duplicate && res.duplicate_customer) {
          this.duplicateWarning = res.duplicate_customer;
        } else {
          this.duplicateWarning = null;
        }
      }
    });
  }

  onSubmit() {
    this.submitted = true;
    if (this.customerForm.invalid) {
      this.toastService.error('Please fill in all required fields accurately.');
      return;
    }

    this.saving = true;
    const payload = this.customerForm.value;

    if (this.isEditMode && this.customerId) {
      this.customerService.updateCustomer(this.customerId, payload).subscribe({
        next: () => {
          this.saving = false;
          this.toastService.success('Customer profile updated successfully.');
          this.router.navigate(['/customers']);
        },
        error: (err) => {
          this.saving = false;
          this.toastService.error(err.error?.message || 'Failed to update customer.');
        }
      });
    } else {
      this.customerService.createCustomer(payload).subscribe({
        next: () => {
          this.saving = false;
          this.toastService.success('Customer registered successfully.');
          this.router.navigate(['/customers']);
        },
        error: (err) => {
          this.saving = false;
          this.toastService.error(err.error?.message || 'Failed to create customer.');
        }
      });
    }
  }
}
