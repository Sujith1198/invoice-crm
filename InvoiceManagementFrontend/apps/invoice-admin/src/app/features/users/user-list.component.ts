import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ToastService, UserService } from '@invoice-mgmt/shared-services';
import { PaginatedResponse, User } from '@invoice-mgmt/shared-models';
import { formatDate, getRoleBadgeClass, getStatusBadgeClass } from '@invoice-mgmt/shared-utils';
import { ConfirmDialogComponent, EmptyStateComponent, LoadingSpinnerComponent, PaginationComponent } from '@invoice-mgmt/shared-ui';

@Component({
  selector: 'app-user-list',
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
  templateUrl: './user-list.component.html',
  styleUrls: ['./user-list.component.scss']
})
export class UserListComponent implements OnInit {
  private userService = inject(UserService);
  private toastService = inject(ToastService);
  private cdr = inject(ChangeDetectorRef);

  loading = true;
  pagination: PaginatedResponse<User> | null = null;

  searchQuery = '';
  selectedRole = '';
  selectedStatus = '';
  currentPage = 1;

  isDeleteModalOpen = false;
  userToDelete: User | null = null;

  formatDate = formatDate;
  getBadgeClass = getStatusBadgeClass;
  getRoleBadge = getRoleBadgeClass;

  private screenLabels: Record<string, string> = {
    'dashboard': '📊 Dashboard',
    'customers': '👥 Customers',
    'items': '📦 Items',
    'invoice_generate': '📄 Generate Invoice',
    'invoices': '📑 Invoices List',
    'reports': '📈 Reports',
    'settings': '⚙️ Settings',
    'users': '👤 Users',
  };

  getScreenLabel(key: string): string {
    return this.screenLabels[key] || key;
  }

  ngOnInit() {
    this.loadUsers();
  }

  loadUsers(page = 1) {
    this.loading = true;
    this.currentPage = page;
    this.userService.getUsers({
      page: this.currentPage,
      per_page: 10,
      search: this.searchQuery,
      role: this.selectedRole,
      status: this.selectedStatus,
    }).subscribe({
      next: (res) => {
        this.pagination = res?.data || null;
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.loading = false;
        this.toastService.error('Failed to load users list.');
        this.cdr.detectChanges();
      }
    });
  }

  onSearch() {
    this.loadUsers(1);
  }

  onFilterChange() {
    this.loadUsers(1);
  }

  resetFilters() {
    this.searchQuery = '';
    this.selectedRole = '';
    this.selectedStatus = '';
    this.loadUsers(1);
  }

  onPageChange(page: number) {
    this.loadUsers(page);
  }

  openDeleteConfirm(user: User) {
    if (user.id === 1) {
      this.toastService.error('Primary Super Administrator cannot be deleted.');
      return;
    }
    this.userToDelete = user;
    this.isDeleteModalOpen = true;
  }

  confirmDelete() {
    if (!this.userToDelete) return;
    this.userService.deleteUser(this.userToDelete.id).subscribe({
      next: () => {
        this.toastService.success(`User ${this.userToDelete?.name} has been deleted.`);
        this.isDeleteModalOpen = false;
        this.userToDelete = null;
        this.loadUsers(this.currentPage);
      },
      error: (err) => {
        this.toastService.error(err.error?.message || 'Failed to delete user.');
        this.isDeleteModalOpen = false;
      }
    });
  }
}
