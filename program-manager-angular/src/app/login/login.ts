import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { Router } from '@angular/router';
import { finalize } from 'rxjs';

import { AuthService } from '../auth.service';

@Component({
  selector: 'app-login',
  imports: [FormsModule],
  templateUrl: './login.html',
  styleUrl: './login.css'
})
export class Login {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  username = '';
  password = '';

  readonly loading = signal(false);
  readonly errorMessage = signal('');

  onSubmit(): void {
    if (this.loading()) {
      return;
    }

    this.errorMessage.set('');

    const username = this.username.trim();

    if (username === '' || this.password === '') {
      this.errorMessage.set('Please enter your username and password.');
      return;
    }

    this.loading.set(true);

    this.auth.login(username, this.password)
      .pipe(
        finalize(() => this.loading.set(false))
      )
      .subscribe({
        next: () => {
          this.password = '';
          void this.router.navigate(['/']);
        },
        error: (error: HttpErrorResponse) => {
          this.password = '';

          if (error.status === 401) {
            this.errorMessage.set('Invalid username or password.');
          } else if (error.status === 0) {
            this.errorMessage.set(
              'Cannot reach the API. Check XAMPP and your connection.'
            );
          } else {
            this.errorMessage.set(
              'Login could not be completed. Please try again.'
            );
          }
        }
      });
  }
}