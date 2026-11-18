# Orange Invoice & Customer Management System – Frontend (Angular + Nx)

A commercial-grade SaaS frontend for Invoice and Customer Management built with **Angular (standalone components)**, **Nx Workspace**, **TypeScript**, and **SCSS** adhering to the Orange Design System palette (`#FFAB00`, `#FF9D00`, `#F77F23`, `#FF6200`).

---

## Architecture & Structure

```
InvoiceManagementFrontend/
├── apps/
│   └── invoice-admin/
│       └── src/
│           ├── app/
│           │   ├── core/
│           │   │   ├── guards/          # AuthGuard
│           │   │   └── interceptors/    # Bearer Token AuthInterceptor
│           │   ├── layout/
│           │   │   ├── sidebar/         # Collapsible orange active sidebar
│           │   │   ├── topbar/          # Header with breadcrumbs & user profile
│           │   │   └── main-layout/     # Responsive shell layout
│           │   └── features/
│           │       ├── auth/            # Login with Sanctum session
│           │       ├── dashboard/       # 4 Stat cards, SVG charts, recent ledgers
│           │       ├── customers/       # List, multiple phones form, details
│           │       ├── items/           # Declarations, permits, GST catalog
│           │       ├── invoices/        # Autocomplete, dynamic table, math, PDF
│           │       ├── reports/         # 11 Report types, date filters, CSV/print
│           │       └── settings/        # Company profile, GSTIN, currency
│           ├── environments/            # Configurable API URL
│           ├── index.html
│           ├── main.ts
│           └── styles.scss              # Orange design system styling
├── libs/
│   ├── shared-models/                   # TypeScript interfaces
│   ├── shared-services/                 # REST API services & Toast notifications
│   ├── shared-ui/                       # Reusable UI components (modals, toasts, stats)
│   └── shared-utils/                    # Math, formatting, and CSV exports
├── angular.json
├── nx.json
├── package.json
├── tsconfig.base.json
└── README.md
```

---

## Prerequisites

- **Node.js**: v18.x, v20.x, or v22+
- **npm**: v9+ or v10+
- **Nx Console**: Recommended VS Code / Cursor extension for visual Angular schematics

---

## Windows 11 PowerShell Commands

### 1. Navigate to Project
```powershell
Set-Location -Path "D:\invoice-crm\invoice-crm\InvoiceManagementFrontend"
```

### 2. Install Dependencies
```powershell
npm install
```

### 3. Run Development Server
```powershell
npm start
# or: npx nx serve invoice-admin
```
The application will launch and be available at:
👉 **`http://localhost:4200`**

### 4. Build Production Bundle
```powershell
npm run build
# or: npx nx build invoice-admin --configuration production
```
Artifacts are emitted to `dist/apps/invoice-admin/`.

### 5. Run Unit Tests
```powershell
npm test
```

---

## Key Features

1. **Orange Design System**:
   - Primary: `#FFAB00`
   - Secondary: `#FF9D00`
   - Accent: `#F77F23`
   - Deep Orange: `#FF6200`
   - Background: `#F5F5F5`, Card: `#FFFFFF`, Borders: `#EAEAEA`
2. **Dynamic Calculations**:
   - $\text{Line Total} = \text{Price} \times \text{Qty}$
   - $\text{GST Amount} = \frac{\text{Line Total} \times \text{GST Percentage}}{100}$
   - $\text{Grand Total} = \text{Subtotal} + \text{Total GST} - \text{Discount} + \text{Additional Charges}$
   - $\text{Balance Due} = \text{Grand Total} - \text{Amount Paid}$
3. **Customer Autocomplete**: Autofills separate editable inputs (name, company, address, phone, email, payment method) without modifying the customer's master directory record.
4. **Multiple Phones**: Full support for adding, labeling, selecting primary, and removing phone numbers.
5. **Live PDF & Print**: Integrated PDF generation and responsive printable invoice layouts.
6. **11 Reports**: Real database reporting with CSV export and print functionality.
