import { Routes } from '@angular/router';

import { AcessoComponent } from './components/first-component/acesso';
import { TelainicialComponent } from './components/first-component/telainicial';
import { PainelestagiarioComponent } from './components/first-component/painelestagiario';

export const routes: Routes = [
  {
    path: 'acesso',
    component: AcessoComponent
  },
  {
    path: 'telainicial',
    component: TelainicialComponent
  },
  {
    path: 'painelestagiario',
    component: PainelestagiarioComponent
  }
  
];