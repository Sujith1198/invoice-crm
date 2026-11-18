export type UserRole = 'admin' | 'manager' | 'team lead' | 'user';

export interface User {
  id: number;
  name: string;
  email: string;
  role?: UserRole;
  permissions?: string[];
  status?: 'active' | 'inactive';
  created_at?: string;
  updated_at?: string;
}

export interface AuthResponse {
  status: string;
  message: string;
  token: string;
  user: User;
}

export interface CustomerPhone {
  id?: number;
  customer_id?: number;
  phone_number: string;
  is_primary: boolean;
  type: string;
}

export type PaymentMethod = 'Daily Pay' | 'Monthly Pay' | '15 Days Pay';

export interface Customer {
  id: number;
  first_name: string;
  last_name: string;
  full_name?: string;
  company_name?: string;
  email: string;
  address_line1: string;
  address_line2?: string;
  city: string;
  country: string;
  payment_method: PaymentMethod;
  status: 'active' | 'inactive';
  notes?: string;
  primary_phone?: string;
  phones: CustomerPhone[];
  invoices?: Invoice[];
  created_at?: string;
  updated_at?: string;
}

export interface CustomerFormData {
  first_name: string;
  last_name: string;
  company_name?: string;
  email: string;
  address_line1: string;
  address_line2?: string;
  city: string;
  country: string;
  payment_method: PaymentMethod;
  status: 'active' | 'inactive';
  notes?: string;
  phones: Array<{
    phone_number: string;
    is_primary: boolean;
    type: string;
  }>;
}

export interface Item {
  id: number;
  customer_id?: number | null;
  customer?: Customer | null;
  item_name: string;
  import_declaration?: string;
  export_declaration?: string;
  item_cost: number;
  gst_percentage: number;
  certificate_of_origin?: string;
  xo_permit?: string;
  amendment?: string;
  status: 'active' | 'inactive';
  created_at?: string;
  updated_at?: string;
}

export type InvoiceStatus = 'Draft' | 'Generated' | 'Partially Paid' | 'Paid' | 'Overdue' | 'Cancelled';

export interface InvoiceItem {
  id?: number;
  invoice_id?: number;
  item_id?: number | null;
  item_name: string;
  description?: string;
  price: number;
  quantity: number;
  gst_percentage: number;
  gst_amount?: number;
  line_total: number;
  sort_order?: number;
}

export interface Payment {
  id: number;
  invoice_id: number;
  payment_number: string;
  payment_date: string;
  amount: number;
  payment_method: string;
  reference_number?: string;
  notes?: string;
  created_at?: string;
}

export interface Invoice {
  id: number;
  invoice_number: string;
  invoice_date: string;
  due_date: string;
  customer_id?: number | null;
  customer_name: string;
  company_name?: string;
  customer_address?: string;
  customer_phone?: string;
  customer_email?: string;
  payment_method?: string;
  subtotal: number;
  total_gst: number;
  discount: number;
  additional_charges: number;
  grand_total: number;
  amount_paid: number;
  balance_due: number;
  payment_terms?: string;
  notes?: string;
  terms_and_conditions?: string;
  status: InvoiceStatus;
  items: InvoiceItem[];
  payments: Payment[];
  customer?: Customer;
  created_at?: string;
  updated_at?: string;
}

export interface CompanySetting {
  id?: number;
  company_name: string;
  company_logo?: string;
  company_address?: string;
  phone_number?: string;
  email?: string;
  gstin?: string;
  invoice_prefix: string;
  default_currency: string;
  default_currency_symbol: string;
  default_payment_terms?: string;
  invoice_footer?: string;
  terms_and_conditions?: string;
}

export interface DashboardStatistics {
  total_customers: {
    value: number;
    growth_pct: number;
    comparison_text: string;
  };
  today_invoices: {
    value: number;
    diff: number;
    comparison_text: string;
  };
  total_revenue: {
    value: number;
    formatted: string;
    comparison_text: string;
  };
  monthly_revenue: {
    value: number;
    formatted: string;
    growth_pct: number;
    comparison_text: string;
  };
}

export interface DashboardData {
  statistics: DashboardStatistics;
  charts: {
    daily_invoices: Array<{ date: string; label: string; count: number }>;
    monthly_revenue: Array<{ month: string; revenue: number }>;
    status_distribution: Record<string, number>;
  };
  recent_invoices: Invoice[];
  recent_customers: Customer[];
}

export interface PaginatedResponse<T> {
  current_page: number;
  data: T[];
  first_page_url: string;
  from: number;
  last_page: number;
  last_page_url: string;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number;
  total: number;
}

export interface ApiResponse<T> {
  status: string;
  message?: string;
  data: T;
  summary?: any;
}

export interface PendingNotification {
  id: number;
  invoice_id: number;
  invoice_number: string;
  customer_id?: number;
  customer_name: string;
  company_name?: string;
  customer_phone?: string;
  customer_email?: string;
  invoice_date: string;
  due_date: string;
  grand_total: number;
  amount_paid: number;
  balance_due: number;
  payment_method: string;
  status: string;
  is_overdue: boolean;
  days_diff: number;
}

export interface NotificationResponse {
  status: string;
  counts: {
    total: number;
    daily: number;
    fifteen_days: number;
    monthly: number;
  };
  daily: PendingNotification[];
  fifteen_days: PendingNotification[];
  monthly: PendingNotification[];
  all: PendingNotification[];
}


