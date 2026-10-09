import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { environment } from '../../environments/environment';
import { AuthService, ROTA_ACESSO } from './auth.service';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  // O token só segue para a nossa API, nunca para outros endereços
  const paraApi = req.url.startsWith(environment.apiUrl);
  const token = auth.token();

  const pedido =
    paraApi && token
      ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } })
      : req;

  return next(pedido).pipe(
    catchError((erro: HttpErrorResponse) => {
      // Token expirado ou inválido: limpa a sessão e volta ao login.
      // O próprio login devolve 422 quando as credenciais estão erradas, por isso não passa por aqui.
      if (erro.status === 401 && paraApi) {
        auth.terminarLocal();
        router.navigate([ROTA_ACESSO]);
      }
      return throwError(() => erro);
    })
  );
};