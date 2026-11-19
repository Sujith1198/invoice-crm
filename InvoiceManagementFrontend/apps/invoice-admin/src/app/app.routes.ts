import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';
import { MainLayoutComponent } from './layout/main-layout/main-layout.component';
import { LoginComponent } from './features/auth/login.component';

export const routes: Routes = [
  {
    path: 'login',
    component: LoginComponent,
  },
  {
    path: '',
    component: MainLayoutComponent,
    canActivate: [authGuard],
    children: [
      {
        path: '',
        redirectTo: 'dashboard',
        pathMatch: 'full',
      },
      {
        path: 'dashboard',
        data: { screen: 'dashboard' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/dashboard/dashboard.component').then(m => m.DashboardComponent),
      },
      // Customers
      {
        path: 'customers',
        data: { screen: 'customers' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/customers/customer-list.component').then(m => m.CustomerListComponent),
      },
      {
        path: 'customers/create',
        data: { screen: 'customers' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/customers/customer-form.component').then(m => m.CustomerFormComponent),
      },
      {
        path: 'customers/edit/:id',
        data: { screen: 'customers' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/customers/customer-form.component').then(m => m.CustomerFormComponent),
      },
      {
        path: 'customers/:id',
        data: { screen: 'customers' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/customers/customer-detail.component').then(m => m.CustomerDetailComponent),
      },
      // Items
      {
        path: 'items',
        data: { screen: 'items' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/items/item-list.component').then(m => m.ItemListComponent),
      },
      {
        path: 'items/create',
        data: { screen: 'items' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/items/item-form.component').then(m => m.ItemFormComponent),
      },
      {
        path: 'items/edit/:id',
        data: { screen: 'items' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/items/item-form.component').then(m => m.ItemFormComponent),
      },
      // Upload Document
      {
        path: 'upload-document',
        data: { screen: 'upload_document' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/upload-document/upload-document.component').then(m => m.UploadDocumentComponent),
      },
      // Invoices
      {
        path: 'invoices',
        data: { screen: 'invoices' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/invoices/invoice-list.component').then(m => m.InvoiceListComponent),
      },
      {
        path: 'invoices/generate',
        data: { screen: 'invoice_generate' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/invoices/invoice-generate.component').then(m => m.InvoiceGenerateComponent),
      },
      {
        path: 'invoices/edit/:id',
        data: { screen: 'invoice_generate' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/invoices/invoice-generate.component').then(m => m.InvoiceGenerateComponent),
      },
      {
        path: 'invoices/:id',
        data: { screen: 'invoices' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/invoices/invoice-detail.component').then(m => m.InvoiceDetailComponent),
      },
      // Reports
      {
        path: 'reports',
        data: { screen: 'reports' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/reports/reports.component').then(m => m.ReportsComponent),
      },
      // Settings
      {
        path: 'settings',
        data: { screen: 'settings' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/settings/settings.component').then(m => m.SettingsComponent),
      },
      // Users & Roles
      {
        path: 'users',
        data: { screen: 'users' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/users/user-list.component').then(m => m.UserListComponent),
      },
      {
        path: 'users/create',
        data: { screen: 'users' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/users/user-form.component').then(m => m.UserFormComponent),
      },
      {
        path: 'users/edit/:id',
        data: { screen: 'users' },
        canActivate: [authGuard],
        loadComponent: () => import('./features/users/user-form.component').then(m => m.UserFormComponent),
      },
    ],
  },
  {
    path: '**',
    redirectTo: 'dashboard',
  },
];
