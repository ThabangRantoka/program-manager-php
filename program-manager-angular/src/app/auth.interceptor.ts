import { inject } from '@angular/core';
import {
  HttpErrorResponse,
  HttpInterceptorFn
} from '@angular/common/http';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

import { AuthService } from './auth.service';
import { API_BASE } from './api.config';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  // Leave requests to other websites unchanged.
  if (!req.url.startsWith(`${API_BASE}/`)) {
    return next(req);
  }

  // Login uses a username and password, not an existing token.
  const endpoint = req.url.split('?')[0];

  if (endpoint === `${API_BASE}/api_login.php`) {
    return next(req);
  }

  const auth = inject(AuthService);
  const router = inject(Router);
  const token = auth.token;

  const authReq = token
    ? req.clone({
        setHeaders: {
          Authorization: `Bearer ${token}`
        }
      })
    : req;

  return next(authReq).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 401) {
        auth.clearSession();
        void router.navigate(['/login']);
      }

      return throwError(() => error);
    })
  );
};