import { Routes } from '@angular/router';

import { authGuard } from './core/auth.guard';
import { adminGuard } from './core/admin.guard';
import { coordenadorGuard } from './core/coordenador.guard';

import { AcessoComponent } from './components/first-component/acesso';
import { PainelestagiarioComponent } from './components/first-component/painelestagiario';
import { TelainicialComponent } from './components/first-component/telainicial';

import { PainelAdmin } from './components/admin/painel-admin/painel-admin';
import { UtilizadoresAdminComponent } from './components/admin/utilizadores-admin/utilizadores-admin';

import {
  PainelCoordenadorComponent
} from './components/coordenador/painel-coordenador/painel-coordenador';


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
      authGuard
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