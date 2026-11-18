import { Component, EventEmitter, HostListener, Input, OnInit, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, NavigationEnd, RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { filter } from 'rxjs';
import { AuthService, NotificationService, ToastService } from '@invoice-mgmt/shared-services';
import { PendingNotification, NotificationResponse } from '@invoice-mgmt/shared-models';

const STORAGE_KEY_DISMISSED = 'orange_dismissed_notifications';

@Component({
  selector: 'app-topbar',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  templateUrl: './topbar.component.html',
  styleUrls: ['./topbar.component.scss']
})
export class TopbarComponent implements OnInit {
  @Output() toggleMobileSidebar = new EventEmitter<void>();

  private router = inject(Router);
  private authService = inject(AuthService);
  private notificationService = inject(NotificationService);
  private toastService = inject(ToastService);

  pageTitle = 'Dashboard';

  // Notification State
  isNotificationOpen = false;
  loading = false;
  selectedTab: 'all' | 'daily' | 'fifteen_days' | 'monthly' = 'all';

  rawNotifications: PendingNotification[] = [];
  dismissedIds = new Set<number>();
  approvedIds = new Set<number>();
  approvingIds = new Set<number>();

  constructor() {
    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe((event: any) => {
      this.updateTitle(event.urlAfterRedirects || event.url);
    });
    this.updateTitle(this.router.url);

    // Load dismissed IDs from localStorage
    this.loadDismissedIds();
  }

  ngOnInit(): void {
    // Initial fetch of notifications
    this.loadNotifications();

    // Listen to global notification refresh triggers
    this.notificationService.refresh$.subscribe(() => {
      this.loadNotifications(false);
    });
  }

  @HostListener('document:click', ['$event'])
  onDocumentClick(event: MouseEvent) {
    const target = event.target as HTMLElement;
    if (this.isNotificationOpen && !target.closest('.bell-btn') && !target.closest('.notification-dropdown')) {
      this.isNotificationOpen = false;
    }
  }

  private updateTitle(url: string) {
    if (url.includes('dashboard')) this.pageTitle = 'Dashboard';
    else if (url.includes('customers')) this.pageTitle = 'Customer Management';
    else if (url.includes('items')) this.pageTitle = 'Item Management';
    else if (url.includes('invoices/generate')) this.pageTitle = 'Generate Tax Invoice';
    else if (url.includes('invoices')) this.pageTitle = 'Invoice Management';
    else if (url.includes('reports')) this.pageTitle = 'Financial & GST Reports';
    else if (url.includes('settings')) this.pageTitle = 'Company Settings';
    else if (url.includes('users')) this.pageTitle = 'User Management';
  }

  get userName(): string {
    const user = this.authService.getUser();
    return user ? user.name : 'Administrator';
  }

  get userRole(): string {
    const user = this.authService.getUser();
    return user?.role || 'admin';
  }

  get userInitial(): string {
    return this.userName.charAt(0).toUpperCase();
  }

  logout() {
    this.authService.logout();
    this.router.navigate(['/login']);
  }

  onSearch(event: any) {
    const query = event.target.value;
    if (query?.trim()) {
      this.router.navigate(['/invoices'], { queryParams: { search: query.trim() } });
    }
  }

  // --- Notifications Logic ---

  toggleNotifications() {
    this.isNotificationOpen = !this.isNotificationOpen;
    if (this.isNotificationOpen) {
      this.loadNotifications();
    }
  }

  closeNotifications() {
    this.isNotificationOpen = false;
  }

  loadNotifications(showSpinner = true) {
    if (showSpinner) {
      this.loading = true;
    }
    this.notificationService.getPendingPayments().subscribe({
      next: (res: NotificationResponse) => {
        this.rawNotifications = res.all || [];
        this.loading = false;
      },
      error: () => {
        this.loading = false;
      }
    });
  }

  setTab(tab: 'all' | 'daily' | 'fifteen_days' | 'monthly') {
    this.selectedTab = tab;
  }

  getTabName(tab: string): string {
    switch (tab) {
      case 'daily': return 'Daily Pay';
      case 'fifteen_days': return '15 Days Pay';
      case 'monthly': return 'Monthly Pay';
      default: return 'All Categories';
    }
  }

  private filterByTab(items: PendingNotification[], tab: string): PendingNotification[] {
    if (tab === 'all') return items;
    return items.filter(inv => {
      const method = (inv.payment_method || '').toLowerCase();
      if (tab === 'daily') return method.includes('daily');
      if (tab === 'fifteen_days') return method.includes('15');
      if (tab === 'monthly') return method.includes('monthly');
      return false;
    });
  }

  get activeNotifications(): PendingNotification[] {
    return this.rawNotifications.filter(item => !this.dismissedIds.has(item.invoice_id));
  }

  get activePendingCount(): number {
    return this.activeNotifications.filter(item => !this.approvedIds.has(item.invoice_id)).length;
  }

  get currentTabItems(): PendingNotification[] {
    return this.filterByTab(this.activeNotifications, this.selectedTab);
  }

  getTabCount(tab: 'all' | 'daily' | 'fifteen_days' | 'monthly'): number {
    const list = this.filterByTab(this.activeNotifications, tab);
    return list.filter(item => !this.approvedIds.has(item.invoice_id)).length;
  }

  trackByInvoiceId(_index: number, item: PendingNotification): number {
    return item.invoice_id;
  }

  absDays(days: number): number {
    return Math.abs(days || 0);
  }

  isApproving(invoiceId: number): boolean {
    return this.approvingIds.has(invoiceId);
  }

  isItemApproved(invoiceId: number): boolean {
    return this.approvedIds.has(invoiceId);
  }

  approvePayment(item: PendingNotification) {
    if (this.isApproving(item.invoice_id) || this.isItemApproved(item.invoice_id)) {
      return;
    }

    this.approvingIds.add(item.invoice_id);

    this.notificationService.approvePayment(item.invoice_id).subscribe({
      next: (res: any) => {
        this.approvingIds.delete(item.invoice_id);
        this.approvedIds.add(item.invoice_id);
        this.toastService.success(
          res.message || `Payment for customer "${item.customer_name}" successfully approved and marked Paid!`,
          'Payment Approved'
        );
      },
      error: (err: any) => {
        this.approvingIds.delete(item.invoice_id);
        const errMsg = err?.error?.message || err?.message || 'Failed to approve payment.';
        this.toastService.error(errMsg, 'Approval Failed');
      }
    });
  }

  dismissNotification(invoiceId: number) {
    this.dismissedIds.add(invoiceId);
    this.saveDismissedIds();
  }

  clearAllNotifications() {
    this.activeNotifications.forEach(item => {
      this.dismissedIds.add(item.invoice_id);
    });
    this.saveDismissedIds();
    this.toastService.show('info', 'Notifications Cleared', 'All pending notifications cleared from view.');
  }

  restoreClearedNotifications() {
    this.dismissedIds.clear();
    this.saveDismissedIds();
    this.toastService.show('info', 'Restored', 'Cleared notifications have been restored.');
    this.loadNotifications();
  }

  private loadDismissedIds() {
    try {
      const stored = localStorage.getItem(STORAGE_KEY_DISMISSED);
      if (stored) {
        const ids: number[] = JSON.parse(stored);
        this.dismissedIds = new Set(ids);
      }
    } catch {
      this.dismissedIds = new Set();
    }
  }

  private saveDismissedIds() {
    try {
      localStorage.setItem(STORAGE_KEY_DISMISSED, JSON.stringify(Array.from(this.dismissedIds)));
    } catch (e) {
      console.warn('Could not save dismissed notifications to localStorage', e);
    }
  }
}
