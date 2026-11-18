import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable, BehaviorSubject, tap } from 'rxjs';
import {
  ApiResponse,
  AuthResponse,
  CompanySetting,
  Customer,
  CustomerFormData,
  DashboardData,
  Invoice,
  Item,
  NotificationResponse,
  PaginatedResponse,
  Payment,
  PendingNotification,
  User,
} from '@invoice-mgmt/shared-models';

export const API_BASE_URL = (typeof window !== 'undefined' && (window as any).__ORANGE_API_URL__)
  ? (window as any).__ORANGE_API_URL__
  : 'http://127.0.0.1:8000/api';

export interface ToastMessage {
  id: string;
  type: 'success' | 'error' | 'info' | 'warning';
  title: string;
  message: string;
}

@Injectable({ providedIn: 'root' })
export class ToastService {
  private toasts$ = new BehaviorSubject<ToastMessage[]>([]);
  public messages$ = this.toasts$.asObservable();

  show(type: 'success' | 'error' | 'info' | 'warning', title: string, message: string, duration = 4000) {
    const id = Math.random().toString(36).substring(2, 9);
    const newToast: ToastMessage = { id, type, title, message };
    this.toasts$.next([...this.toasts$.value, newToast]);

    setTimeout(() => {
      this.remove(id);
    }, duration);
  }

  success(message: string, title = 'Success') {
    this.show('success', title, message);
  }

  error(message: string, title = 'Error') {
    this.show('error', title, message, 6000);
  }

  info(message: string, title = 'Information') {
    this.show('info', title, message);
  }

  warning(message: string, title = 'Warning') {
    this.show('warning', title, message, 5000);
  }

  remove(id: string) {
    this.toasts$.next(this.toasts$.value.filter(t => t.id !== id));
  }
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);
  private currentUser$ = new BehaviorSubject<User | null>(this.getStoredUser());
  public user$ = this.currentUser$.asObservable();

  private getStoredUser(): User | null {
    const raw = localStorage.getItem('orange_user');
    if (!raw) return null;
    try {
      return JSON.parse(raw);
    } catch {
      return null;
    }
  }

  public getToken(): string | null {
    return localStorage.getItem('orange_token');
  }

  public isAuthenticated(): boolean {
    return !!this.getToken();
  }

  public getUser(): User | null {
    return this.currentUser$.value;
  }

  public hasPermission(screen: string): boolean {
    const user = this.getUser();
    if (!user) return false;
    if (user.role === 'admin') return true;
    if (!user.permissions || !Array.isArray(user.permissions)) return false;
    return user.permissions.includes(screen);
  }

  public isAdmin(): boolean {
    const user = this.getUser();
    return user?.role === 'admin';
  }

  login(credentials: { email: string; password: string }): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${API_BASE_URL}/login`, credentials).pipe(
      tap(res => {
        if (res.token) {
          localStorage.setItem('orange_token', res.token);
          localStorage.setItem('orange_user', JSON.stringify(res.user));
          this.currentUser$.next(res.user);
        }
      })
    );
  }

  logout(): void {
    const token = this.getToken();
    if (token) {
      this.http.post(`${API_BASE_URL}/logout`, {}).subscribe({
        error: () => { }
      });
    }
    localStorage.removeItem('orange_token');
    localStorage.removeItem('orange_user');
    this.currentUser$.next(null);
  }

  sendPasswordOtp(email: string): Observable<ApiResponse<{ email: string; delivered_to?: string; debug_otp?: string }>> {
    return this.http.post<ApiResponse<{ email: string; delivered_to?: string; debug_otp?: string }>>(`${API_BASE_URL}/forgot-password`, { email });
  }

  verifyPasswordOtp(email: string, otp: string): Observable<ApiResponse<any>> {
    return this.http.post<ApiResponse<any>>(`${API_BASE_URL}/verify-otp`, { email, otp });
  }

  resetPasswordWithOtp(payload: { email: string; otp: string; password: string }): Observable<ApiResponse<any>> {
    return this.http.post<ApiResponse<any>>(`${API_BASE_URL}/reset-password`, payload);
  }
}

@Injectable({ providedIn: 'root' })
export class DashboardService {
  private http = inject(HttpClient);

  getDashboardData(): Observable<ApiResponse<DashboardData>> {
    return this.http.get<ApiResponse<DashboardData>>(`${API_BASE_URL}/dashboard`);
  }
}

@Injectable({ providedIn: 'root' })
export class CustomerService {
  private http = inject(HttpClient);

  getCustomers(params: any = {}): Observable<ApiResponse<PaginatedResponse<Customer>>> {
    let httpParams = new HttpParams();
    Object.keys(params).forEach(key => {
      if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
        httpParams = httpParams.set(key, params[key]);
      }
    });
    return this.http.get<ApiResponse<PaginatedResponse<Customer>>>(`${API_BASE_URL}/customers`, { params: httpParams });
  }

  getCustomer(id: number): Observable<ApiResponse<{ customer: Customer; metrics: any }>> {
    return this.http.get<ApiResponse<{ customer: Customer; metrics: any }>>(`${API_BASE_URL}/customers/${id}`);
  }

  createCustomer(data: CustomerFormData): Observable<ApiResponse<Customer>> {
    return this.http.post<ApiResponse<Customer>>(`${API_BASE_URL}/customers`, data);
  }

  updateCustomer(id: number, data: CustomerFormData): Observable<ApiResponse<Customer>> {
    return this.http.put<ApiResponse<Customer>>(`${API_BASE_URL}/customers/${id}`, data);
  }

  deleteCustomer(id: number): Observable<ApiResponse<any>> {
    return this.http.delete<ApiResponse<any>>(`${API_BASE_URL}/customers/${id}`);
  }

  searchCustomers(query: string): Observable<ApiResponse<Customer[]>> {
    return this.http.get<ApiResponse<Customer[]>>(`${API_BASE_URL}/customers/search`, {
      params: { q: query }
    });
  }

  getCities(): Observable<ApiResponse<string[]>> {
    return this.http.get<ApiResponse<string[]>>(`${API_BASE_URL}/customers/cities`);
  }

  checkDuplicate(email: string, phone: string, excludeId?: number): Observable<{ is_duplicate: boolean; duplicate_customer?: any }> {
    return this.http.post<{ is_duplicate: boolean; duplicate_customer?: any }>(`${API_BASE_URL}/customers/check-duplicate`, {
      email,
      phone,
      exclude_id: excludeId
    });
  }
}

@Injectable({ providedIn: 'root' })
export class ItemService {
  private http = inject(HttpClient);

  getItems(params: any = {}): Observable<ApiResponse<PaginatedResponse<Item>>> {
    let httpParams = new HttpParams();
    Object.keys(params).forEach(key => {
      if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
        httpParams = httpParams.set(key, params[key]);
      }
    });
    return this.http.get<ApiResponse<PaginatedResponse<Item>>>(`${API_BASE_URL}/items`, { params: httpParams });
  }

  getItem(id: number): Observable<ApiResponse<Item>> {
    return this.http.get<ApiResponse<Item>>(`${API_BASE_URL}/items/${id}`);
  }

  createItem(data: Partial<Item>): Observable<ApiResponse<Item>> {
    return this.http.post<ApiResponse<Item>>(`${API_BASE_URL}/items`, data);
  }

  updateItem(id: number, data: Partial<Item>): Observable<ApiResponse<Item>> {
    return this.http.put<ApiResponse<Item>>(`${API_BASE_URL}/items/${id}`, data);
  }

  deleteItem(id: number): Observable<ApiResponse<any>> {
    return this.http.delete<ApiResponse<any>>(`${API_BASE_URL}/items/${id}`);
  }

  searchItems(query: string = '', customerId?: number | null): Observable<ApiResponse<Item[]>> {
    let httpParams = new HttpParams().set('q', query || '');
    if (customerId) {
      httpParams = httpParams.set('customer_id', customerId.toString());
    }
    return this.http.get<ApiResponse<Item[]>>(`${API_BASE_URL}/items/search`, {
      params: httpParams
    });
  }
}

@Injectable({ providedIn: 'root' })
export class InvoiceService {
  private http = inject(HttpClient);

  getInvoices(params: any = {}): Observable<ApiResponse<PaginatedResponse<Invoice>>> {
    let httpParams = new HttpParams();
    Object.keys(params).forEach(key => {
      if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
        httpParams = httpParams.set(key, params[key]);
      }
    });
    return this.http.get<ApiResponse<PaginatedResponse<Invoice>>>(`${API_BASE_URL}/invoices`, { params: httpParams });
  }

  getNextNumber(date?: string): Observable<ApiResponse<{ next_invoice_number: string }>> {
    let params = new HttpParams();
    if (date) {
      params = params.set('date', date);
    }
    return this.http.get<ApiResponse<{ next_invoice_number: string }>>(`${API_BASE_URL}/invoices/next-number`, { params });
  }

  getInvoice(id: number): Observable<ApiResponse<Invoice>> {
    return this.http.get<ApiResponse<Invoice>>(`${API_BASE_URL}/invoices/${id}`);
  }

  createInvoice(data: any): Observable<ApiResponse<Invoice>> {
    return this.http.post<ApiResponse<Invoice>>(`${API_BASE_URL}/invoices`, data);
  }

  updateInvoice(id: number, data: any): Observable<ApiResponse<Invoice>> {
    return this.http.put<ApiResponse<Invoice>>(`${API_BASE_URL}/invoices/${id}`, data);
  }

  deleteInvoice(id: number): Observable<ApiResponse<any>> {
    return this.http.delete<ApiResponse<any>>(`${API_BASE_URL}/invoices/${id}`);
  }

  recordPayment(invoiceId: number, data: Partial<Payment>): Observable<ApiResponse<{ payment: Payment; invoice: Invoice }>> {
    return this.http.post<ApiResponse<{ payment: Payment; invoice: Invoice }>>(`${API_BASE_URL}/invoices/${invoiceId}/payments`, data);
  }

  getPdfUrl(invoiceId: number): string {
    return `${API_BASE_URL}/invoices/${invoiceId}/pdf`;
  }

  sendInvoiceEmail(invoiceId: number, data: { email?: string; subject?: string; message?: string } = {}): Observable<ApiResponse<any>> {
    return this.http.post<ApiResponse<any>>(`${API_BASE_URL}/invoices/${invoiceId}/send-email`, data);
  }
}

@Injectable({ providedIn: 'root' })
export class ReportService {
  private http = inject(HttpClient);

  getReport(type: string, params: any = {}): Observable<ApiResponse<any>> {
    let httpParams = new HttpParams();
    Object.keys(params).forEach(key => {
      if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
        httpParams = httpParams.set(key, params[key]);
      }
    });
    return this.http.get<ApiResponse<any>>(`${API_BASE_URL}/reports/${type}`, { params: httpParams });
  }
}

@Injectable({ providedIn: 'root' })
export class SettingService {
  private http = inject(HttpClient);

  getSettings(): Observable<ApiResponse<CompanySetting>> {
    return this.http.get<ApiResponse<CompanySetting>>(`${API_BASE_URL}/settings`);
  }

  updateSettings(data: Partial<CompanySetting>): Observable<ApiResponse<CompanySetting>> {
    return this.http.put<ApiResponse<CompanySetting>>(`${API_BASE_URL}/settings`, data);
  }
}

@Injectable({ providedIn: 'root' })
export class UserService {
  private http = inject(HttpClient);

  getUsers(params: any = {}): Observable<ApiResponse<PaginatedResponse<User>>> {
    let httpParams = new HttpParams();
    Object.keys(params).forEach(key => {
      if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
        httpParams = httpParams.set(key, params[key]);
      }
    });
    return this.http.get<ApiResponse<PaginatedResponse<User>>>(`${API_BASE_URL}/users`, { params: httpParams });
  }

  getUser(id: number): Observable<ApiResponse<User>> {
    return this.http.get<ApiResponse<User>>(`${API_BASE_URL}/users/${id}`);
  }

  createUser(data: any): Observable<ApiResponse<User>> {
    return this.http.post<ApiResponse<User>>(`${API_BASE_URL}/users`, data);
  }

  updateUser(id: number, data: any): Observable<ApiResponse<User>> {
    return this.http.put<ApiResponse<User>>(`${API_BASE_URL}/users/${id}`, data);
  }

  deleteUser(id: number): Observable<ApiResponse<any>> {
    return this.http.delete<ApiResponse<any>>(`${API_BASE_URL}/users/${id}`);
  }
}

@Injectable({ providedIn: 'root' })
export class NotificationService {
  private http = inject(HttpClient);
  private refreshTrigger$ = new BehaviorSubject<void>(undefined);

  public refresh$ = this.refreshTrigger$.asObservable();

  triggerRefresh() {
    this.refreshTrigger$.next();
  }

  getPendingPayments(): Observable<NotificationResponse> {
    return this.http.get<NotificationResponse>(`${API_BASE_URL}/notifications/pending-payments`);
  }

  approvePayment(invoiceId: number): Observable<any> {
    return this.http.post<any>(`${API_BASE_URL}/notifications/approve-payment/${invoiceId}`, {}).pipe(
      tap(() => this.triggerRefresh())
    );
  }
}
