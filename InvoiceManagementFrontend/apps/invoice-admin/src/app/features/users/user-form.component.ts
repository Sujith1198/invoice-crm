import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { ToastService, UserService } from '@invoice-mgmt/shared-services';
import { LoadingSpinnerComponent } from '@invoice-mgmt/shared-ui';

export interface ScreenOption {
  key: string;
  name: string;
  icon: string;
  description: string;
}

@Component({
  selector: 'app-user-form',
  standalone: true,
  imports: [CommonModule, RouterModule, ReactiveFormsModule, LoadingSpinnerComponent],
  templateUrl: './user-form.component.html',
  styleUrls: ['./user-form.component.scss']
})
export class UserFormComponent implements OnInit {
  private fb = inject(FormBuilder);
  private userService = inject(UserService);
  private toastService = inject(ToastService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private cdr = inject(ChangeDetectorRef);

  isEditMode = false;
  userId: number | null = null;
  loadingUser = false;
  saving = false;
  submitted = false;
  showPassword = false;

  availableScreens: ScreenOption[] = [
    { key: 'dashboard', name: 'Dashboard', icon: '📊', description: 'Financial & Sales overview stats, charts, and collections' },
    { key: 'customers', name: 'Customers', icon: '👥', description: 'Manage client directory, corporate entities & contact numbers' },
    { key: 'items', name: 'Items & Products', icon: '📦', description: 'Manage item catalog, pricing, GST & customs declarations' },
    { key: 'invoice_generate', name: 'Invoice Generate', icon: '📄', description: 'Interactive tax invoice creation, calculation & preview' },
    { key: 'invoices', name: 'Invoices List', icon: '📑', description: 'View invoice ledger, record payments & download PDFs' },
    { key: 'reports', name: 'Reports', icon: '📈', description: '11 Financial, GST tax, and customer/item export statements' },
    { key: 'settings', name: 'Company Settings', icon: '⚙️', description: 'Corporate profile, GSTIN, invoice prefix & default currency' },
    { key: 'users', name: 'User Management', icon: '👤', description: 'Manage team user credentials, roles & screen permissions' },
  ];

  selectedScreens: string[] = ['dashboard', 'invoices'];

  userForm: FormGroup = this.fb.group({
    name: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    password: [''],
    role: ['user', Validators.required],
    status: ['active', Validators.required],
  });

  get f() {
    return this.userForm.controls;
  }

  togglePasswordVisibility() {
    this.showPassword = !this.showPassword;
  }

  ngOnInit() {
    const idParam = this.route.snapshot.paramMap.get('id');
    if (idParam) {
      this.isEditMode = true;
      this.userId = parseInt(idParam, 10);
      this.loadUser(this.userId);
    } else {
      // New user: password is required
      this.userForm.get('password')?.setValidators([Validators.required, Validators.minLength(6)]);
      this.userForm.get('password')?.updateValueAndValidity();
      this.applyRolePreset();
    }
  }

  isScreenSelected(key: string): boolean {
    return this.selectedScreens.includes(key);
  }

  toggleScreen(key: string) {
    if (this.selectedScreens.includes(key)) {
      this.selectedScreens = this.selectedScreens.filter(k => k !== key);
    } else {
      this.selectedScreens = [...this.selectedScreens, key];
    }
  }

  selectAllScreens() {
    this.selectedScreens = this.availableScreens.map(s => s.key);
  }

  clearAllScreens() {
    this.selectedScreens = [];
  }

  onRoleChange() {
    this.applyRolePreset();
  }

  applyRolePreset() {
    const role = this.userForm.get('role')?.value;
    switch (role) {
      case 'admin':
        this.selectedScreens = this.availableScreens.map(s => s.key);
        break;
      case 'manager':
        this.selectedScreens = ['dashboard', 'customers', 'items', 'invoice_generate', 'invoices', 'reports'];
        break;
      case 'team lead':
        this.selectedScreens = ['dashboard', 'customers', 'items', 'invoice_generate', 'invoices'];
        break;
      case 'user':
      default:
        this.selectedScreens = ['dashboard', 'invoices', 'items'];
        break;
    }
  }

  loadUser(id: number) {
    this.loadingUser = true;
    this.userService.getUser(id).subscribe({
      next: (res) => {
        try {
          const u = res?.data;
          if (u) {
            this.userForm.patchValue({
              name: u.name,
              email: u.email,
              role: u.role || 'user',
              status: u.status || 'active',
            });
            if (u.permissions && Array.isArray(u.permissions)) {
              this.selectedScreens = [...u.permissions];
            } else if (u.role === 'admin') {
              this.selectAllScreens();
            }
          }
        } finally {
          this.loadingUser = false;
          this.cdr.detectChanges();
        }
      },
      error: () => {
        this.loadingUser = false;
        this.toastService.error('Failed to load user profile.');
        this.router.navigate(['/users']);
        this.cdr.detectChanges();
      }
    });
  }

  onSubmit() {
    this.submitted = true;
    if (this.userForm.invalid) {
      this.toastService.error('Please verify all required user fields.');
      return;
    }

    if (this.selectedScreens.length === 0) {
      this.toastService.warning('Please select at least one screen permission for this user.');
      return;
    }

    this.saving = true;
    const formVal = this.userForm.value;
    const payload: any = {
      name: formVal.name,
      email: formVal.email,
      role: formVal.role,
      status: formVal.status,
      permissions: this.selectedScreens,
    };

    if (formVal.password) {
      payload.password = formVal.password;
    }

    if (this.isEditMode && this.userId) {
      this.userService.updateUser(this.userId, payload).subscribe({
        next: () => {
          this.saving = false;
          this.toastService.success(`User ${payload.name} updated successfully.`);
          this.router.navigate(['/users']);
        },
        error: (err) => {
          this.saving = false;
          this.toastService.error(err.error?.message || 'Failed to update user.');
        }
      });
    } else {
      this.userService.createUser(payload).subscribe({
        next: () => {
          this.saving = false;
          this.toastService.success(`User ${payload.name} created successfully.`);
          this.router.navigate(['/users']);
        },
        error: (err) => {
          this.saving = false;
          this.toastService.error(err.error?.message || 'Failed to create user.');
        }
      });
    }
  }
}
