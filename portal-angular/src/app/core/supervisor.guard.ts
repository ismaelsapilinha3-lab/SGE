import { isPlatformBrowser } from '@angular/common';

import {
  PLATFORM_ID,
  inject
} from '@angular/core';

import {
  CanActivateFn,
  Router
} from '@angular/router';

import {
  AuthService,
  ROTA_ACESSO
} from './auth.service';


export const supervisorGuard: CanActivateFn = () => {

  // Durante a renderização no servidor, deixar a verificação
  // definitiva para o navegador.
  if (!isPlatformBrowser(inject(PLATFORM_ID))) {
    return true;
  }

  const auth = inject(AuthService);

  const router = inject(Router);

  const utilizador = auth.utilizador();


  // Exigir autenticação.
  if (!utilizador) {
    return router.createUrlTree([ROTA_ACESSO]);
  }


  // Permitir apenas supervisores.
  if (utilizador.tipo_utilizador !== 'supervisor') {

    if (utilizador.tipo_utilizador === 'administrador') {
      return router.createUrlTree(['/admin']);
    }

    if (utilizador.tipo_utilizador === 'coordenador') {
      return router.createUrlTree(['/coordenador']);
    }

    if (utilizador.tipo_utilizador === 'estagiario') {
      return router.createUrlTree(['/estagiario']);
    }

    return router.createUrlTree(['/telainicial']);
  }


  return true;
};
