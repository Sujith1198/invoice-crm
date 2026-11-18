# Orange Invoice & Customer Management System – Backend (Laravel + MySQL)

A commercial-grade RESTful API backend for the Orange Invoicing & Customer Management System, built with **Laravel 12**, **MySQL / SQLite**, **Laravel Sanctum**, **Eloquent ORM**, and **Dompdf**.

---

## Architecture & Structure

```
InvoiceManagementBackend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php          # Login, logout, user profile
│   │   │   ├── DashboardController.php     # 4 Stat cards, verified collections, charts
│   │   │   ├── CustomerController.php      # Full CRUD, duplicate warning, search
│   │   │   ├── ItemController.php          # Declarations, permits, GST catalog
│   │   │   ├── InvoiceController.php       # Invoices, PDF stream, payments
│   │   │   ├── ReportController.php        # 11 Report types with summaries
│   │   │   └── SettingController.php       # Company profile & terms
│   │   └── Requests/
│   │       ├── StoreCustomerRequest.php
│   │       ├── UpdateCustomerRequest.php
│   │       ├── StoreItemRequest.php
│   │       ├── UpdateItemRequest.php
│   │       ├── StoreInvoiceRequest.php
│   │       ├── UpdateInvoiceRequest.php
│   │       └── RecordPaymentRequest.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Customer.php
│   │   ├── CustomerPhone.php
│   │   ├── Item.php
│   │   ├── Invoice.php
│   │   ├── InvoiceItem.php
│   │   ├── Payment.php
│   │   ├── CompanySetting.php
│   │   └── InvoiceSequence.php
│   └── Services/
│       └── InvoiceService.php              # Math, sequence lock, PDF generator
├── database/
│   ├── migrations/                         # 5 Migration files (customers, items, settings, invoices, tokens)
│   └── seeders/
│       └── DatabaseSeeder.php              # Admin, customers, phones, items, invoices, payments
├── resources/
│   └── views/
│       └── invoices/
│           └── pdf.blade.php               # Orange-themed professional PDF template
├── routes/
│   └── api.php                             # REST endpoints
├── tests/
│   ├── run_tests.php                       # Automated HTTP test suite (100% pass)
│   └── Feature/
├── .env.example
├── composer.json
└── README.md
```

---

## Prerequisites

- **PHP**: 8.2 or 8.3+ (Extensions: `curl`, `mbstring`, `openssl`, `pdo_mysql`, `pdo_sqlite`, `fileinfo`, `gd`, `intl`, `zip`)
- **Composer**: v2.x
- **MySQL Server**: 8.x or MariaDB 10.x (or portable SQLite)

---

## Windows 11 PowerShell Setup Commands

### 1. Navigate to Backend Directory
```powershell
Set-Location -Path "D:\invoice-crm\invoice-crm\InvoiceManagementBackend"
```

### 2. Configure Environment (`.env`)
Copy the example configuration:
```powershell
Copy-Item .env.example .env
```

To use **MySQL**, edit `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=invoice_management
DB_USERNAME=root
DB_PASSWORD=your_password
```

Or for instant zero-configuration testing with **SQLite**:
```env
DB_CONNECTION=sqlite
DB_DATABASE=D:/invoice-crm/invoice-crm/InvoiceManagementBackend/database/database.sqlite
```

### 3. Generate Application Key
```powershell
php artisan key:generate
```

### 4. Run Migrations & Comprehensive Seeders
```powershell
php artisan migrate:fresh --seed
```

### 5. Launch Backend Development Server
```powershell
php artisan serve --host=127.0.0.1 --port=8000
```
Backend API will be active at:
👉 **`http://127.0.0.1:8000/api`**

### 6. Run Automated Backend Test Suite
```powershell
php tests/run_tests.php
```

---

## Default Administrator Credentials

- **Email**: `admin@orangeinvoice.com`
- **Password**: `password123`

---

## Core API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/login` | Authenticate and obtain Sanctum Bearer token |
| `POST` | `/api/logout` | Revoke active Sanctum token |
| `GET` | `/api/user` | Get current authenticated user |
| `GET` | `/api/dashboard` | 4 Stat cards, verified collections, charts, recent ledgers |
| `GET` | `/api/customers` | Paginated customer list with city/payment method filters |
| `POST` | `/api/customers` | Register customer with multiple phone numbers |
| `GET` | `/api/customers/{id}` | Customer profile with financial metrics & invoice history |
| `PUT` | `/api/customers/{id}` | Update customer & synchronize phones |
| `DELETE`| `/api/customers/{id}` | Soft delete customer |
| `GET` | `/api/customers/search?q=` | Autocomplete customer search |
| `POST` | `/api/customers/check-duplicate` | Duplicate customer warning detector |
| `GET` | `/api/items` | Item catalog with declarations, permits & GST rates |
| `POST` | `/api/items` | Create new item |
| `GET` | `/api/items/search?q=` | Autocomplete item search |
| `GET` | `/api/invoices` | Paginated invoices with status & date range filters |
| `GET` | `/api/invoices/next-number` | Auto-generate collision-proof sequence number |
| `POST` | `/api/invoices` | Generate invoice with server-side validation & math |
| `GET` | `/api/invoices/{id}` | Invoice details with line items and payment transactions |
| `PUT` | `/api/invoices/{id}` | Update invoice and recalculate totals |
| `GET` | `/api/invoices/{id}/pdf` | Stream / Download high-resolution Dompdf invoice |
| `POST` | `/api/invoices/{id}/payments` | Record payment transaction (updates balance & status) |
| `GET` | `/api/reports/daily` | Daily invoice report |
| `GET` | `/api/reports/weekly` | Weekly invoice report |
| `GET` | `/api/reports/monthly` | Monthly invoice report |
| `GET` | `/api/reports/yearly` | Yearly invoice report |
| `GET` | `/api/reports/revenue` | Actual verified revenue collections report |
| `GET` | `/api/reports/customers`| Customer ledger statements |
| `GET` | `/api/reports/items` | Item performance report |
| `GET` | `/api/reports/outstanding` | Outstanding debt report |
| `GET` | `/api/reports/paid` | Settled invoices report |
| `GET` | `/api/reports/unpaid` | Unpaid invoices report |
| `GET` | `/api/reports/gst` | GST taxation summary report |
| `GET` | `/api/settings` | Get company identity, GSTIN, and currency |
| `PUT` | `/api/settings` | Update company settings |
