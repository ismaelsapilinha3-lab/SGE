import { Routes } from '@angular/router';

import { authGuard } from './core/auth.guard';
import { adminGuard } from './core/admin.guard';

import { AcessoComponent } from './components/first-component/acesso';
import { PainelestagiarioComponent } from './components/first-component/painelestagiario';
import { TelainicialComponent } from './components/first-component/telainicial';

import { PainelAdmin } from './components/admin/painel-admin/painel-admin';
import { AreaReservadaComponent } from './components/first-component/area-reservada/area-reservada';
import { estagioAtivoGuard } from './core/estagio-ativo.guard';
import { UtilizadoresAdminComponent } from './components/admin/utilizadores-admin/utilizadores-admin';

export const routes: Routes = [

  // Página inicial
  {
    path: '',
    redirectTo: 'telainicial',
    pathMatch: 'full'
  },

  // Portal público
  {
    path: 'telainicial',
    component: TelainicialComponent
  },

{
path: 'area-reservada',
component: AreaReservadaComponent,
canActivate: [authGuard, estagioAtivoGuard]
},

  // Autenticação
  {
    path: 'acesso',
    component: AcessoComponent
  },

  // Painel do estagiário
  {
    path: 'estagiario',
    component: PainelestagiarioComponent,
    canActivate: [authGuard]
  },

  // Painel administrativo
  {
    path: 'admin',
    component: PainelAdmin,
    canActivate: [authGuard, adminGuard]
  },

  // Gestão de utilizadores
  {
    path: 'admin/utilizadores',
    component: UtilizadoresAdminComponent,
    canActivate: [authGuard, adminGuard]
  },

  // Rota desconhecida
  {
    path: '**',
    redirectTo: 'telainicial'
  }

];
