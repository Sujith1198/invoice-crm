import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService, ToastService } from '@invoice-mgmt/shared-services';

export const authGuard: CanActivateFn = (route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);
  const toastService = inject(ToastService);

  if (!authService.isAuthenticated()) {
    router.navigate(['/login'], { queryParams: { returnUrl: state.url } });
    return false;
  }

  // Check screen permission if defined
  const requiredScreen = route.data?.['screen'] as string | undefined;
  if (requiredScreen) {
    if (!authService.hasPermission(requiredScreen)) {
      toastService.error(`Access Denied: You do not have permission to access the '${requiredScreen}' screen.`, 'Permission Denied');
      
      // Find first accessible screen
      const user = authService.getUser();
      const allowed = user?.permissions || [];
      const fallback = allowed.length > 0 ? (allowed[0] === 'invoice_generate' ? '/invoices/generate' : `/${allowed[0]}`) : '/login';
      
      router.navigateByUrl(fallback);
      return false;
    }
  }

  return true;
};
