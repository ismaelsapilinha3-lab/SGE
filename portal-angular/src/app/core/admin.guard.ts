import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService, ROTA_ACESSO } from './auth.service';

export const adminGuard: CanActivateFn = () => {
const auth = inject(AuthService);
const router = inject(Router);

const utilizador = auth.utilizador();

if (!utilizador) {
return router.createUrlTree([ROTA_ACESSO]);
}

if (utilizador.tipo_utilizador !== 'administrador') {
return router.createUrlTree(['/estagiario']);
}

return true;
};
