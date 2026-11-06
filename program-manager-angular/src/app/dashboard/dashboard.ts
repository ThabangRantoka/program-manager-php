import { Component, signal } from '@angular/core';
import { Router } from '@angular/router';
import { finalize } from 'rxjs';

import { AuthService } from '../auth.service';
import { ProgramList } from '../program-list/program-list';
import { AddProgram } from '../add-program/add-program';

@Component({
  selector: 'app-dashboard',
  imports: [ProgramList, AddProgram],
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.css'
})
export class Dashboard {
  readonly loggingOut = signal(false);
  readonly logoutError = signal('');

  constructor(
    public auth: AuthService,
    private router: Router
  ) {}

  logout(): void {
    if (this.loggingOut()) {
      return;
    }

    this.loggingOut.set(true);
    this.logoutError.set('');

    this.auth.logout()
      .pipe(
        finalize(() => this.loggingOut.set(false))
      )
      .subscribe({
        next: (response) => {
          if (response.success) {
            void this.router.navigate(['/login']);
          } else {
            this.logoutError.set('Logout failed. Please try again.');
          }
        },
        error: () => {
          this.logoutError.set(
            'Could not complete logout. Please try again.'
          );
        }
      });
  }
}