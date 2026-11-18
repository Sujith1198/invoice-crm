# Orange Invoice & Customer Management System

A complete, production-ready, professional commercial SaaS Invoice and Customer Management System built as two decoupled projects:

1. **`InvoiceManagementFrontend/`**: Modern Angular application built with Nx Workspace, TypeScript, SCSS, Reactive Forms, and the Orange UI Design System (`#FFAB00`, `#FF9D00`, `#F77F23`, `#FF6200`).
2. **`InvoiceManagementBackend/`**: High-performance REST API built with Laravel 12, MySQL / SQLite, Sanctum Authentication, Form Requests, Dompdf, and Eloquent ORM.

---

## Workspace Architecture

```
d:\invoice-crm\invoice-crm\
├── InvoiceManagementFrontend/       # Project A: Angular + Nx Monorepo
│   ├── apps/
│   │   └── invoice-admin/           # Standalone Angular Admin Application
│   ├── libs/
│   │   ├── shared-models/           # TypeScript Data Models & DTOs
│   │   ├── shared-services/         # Angular HttpClient Services & Toasts
│   │   ├── shared-ui/               # Reusable Components (Toasts, Modals, Stats, Pagination)
│   │   └── shared-utils/            # Formatting, Formulas & CSV Export
│   ├── angular.json
│   ├── nx.json
│   └── README.md
│
├── InvoiceManagementBackend/        # Project B: Laravel REST API
│   ├── app/
│   │   ├── Http/Controllers/        # Auth, Dashboard, Customers, Items, Invoices, Reports, Settings
│   │   ├── Http/Requests/           # Server-side Form Request Validation
│   │   ├── Models/                  # Eloquent Entities with Relationships
│   │   └── Services/                # Financial Math, Lock Sequences, PDF Generation
│   ├── database/migrations/         # 5 Normalized Migrations
│   ├── database/seeders/            # Comprehensive Production-Grade Seed Data
│   ├── resources/views/invoices/    # Dompdf Orange Invoice Blade Template
│   ├── routes/api.php               # Fully Defined REST Endpoints
│   ├── tests/run_tests.php          # Automated Backend Test Suite (100% Pass)
│   └── README.md
│
├── composer.phar                    # Portable Composer Binary
├── php.ini                          # PHP Configuration (curl, mbstring, gd, pdo_mysql, zip, etc.)
├── php.bat                          # Quick execution helper
└── composer.bat                     # Quick execution helper
```

---

## Quickstart (Windows 11 PowerShell)

### Step 1: Start Laravel Backend
```powershell
Set-Location -Path "D:\invoice-crm\invoice-crm\InvoiceManagementBackend"
d:\invoice-crm\invoice-crm\php.bat artisan serve --host=127.0.0.1 --port=8000
```
API active at: 👉 **`http://127.0.0.1:8000/api`**

### Step 2: Start Angular Frontend
Open a new PowerShell terminal:
```powershell
Set-Location -Path "D:\invoice-crm\invoice-crm\InvoiceManagementFrontend"
npm start
```
Frontend active at: 👉 **`http://localhost:4200`**

### Default Administrator Credentials
- **Email**: `admin@orangeinvoice.com`
- **Password**: `password123`

---

## Verifying Test Suites

### Backend Feature & API Tests:
```powershell
Set-Location -Path "D:\invoice-crm\invoice-crm"
.\php.bat InvoiceManagementBackend\tests\run_tests.php
```

### Frontend Calculations & Formatting Tests:
```powershell
Set-Location -Path "D:\invoice-crm\invoice-crm\InvoiceManagementFrontend"
npm test
```
