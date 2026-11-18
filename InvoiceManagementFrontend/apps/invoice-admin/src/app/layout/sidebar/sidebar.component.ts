import { Component, EventEmitter, Input, Output, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { AuthService } from '@invoice-mgmt/shared-services';
import { getRoleBadgeClass } from '@invoice-mgmt/shared-utils';

interface MenuItem {
  title: string;
  route: string;
  icon: string;
  permission: string;
}

@Component({
  selector: 'app-sidebar',
  standalone: true,
  imports: [CommonModule, RouterModule],
  template: `
    <aside class="sidebar d-flex flex-column" [class.collapsed]="isCollapsed">
      <!-- Logo Section (Fixed at Top) -->
      <div class="sidebar-header d-flex align-items-center justify-content-between px-3 border-bottom border-light-subtle">
        <div class="d-flex align-items-center gap-2 overflow-hidden logo-brand">
          <div class="logo-icon-box">
            <span>⚡</span>
          </div>
          @if (!isCollapsed) {
            <div class="d-flex flex-column text-nowrap">
              <span class="brand-title">ORANGE</span>
              <span class="brand-sub">Invoice & CRM</span>
            </div>
          }
        </div>
        <button class="btn btn-sm btn-collapse d-none d-lg-block p-1" (click)="toggleCollapse()" [title]="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
          <span>{{ isCollapsed ? '👉' : '👈' }}</span>
        </button>
      </div>

      <!-- Navigation Menu (Dynamically Filtered by Screen Permission) -->
      <div class="sidebar-menu flex-grow-1 py-2">
        <ul class="nav flex-column gap-1 px-2">
          @for (item of menuItems; track item.route) {
            <li class="nav-item">
              <a
                [routerLink]="item.route"
                routerLinkActive="active"
                [routerLinkActiveOptions]="{ exact: item.route === '/invoices' || item.route === '/dashboard' || item.route === '/users' }"
                class="nav-link d-flex align-items-center gap-3 px-3 py-2 rounded-3 text-secondary"
                [title]="item.title"
              >
                <span class="nav-icon fs-5">{{ item.icon }}</span>
                @if (!isCollapsed) {
                  <span class="nav-label fw-semibold text-nowrap">{{ item.title }}</span>
                }
              </a>
            </li>
          }
        </ul>
      </div>

      <!-- User Profile & Assigned Role (Fixed at Bottom) -->
      <div class="sidebar-footer p-3 border-top border-light-subtle">
        <div class="d-flex align-items-center gap-2 overflow-hidden">
          <div class="avatar-circle">
            {{ userInitial }}
          </div>
          @if (!isCollapsed) {
            <div class="d-flex flex-column text-truncate me-auto">
              <span class="fw-bold small text-dark text-truncate">{{ userName }}</span>
              <span class="badge text-capitalize mt-1 px-2 py-0" [ngClass]="userRoleBadgeClass" style="font-size: 10px; width: fit-content;">
                {{ userRole }}
              </span>
            </div>
            <button class="btn btn-sm btn-outline-danger border-0 p-1" (click)="logout()" title="Logout">
              🚪
            </button>
          }
        </div>
      </div>
    </aside>
  `,
  styles: [`
    .sidebar {
      width: 250px;
      height: 100vh;
      max-height: 100vh;
      background: #FFFFFF;
      border-right: 1px solid #EAEAEA;
      transition: width 0.25s ease;
      z-index: 1000;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      box-sizing: border-box;
    }
    .sidebar.collapsed {
      width: 72px;
    }
    .sidebar-header {
      height: 64px;
      flex-shrink: 0;
      background: #FFFFFF;
      box-sizing: border-box;
    }
    .sidebar-menu {
      flex: 1 1 auto;
      min-height: 0;
      overflow-y: auto;
      overflow-x: hidden;
      scrollbar-width: thin;
      scrollbar-color: #E2E2E2 transparent;
    }
    .sidebar-menu::-webkit-scrollbar {
      width: 4px;
    }
    .sidebar-menu::-webkit-scrollbar-track {
      background: transparent;
    }
    .sidebar-menu::-webkit-scrollbar-thumb {
      background: #E2E2E2;
      border-radius: 4px;
    }
    .sidebar-menu::-webkit-scrollbar-thumb:hover {
      background: #FFAB00;
    }
    .sidebar-footer {
      flex-shrink: 0;
      background: #FFFFFF;
      box-sizing: border-box;
    }
    .logo-icon-box {
      width: 38px;
      height: 38px;
      background: linear-gradient(135deg, #FFAB00, #FF6200);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      color: #fff;
      flex-shrink: 0;
    }
    .brand-title {
      font-weight: 800;
      font-size: 17px;
      letter-spacing: 0.5px;
      color: #FF6200;
      line-height: 1.1;
    }
    .brand-sub {
      font-size: 10.5px;
      color: #777;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .btn-collapse {
      background: #F5F5F5;
      border-radius: 6px;
      font-size: 12px;
    }
    .nav-link {
      transition: all 0.2s ease;
      color: #555555;
    }
    .nav-link:hover {
      background-color: #FFF8E1;
      color: #FF6200;
    }
    .nav-link.active {
      background: linear-gradient(90deg, #FFF8E1 0%, #FFFFFF 100%);
      color: #FF6200 !important;
      font-weight: 700;
      border-left: 4px solid #FFAB00;
      border-radius: 0 8px 8px 0;
      box-shadow: 0 2px 6px rgba(255, 171, 0, 0.12);
    }
    .avatar-circle {
      width: 34px;
      height: 34px;
      background: #FFAB00;
      color: #242424;
      font-weight: 700;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
  `]
})
export class SidebarComponent {
  @Input() isCollapsed = false;
  @Output() toggle = new EventEmitter<void>();

  private authService = inject(AuthService);
  private router = inject(Router);

  allMenuItems: MenuItem[] = [
    { title: 'Dashboard', route: '/dashboard', icon: '📊', permission: 'dashboard' },
    { title: 'Customers', route: '/customers', icon: '👥', permission: 'customers' },
    { title: 'Items', route: '/items', icon: '📦', permission: 'items' },
    { title: 'Invoice Generate', route: '/invoices/generate', icon: '📄', permission: 'invoice_generate' },
    { title: 'Invoices List', route: '/invoices', icon: '📑', permission: 'invoices' },
    { title: 'Reports', route: '/reports', icon: '📈', permission: 'reports' },
    { title: 'Settings', route: '/settings', icon: '⚙️', permission: 'settings' },
    { title: 'Users', route: '/users', icon: '👤', permission: 'users' },
  ];

  get menuItems(): MenuItem[] {
    return this.allMenuItems.filter(item => this.authService.hasPermission(item.permission));
  }

  get userName(): string {
    const user = this.authService.getUser();
    return user ? user.name : 'Administrator';
  }

  get userRole(): string {
    const user = this.authService.getUser();
    return user?.role || 'admin';
  }

  get userRoleBadgeClass(): string {
    return getRoleBadgeClass(this.userRole);
  }

  get userInitial(): string {
    return this.userName.charAt(0).toUpperCase();
  }

  toggleCollapse() {
    this.toggle.emit();
  }

  logout() {
    this.authService.logout();
    this.router.navigate(['/login']);
  }
}
