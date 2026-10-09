import { isPlatformBrowser } from '@angular/common';
import { PLATFORM_ID, inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService, ROTA_ACESSO } from './auth.service';

export const authGuard: CanActivateFn = () => {
  // No servidor (SSR) não há sessão para ler; a verificação a sério acontece no navegador
  if (!isPlatformBrowser(inject(PLATFORM_ID))) {
    return true;
  }

  const auth = inject(AuthService);
  const router = inject(Router);

  return auth.autenticado() ? true : router.createUrlTree([ROTA_ACESSO]);
};