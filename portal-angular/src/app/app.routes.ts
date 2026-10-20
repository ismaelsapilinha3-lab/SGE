import { Routes } from '@angular/router';

import { authGuard } from './core/auth.guard';
import { adminGuard } from './core/admin.guard';
import { coordenadorGuard } from './core/coordenador.guard';
import { estagiarioGuard } from './core/estagiario.guard';
import { supervisorGuard } from './core/supervisor.guard';

import { AcessoComponent } from './components/first-component/acesso';
import { PainelestagiarioComponent } from './components/first-component/painelestagiario';
import { TelainicialComponent } from './components/first-component/telainicial';

import { PainelAdmin } from './components/admin/painel-admin/painel-admin';
import { UtilizadoresAdminComponent } from './components/admin/utilizadores-admin/utilizadores-admin';

import {
  PainelCoordenadorComponent
} from './components/coordenador/painel-coordenador/painel-coordenador';

import {
  PainelSupervisorComponent
} from './components/supervisor/painel-supervisor/painel-supervisor';


export const routes: Routes = [

  // ============================================================
  // PÁGINA INICIAL
  // ============================================================
  {
    path: '',
    redirectTo: 'telainicial',
    pathMatch: 'full'
  },


  // ============================================================
  // PORTAL PÚBLICO
  // ============================================================
  {
    path: 'telainicial',
    component: TelainicialComponent
  },


  // ============================================================
  // AUTENTICAÇÃO
  // ============================================================
  {
    path: 'acesso',
    component: AcessoComponent
  },


  // ============================================================
  // PAINEL DO ESTAGIÁRIO
  // ============================================================
  {
    path: 'estagiario',
    component: PainelestagiarioComponent,
    canActivate: [
      authGuard,
      estagiarioGuard
    ]
  },


  // ============================================================
  // PAINEL DO COORDENADOR
  // ============================================================
  {
    path: 'coordenador',
    component: PainelCoordenadorComponent,
    canActivate: [
      authGuard,
      coordenadorGuard
    ]
  },


  // ============================================================
  // PAINEL DO SUPERVISOR
  // ============================================================
  {
    path: 'supervisor',
    component: PainelSupervisorComponent,
    canActivate: [
      authGuard,
      supervisorGuard
    ]
  },


  // ============================================================
  // PAINEL ADMINISTRATIVO
  // ============================================================
  {
    path: 'admin',
    component: PainelAdmin,
    canActivate: [
      authGuard,
      adminGuard
    ]
  },


  // ============================================================
  // GESTÃO DE UTILIZADORES
  // ============================================================
  {
    path: 'admin/utilizadores',
    component: UtilizadoresAdminComponent,
    canActivate: [
      authGuard,
      adminGuard
    ]
  },


  // ============================================================
  // ROTA DESCONHECIDA
  // ============================================================
  {
    path: '**',
    redirectTo: 'telainicial'
  }

];