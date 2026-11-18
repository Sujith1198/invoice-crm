import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { NavigationEnd, Router, RouterModule } from '@angular/router';
import { filter } from 'rxjs';
import { SidebarComponent } from '../sidebar/sidebar.component';
import { TopbarComponent } from '../topbar/topbar.component';
import { ToastContainerComponent } from '@invoice-mgmt/shared-ui';

@Component({
  selector: 'app-main-layout',
  standalone: true,
  imports: [CommonModule, RouterModule, SidebarComponent, TopbarComponent, ToastContainerComponent],
  template: `
    <div class="d-flex min-vh-100 layout-wrapper">
      <!-- Mobile Backdrop -->
      @if (isMobileSidebarOpen) {
        <div class="sidebar-backdrop d-lg-none" (click)="isMobileSidebarOpen = false"></div>
      }

      <!-- Sidebar (Static viewport, self-contained scroll) -->
      <app-sidebar
        [isCollapsed]="isSidebarCollapsed"
        (toggle)="isSidebarCollapsed = !isSidebarCollapsed"
        [class.mobile-open]="isMobileSidebarOpen"
      ></app-sidebar>

      <!-- Main Content Area -->
      <div class="d-flex flex-column flex-grow-1 main-content-wrapper overflow-x-hidden">
        <app-topbar (toggleMobileSidebar)="isMobileSidebarOpen = !isMobileSidebarOpen"></app-topbar>
        <main class="flex-grow-1 p-3 p-md-4">
          <router-outlet></router-outlet>
        </main>
      </div>

      <ui-toast-container></ui-toast-container>
    </div>
  `,
  styles: [`
    .layout-wrapper {
      background-color: #F5F5F5;
    }
    app-sidebar {
      position: sticky;
      top: 0;
      height: 100vh;
      flex-shrink: 0;
      display: block;
      z-index: 1000;
    }
    .main-content-wrapper {
      min-width: 0;
      transition: margin 0.25s ease;
    }
    @media (max-width: 991.98px) {
      app-sidebar {
        position: fixed;
        left: -260px;
        top: 0;
        bottom: 0;
        height: 100vh;
        transition: left 0.3s ease;
        z-index: 1050;
      }
      app-sidebar.mobile-open {
        left: 0;
        box-shadow: 0 0 50px rgba(0,0,0,0.3);
      }
      .sidebar-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.45);
        z-index: 1040;
      }
    }
  `]
})
export class MainLayoutComponent implements OnInit {
  isSidebarCollapsed = false;
  isMobileSidebarOpen = false;
  private router = inject(Router);

  ngOnInit() {
    this.router.events.pipe(filter(e => e instanceof NavigationEnd)).subscribe(() => {
      this.isMobileSidebarOpen = false;
    });
  }
}

