import { Injectable, inject, PLATFORM_ID } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';
import { API_BASE } from './api.config';

export interface AuthSession {
  token: string;
  username: string;
  role: string;
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly platformId = inject(PLATFORM_ID);
  private readonly storageKey = 'pm_auth';

  login(username: string, password: string): Observable<AuthSession> {
    return this.http
      .post<AuthSession>(
        `${API_BASE}/api_login.php`,
        { username, password }
      )
      .pipe(
        tap(session => {
          if (isPlatformBrowser(this.platformId)) {
            localStorage.setItem(
              this.storageKey,
              JSON.stringify(session)
            );
          }
        })
      );
  }

  private get session(): AuthSession | null {
    if (!isPlatformBrowser(this.platformId)) {
      return null;
    }

    try {
      const raw = localStorage.getItem(this.storageKey);

      if (!raw) {
        return null;
      }

      const saved = JSON.parse(raw);

      if (
        saved === null ||
        typeof saved !== 'object' ||
        typeof saved.token !== 'string' ||
        !/^[a-f0-9]{64}$/i.test(saved.token) ||
        typeof saved.username !== 'string' ||
        typeof saved.role !== 'string'
      ) {
        return null;
      }

      return saved;
    } catch {
      return null;
    }
  }

  get token(): string | null {
    return this.session?.token ?? null;
  }

  get username(): string | null {
    return this.session?.username ?? null;
  }

  get role(): string | null {
    return this.session?.role ?? null;
  }

  isLoggedIn(): boolean {
    return this.token !== null;
  }

  hasRole(...roles: string[]): boolean {
    const role = this.role;
    return role !== null && roles.includes(role);
  }

  clearSession(): void {
    if (isPlatformBrowser(this.platformId)) {
      localStorage.removeItem(this.storageKey);
    }
  }

    logout(): Observable<{ success: boolean }> {
    const token = this.token;

    return this.http
      .post<{ success: boolean }>(
        `${API_BASE}/api_logout.php`,
        {},
        {
          headers: token
            ? { Authorization: `Bearer ${token}` }
            : {}
        }
      )
      .pipe(
        tap(response => {
          if (response.success) {
            this.clearSession();
          }
        })
      );
  }
  
}