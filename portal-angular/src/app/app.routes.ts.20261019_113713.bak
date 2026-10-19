import { Routes } from '@angular/router';

import { authGuard } from './core/auth.guard';
import { adminGuard } from './core/admin.guard';

import { AcessoComponent } from './components/first-component/acesso';
import { PainelestagiarioComponent } from './components/first-component/painelestagiario';
import { TelainicialComponent } from './components/first-component/telainicial';

import { PainelAdmin } from './components/admin/painel-admin/painel-admin';

export const routes: Routes = [
{
path: '',
redirectTo: 'telainicial',
pathMatch: 'full'
},

{
path: 'telainicial',
component: TelainicialComponent
},

{
path: 'acesso',
component: AcessoComponent
},

{
path: 'estagiario',
component: PainelestagiarioComponent,
canActivate: [authGuard]
},

{
path: 'admin',
component: PainelAdmin,
canActivate: [authGuard, adminGuard]
},

{
path: '**',
redirectTo: 'telainicial'
}
];



