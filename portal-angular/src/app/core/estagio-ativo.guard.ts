import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, map, of } from 'rxjs';

import { EstagioAtivoService } from '../services/estagio-ativo.service';

export const estagioAtivoGuard: CanActivateFn = () => {
  const router = inject(Router);
  const estagioService = inject(EstagioAtivoService);

  return estagioService.verificarEstagioAtivo().pipe(
    map(() => true),
    catchError((erro) => {
      if (erro.status === 401) {
        return of(router.createUrlTree(['/acesso']));
      }

      return of(router.createUrlTree(['/estagiario']));
    })
  );
};