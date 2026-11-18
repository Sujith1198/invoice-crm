import { Component, EventEmitter, Input, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ToastService, ToastMessage } from '@invoice-mgmt/shared-services';

@Component({
  selector: 'ui-toast-container',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
      @for (toast of toastService.messages$ | async; track toast.id) {
        <div class="toast show mb-2 shadow-lg border-0" [ngClass]="'toast-' + toast.type" role="alert">
          <div class="toast-header border-bottom-0 pb-1">
            <span class="toast-badge me-2" [ngClass]="'badge-' + toast.type"></span>
            <strong class="me-auto text-dark">{{ toast.title }}</strong>
            <button type="button" class="btn-close" (click)="toastService.remove(toast.id)"></button>
          </div>
          <div class="toast-body pt-0 text-secondary">
            {{ toast.message }}
          </div>
        </div>
      }
    </div>
  `,
  styles: [`
    .toast {
      min-width: 300px;
      border-radius: 10px;
      overflow: hidden;
      background: #ffffff;
      border-left: 4px solid #FFAB00 !important;
      box-shadow: 0 10px 25px rgba(0,0,0,0.1);
      animation: slideIn 0.25s ease-out;
    }
    .toast-success { border-left-color: #2E7D32 !important; }
    .toast-error { border-left-color: #D32F2F !important; }
    .toast-warning { border-left-color: #FF6200 !important; }
    .toast-info { border-left-color: #0288D1 !important; }
    .toast-badge {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      display: inline-block;
    }
    .badge-success { background: #2E7D32; }
    .badge-error { background: #D32F2F; }
    .badge-warning { background: #FF6200; }
    .badge-info { background: #0288D1; }
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
  `]
})
export class ToastContainerComponent {
  public toastService = inject(ToastService);
}

@Component({
  selector: 'ui-confirm-dialog',
  standalone: true,
  imports: [CommonModule],
  template: `
    @if (isOpen) {
      <div class="modal-backdrop fade show"></div>
      <div class="modal fade show d-block" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content shadow border-0" style="border-radius: 14px;">
            <div class="modal-body p-4 text-center">
              <div class="icon-circle mb-3 mx-auto" [class.danger]="isDanger">
                @if (isDanger) {
                  <span class="warning-icon">⚠️</span>
                } @else {
                  <span class="warning-icon">❓</span>
                }
              </div>
              <h5 class="fw-bold mb-2">{{ title }}</h5>
              <p class="text-muted mb-4">{{ message }}</p>
              <div class="d-flex justify-content-center gap-3">
                <button type="button" class="btn btn-light px-4" (click)="cancel()">
                  {{ cancelText }}
                </button>
                <button type="button" class="btn px-4" [ngClass]="isDanger ? 'btn-danger' : 'btn-orange'" (click)="confirm()">
                  {{ confirmText }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    }
  `,
  styles: [`
    .icon-circle {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: #FFF3E0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
    }
    .icon-circle.danger {
      background: #FFEBEE;
    }
    .btn-orange {
      background-color: #FFAB00;
      color: #242424;
      font-weight: 600;
      border: none;
    }
    .btn-orange:hover {
      background-color: #FF9D00;
      color: #111111;
    }
  `]
})
export class ConfirmDialogComponent {
  @Input() isOpen = false;
  @Input() title = 'Confirm Action';
  @Input() message = 'Are you sure you want to proceed?';
  @Input() confirmText = 'Confirm';
  @Input() cancelText = 'Cancel';
  @Input() isDanger = false;

  @Output() onConfirm = new EventEmitter<void>();
  @Output() onCancel = new EventEmitter<void>();

  confirm() {
    this.onConfirm.emit();
  }

  cancel() {
    this.onCancel.emit();
  }
}

@Component({
  selector: 'ui-pagination',
  standalone: true,
  imports: [CommonModule],
  template: `
    @if (total > 0) {
      <div class="d-flex justify-content-between align-items-center py-3">
        <div class="text-muted small">
          Showing <span class="fw-semibold">{{ from || 0 }}</span> to <span class="fw-semibold">{{ to || 0 }}</span> of <span class="fw-semibold">{{ total }}</span> entries
        </div>
        <nav aria-label="Page navigation">
          <ul class="pagination pagination-sm mb-0">
            <li class="page-item" [class.disabled]="currentPage <= 1">
              <button class="page-link" [disabled]="currentPage <= 1" (click)="changePage(currentPage - 1)">Previous</button>
            </li>
            @for (page of pages; track page) {
              <li class="page-item" [class.active]="currentPage === page">
                <button class="page-link" (click)="changePage(page)">{{ page }}</button>
              </li>
            }
            <li class="page-item" [class.disabled]="currentPage >= totalPages">
              <button class="page-link" [disabled]="currentPage >= totalPages" (click)="changePage(currentPage + 1)">Next</button>
            </li>
          </ul>
        </nav>
      </div>
    }
  `,
  styles: [`
    .page-link {
      color: #242424;
      border-color: #EAEAEA;
      cursor: pointer;
    }
    .page-link:hover {
      background-color: #FFF3D6;
      border-color: #FFAB00;
      color: #D46B08;
    }
    .page-item.active .page-link {
      background-color: #FFAB00;
      border-color: #FFAB00;
      color: #242424;
      font-weight: 700;
    }
    .page-item.disabled .page-link,
    .page-link:disabled {
      color: #8c8c8c;
      pointer-events: none;
      background-color: #FAFAFA;
      border-color: #EAEAEA;
      cursor: not-allowed;
    }
  `]
})
export class PaginationComponent {
  @Input() currentPage = 1;
  @Input() totalPages = 1;
  @Input() total = 0;
  @Input() from = 0;
  @Input() to = 0;

  @Output() pageChange = new EventEmitter<number>();

  get pages(): number[] {
    const list: number[] = [];
    const maxVisible = 5;
    let start = Math.max(1, this.currentPage - 2);
    let end = Math.min(this.totalPages, start + maxVisible - 1);
    if (end - start < maxVisible - 1) {
      start = Math.max(1, end - maxVisible + 1);
    }
    for (let i = start; i <= end; i++) {
      list.push(i);
    }
    return list;
  }

  changePage(page: number) {
    if (page >= 1 && page <= this.totalPages && page !== this.currentPage) {
      this.pageChange.emit(page);
    }
  }
}

@Component({
  selector: 'ui-loading',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="d-flex flex-column align-items-center justify-content-center p-5">
      <div class="spinner-border text-warning" style="width: 3rem; height: 3rem; color: #FFAB00 !important;" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="text-muted mt-3 mb-0">{{ text }}</p>
    </div>
  `
})
export class LoadingSpinnerComponent {
  @Input() text = 'Loading data, please wait...';
}

@Component({
  selector: 'ui-empty-state',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="text-center py-5">
      <div class="empty-icon mb-3">📂</div>
      <h5 class="fw-bold text-dark">{{ title }}</h5>
      <p class="text-muted mb-3 mx-auto" style="max-width: 400px;">{{ description }}</p>
      @if (actionText) {
        <button class="btn btn-orange" (click)="onAction.emit()">
          {{ actionText }}
        </button>
      }
    </div>
  `,
  styles: [`
    .empty-icon {
      font-size: 48px;
    }
    .btn-orange {
      background-color: #FFAB00;
      color: #242424;
      font-weight: 600;
      border: none;
      padding: 8px 20px;
      border-radius: 8px;
    }
    .btn-orange:hover {
      background-color: #FF9D00;
    }
  `]
})
export class EmptyStateComponent {
  @Input() title = 'No records found';
  @Input() description = 'There are no items to display matching your criteria.';
  @Input() actionText?: string;
  @Output() onAction = new EventEmitter<void>();
}

@Component({
  selector: 'ui-stat-card',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="card stat-card shadow-sm border-0 h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <div class="text-muted text-uppercase small fw-semibold tracking-wide mb-1">{{ label }}</div>
            <h3 class="fw-bold mb-0 text-dark">{{ value }}</h3>
          </div>
          <div class="icon-wrap" [ngStyle]="{'background-color': iconBg}">
            <span class="fs-4">{{ icon }}</span>
          </div>
        </div>
        <div class="d-flex align-items-center text-muted small">
          @if (badgeText) {
            <span class="badge rounded-pill me-2" [ngClass]="isPositive ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
              {{ badgeText }}
            </span>
          }
          <span>{{ comparisonText }}</span>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .stat-card {
      border-radius: 12px;
      transition: transform 0.2s, box-shadow 0.2s;
      border-top: 3px solid #FFAB00 !important;
    }
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
    }
    .icon-wrap {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .tracking-wide {
      letter-spacing: 0.5px;
    }
  `]
})
export class StatCardComponent {
  @Input() label = '';
  @Input() value: string | number = '';
  @Input() icon = '📊';
  @Input() iconBg = '#FFF8E1';
  @Input() badgeText?: string;
  @Input() isPositive = true;
  @Input() comparisonText = '';
}

