import { Routes } from '@angular/router';

import { authGuard } from './core/auth.guard';
import { adminGuard } from './core/admin.guard';
import { coordenadorGuard } from './core/coordenador.guard';
import { estagiarioGuard } from './core/estagiario.guard';
import { supervisorGuard } from './core/supervisor.guard';
import { estagioAtivoGuard } from './core/estagio-ativo.guard';


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
    pathMatch: 'full',
    loadComponent: () => import(
      './components/first-component/telainicial'
    ).then(modulo => modulo.TelainicialComponent)
  },

  {
    path: 'area-reservada',
    pathMatch: 'full',
    loadComponent: () => import(
      './components/first-component/area-reservada/area-reservada'
    ).then(modulo => modulo.AreaReservadaComponent),
    canActivate: [authGuard, estagioAtivoGuard]
  },

  // ============================================================
  // AUTENTICAÇÃO
  // ============================================================
  {
    path: 'acesso',
    pathMatch: 'full',
    loadComponent: () => import(
      './components/first-component/acesso'
    ).then(modulo => modulo.AcessoComponent)
  },

  {
    path: 'recuperar-password',
    pathMatch: 'full',
    loadComponent: () => import(
      './components/first-component/recuperar-password'
    ).then(modulo => modulo.RecuperarPasswordComponent)
  },

  {
    path: 'redefinir-password',
    pathMatch: 'full',
    data: {
      modo: 'redefinicao'
    },
    loadComponent: () => import(
      './components/first-component/recuperar-password'
    ).then(modulo => modulo.RecuperarPasswordComponent)
  },


  // ============================================================
  // PAINEL DO ESTAGIÁRIO
  // ============================================================
  {
    path: 'estagiario',
    pathMatch: 'full',
    loadComponent: () => import(
      './components/first-component/painelestagiario'
    ).then(modulo => modulo.PainelestagiarioComponent),
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
    pathMatch: 'full',
    loadComponent: () => import(
      './components/coordenador/painel-coordenador/painel-coordenador'
    ).then(modulo => modulo.PainelCoordenadorComponent),
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
    pathMatch: 'full',
    loadComponent: () => import(
      './components/supervisor/painel-supervisor/painel-supervisor'
    ).then(modulo => modulo.PainelSupervisorComponent),
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
    pathMatch: 'full',
    loadComponent: () => import(
      './components/admin/painel-admin/painel-admin'
    ).then(modulo => modulo.PainelAdmin),
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
    pathMatch: 'full',
    loadComponent: () => import(
      './components/admin/utilizadores-admin/utilizadores-admin'
    ).then(modulo => modulo.UtilizadoresAdminComponent),
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
