import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { SettingService, ToastService } from '@invoice-mgmt/shared-services';
import { LoadingSpinnerComponent } from '@invoice-mgmt/shared-ui';

@Component({
  selector: 'app-settings',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, LoadingSpinnerComponent],
  templateUrl: './settings.component.html',
  styleUrls: ['./settings.component.scss']
})
export class SettingsComponent implements OnInit {
  private fb = inject(FormBuilder);
  private settingService = inject(SettingService);
  private toastService = inject(ToastService);
  private cdr = inject(ChangeDetectorRef);

  loading = true;
  saving = false;
  submitted = false;

  settingsForm: FormGroup = this.fb.group({
    company_name: ['', Validators.required],
    company_logo: [''],
    company_address: [''],
    phone_number: [''],
    email: ['', [Validators.email]],
    gstin: ['', Validators.required],
    invoice_prefix: ['INV-', Validators.required],
    default_currency: ['INR', Validators.required],
    default_currency_symbol: ['₹', Validators.required],
    default_payment_terms: [''],
    invoice_footer: [''],
    terms_and_conditions: [''],
  });

  get f() {
    return this.settingsForm.controls;
  }

  ngOnInit() {
    this.loadSettings();
  }

  loadSettings() {
    this.loading = true;
    this.settingService.getSettings().subscribe({
      next: (res) => {
        try {
          if (res?.data) {
            this.settingsForm.patchValue(res.data);
          }
        } finally {
          this.loading = false;
          this.cdr.detectChanges();
        }
      },
      error: () => {
        this.loading = false;
        this.toastService.error('Could not load company settings.');
        this.cdr.detectChanges();
      }
    });
  }

  onCurrencyChange() {
    const symbols: Record<string, string> = {
      'INR': '₹',
      'USD': '$',
      'EUR': '€',
      'GBP': '£',
      'AED': 'AED',
      'SGD': 'S$',
    };
    const code = this.settingsForm.get('default_currency')?.value || 'INR';
    this.settingsForm.patchValue({
      default_currency_symbol: symbols[code] || '₹'
    });
  }

  onSubmit() {
    this.submitted = true;
    if (this.settingsForm.invalid) {
      this.toastService.error('Please verify company settings inputs.');
      return;
    }

    this.saving = true;
    this.settingService.updateSettings(this.settingsForm.value).subscribe({
      next: () => {
        this.saving = false;
        this.toastService.success('Company settings saved successfully!');
      },
      error: (err) => {
        this.saving = false;
        this.toastService.error(err.error?.message || 'Failed to save settings.');
      }
    });
  }
}
