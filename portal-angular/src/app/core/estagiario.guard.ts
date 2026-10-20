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


export const estagiarioGuard: CanActivateFn = () => {

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


  // Permitir apenas estagiários.
  if (utilizador.tipo_utilizador !== 'estagiario') {

    if (utilizador.tipo_utilizador === 'administrador') {
      return router.createUrlTree(['/admin']);
    }

    if (utilizador.tipo_utilizador === 'coordenador') {
      return router.createUrlTree(['/coordenador']);
    }

    if (utilizador.tipo_utilizador === 'supervisor') {
      return router.createUrlTree(['/supervisor']);
    }

    return router.createUrlTree(['/telainicial']);
  }


  return true;
};
