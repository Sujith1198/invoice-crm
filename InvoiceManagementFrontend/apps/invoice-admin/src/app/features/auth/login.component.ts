import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService, ToastService } from '@invoice-mgmt/shared-services';
import { ToastContainerComponent } from '@invoice-mgmt/shared-ui';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule, ReactiveFormsModule, ToastContainerComponent],
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.scss']
})
export class LoginComponent {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private router = inject(Router);
  private toastService = inject(ToastService);

  loginForm: FormGroup = this.fb.group({
    email: ['admin@orangeinvoice.com', [Validators.required, Validators.email]],
    password: ['password123', [Validators.required]],
  });

  loading = false;
  submitted = false;
  showPassword = false;

  // View state: 'login' | 'reset'
  viewMode: 'login' | 'reset' = 'login';
  resetStep: 1 | 2 | 3 = 1;

  // Reset form states
  resetEmail = 'admin@orangeinvoice.com';
  resetOtp = '';
  newPassword = '';
  confirmPassword = '';
  showNewPassword = false;
  showConfirmPassword = false;

  isSendingOtp = false;
  isResettingPassword = false;
  otpDeliveredNotice = '';
  resendCountdown = 0;
  private resendInterval: any = null;

  get f() {
    return this.loginForm.controls;
  }

  togglePasswordVisibility() {
    this.showPassword = !this.showPassword;
  }

  toggleNewPasswordVisibility() {
    this.showNewPassword = !this.showNewPassword;
  }

  toggleConfirmPasswordVisibility() {
    this.showConfirmPassword = !this.showConfirmPassword;
  }

  fillDemoCredentials() {
    this.loginForm.patchValue({
      email: 'admin@orangeinvoice.com',
      password: 'password123',
    });
    this.toastService.info('Demo credentials auto-filled.');
  }

  openResetFlow() {
    this.viewMode = 'reset';
    this.resetStep = 1;
    this.resetOtp = '';
    this.newPassword = '';
    this.confirmPassword = '';
    const currentEmail = this.loginForm.get('email')?.value;
    if (currentEmail) {
      this.resetEmail = currentEmail;
    }
  }

  backToLogin() {
    this.viewMode = 'login';
    this.resetStep = 1;
    this.clearInterval();
  }

  onSendOtp() {
    if (!this.resetEmail || !this.resetEmail.includes('@')) {
      this.toastService.error('Please enter a valid email address.');
      return;
    }

    this.isSendingOtp = true;
    this.authService.sendPasswordOtp(this.resetEmail.trim()).subscribe({
      next: (res) => {
        this.isSendingOtp = false;
        this.resetStep = 2;
        this.otpDeliveredNotice = res.message || `6-digit OTP sent to ${this.resetEmail}`;
        this.toastService.success(this.otpDeliveredNotice);
        this.startResendTimer();
      },
      error: (err) => {
        this.isSendingOtp = false;
        const msg = err.error?.message || 'Failed to send OTP email. Please try again.';
        this.toastService.error(msg);
      }
    });
  }

  startResendTimer() {
    this.resendCountdown = 30;
    this.clearInterval();
    this.resendInterval = setInterval(() => {
      if (this.resendCountdown > 0) {
        this.resendCountdown--;
      } else {
        this.clearInterval();
      }
    }, 1000);
  }

  private clearInterval() {
    if (this.resendInterval) {
      clearInterval(this.resendInterval);
      this.resendInterval = null;
    }
  }

  onResetPassword() {
    const cleanOtp = this.resetOtp.trim();
    if (!cleanOtp || cleanOtp.length !== 6) {
      this.toastService.error('Please enter the 6-digit OTP code sent to your email.');
      return;
    }

    if (!this.newPassword || this.newPassword.length < 6) {
      this.toastService.error('New password must be at least 6 characters long.');
      return;
    }

    if (this.newPassword !== this.confirmPassword) {
      this.toastService.error('Passwords do not match. Please re-enter.');
      return;
    }

    this.isResettingPassword = true;
    this.authService.resetPasswordWithOtp({
      email: this.resetEmail.trim(),
      otp: cleanOtp,
      password: this.newPassword,
    }).subscribe({
      next: (res) => {
        this.isResettingPassword = false;
        this.resetStep = 3;
        this.toastService.success('Password updated successfully in database!');
        // Update login form with new credentials
        this.loginForm.patchValue({
          email: this.resetEmail.trim(),
          password: this.newPassword,
        });
      },
      error: (err) => {
        this.isResettingPassword = false;
        const msg = err.error?.message || 'Failed to reset password. Please check the OTP.';
        this.toastService.error(msg);
      }
    });
  }

  finishResetAndLogin() {
    this.viewMode = 'login';
    this.resetStep = 1;
    this.toastService.info('Please click Sign In to enter with your new password.');
  }

  onSubmit() {
    this.submitted = true;
    if (this.loginForm.invalid) return;

    this.loading = true;
    this.authService.login(this.loginForm.value).subscribe({
      next: (res) => {
        this.loading = false;
        this.toastService.success(`Welcome back, ${res.user.name}!`);
        this.router.navigate(['/dashboard']);
      },
      error: (err) => {
        this.loading = false;
        const msg = err.error?.message || 'Login failed. Please check your credentials.';
        this.toastService.error(msg);
      }
    });
  }
}
