import { Component, OnInit, inject, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ItemService, ToastService } from '@invoice-mgmt/shared-services';
import { Item, PaginatedResponse } from '@invoice-mgmt/shared-models';
import { formatCurrency, getStatusBadgeClass } from '@invoice-mgmt/shared-utils';
import { ConfirmDialogComponent, EmptyStateComponent, LoadingSpinnerComponent, PaginationComponent } from '@invoice-mgmt/shared-ui';

@Component({
  selector: 'app-item-list',
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
  templateUrl: './item-list.component.html',
  styleUrls: ['./item-list.component.scss']
})
export class ItemListComponent implements OnInit {
  private itemService = inject(ItemService);
  private toastService = inject(ToastService);
  private cdr = inject(ChangeDetectorRef);

  loading = true;
  pagination: PaginatedResponse<Item> | null = null;

  searchQuery = '';
  selectedStatus = '';
  currentPage = 1;

  isDeleteModalOpen = false;
  itemToDelete: Item | null = null;

  formatCurrency = formatCurrency;
  getBadgeClass = getStatusBadgeClass;

  ngOnInit() {
    this.loadItems();
  }

  loadItems(page = 1) {
    this.loading = true;
    this.currentPage = page;
    this.itemService.getItems({
      page: this.currentPage,
      per_page: 10,
      search: this.searchQuery,
      status: this.selectedStatus,
    }).subscribe({
      next: (res) => {
        this.pagination = res?.data || null;
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.loading = false;
        this.toastService.error('Failed to load catalog items.');
        this.cdr.detectChanges();
      }
    });
  }

  onSearch() {
    this.loadItems(1);
  }

  onFilterChange() {
    this.loadItems(1);
  }

  resetFilters() {
    this.searchQuery = '';
    this.selectedStatus = '';
    this.loadItems(1);
  }

  onPageChange(page: number) {
    this.loadItems(page);
  }

  openDeleteConfirm(item: Item) {
    this.itemToDelete = item;
    this.isDeleteModalOpen = true;
  }

  confirmDelete() {
    if (!this.itemToDelete) return;
    this.itemService.deleteItem(this.itemToDelete.id).subscribe({
      next: () => {
        this.toastService.success(`Item "${this.itemToDelete?.item_name}" deleted.`);
        this.isDeleteModalOpen = false;
        this.itemToDelete = null;
        this.loadItems(this.currentPage);
      },
      error: () => {
        this.toastService.error('Could not delete item.');
        this.isDeleteModalOpen = false;
      }
    });
  }
}
