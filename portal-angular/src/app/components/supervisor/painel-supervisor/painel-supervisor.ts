import { CommonModule } from '@angular/common';

import {
  Component,
  OnInit,
  inject,
  signal
} from '@angular/core';

import { Router } from '@angular/router';

import {
  SupervisorService,
  PainelSupervisor,
  EstagioSupervisionado
} from '../../../services/supervisor.service';

import { AuthService } from '../../../core/auth.service';

import { PerfilService } from '../../../services/perfil.service';

import {
  LayoutPainelComponent,
  GrupoNavegacaoPainel
} from '../../shared/layout-painel/layout-painel';

import {
  EstagioSupervisorComponent
} from '../../shared/estagio-supervisor/estagio-supervisor';


type SecaoSupervisor =
  | 'estagios'
  | 'acompanhamento';


@Component({
  selector: 'app-painel-supervisor',
  standalone: true,
  imports: [
    CommonModule,
    LayoutPainelComponent,
    EstagioSupervisorComponent
  ],
  templateUrl: './painel-supervisor.html',
  styleUrls: ['./painel-supervisor.css']
})
export class PainelSupervisorComponent implements OnInit {

  private readonly supervisorService =
    inject(SupervisorService);

  private readonly authService =
    inject(AuthService);

  private readonly perfilService =
    inject(PerfilService);

  private readonly router =
    inject(Router);


  // ============================================================
  // ESTADO DO PAINEL
  // ============================================================

  carregando = signal(true);

  erro = signal('');

  nomeSupervisor = signal('');

  totalEstagiosAtivos = signal(0);

  estagios = signal<EstagioSupervisionado[]>([]);


  // ============================================================
  // NAVEGAÇÃO LATERAL
  // ============================================================

  secaoAtiva = signal<SecaoSupervisor>('estagios');

  readonly gruposNavegacao: GrupoNavegacaoPainel[] = [
    {
      titulo: 'ACOMPANHAMENTO',
      itens: [
        {
          id: 'estagios',
          rotulo: 'Estágios',
          icone: '▣'
        },
        {
          id: 'acompanhamento',
          rotulo: 'Gestão do estágio',
          icone: '★'
        }
      ]
    }
  ];


  // ============================================================
  // CICLO DE VIDA
  // ============================================================

  ngOnInit(): void {

    this.carregarPainel();
  }


  // ============================================================
  // CARREGAR PAINEL (APENAS LEITURA)
  // ============================================================

  carregarPainel(): void {

    this.carregando.set(true);

    this.erro.set('');


    this.supervisorService
      .dashboard()
      .subscribe({

        next: (painel: PainelSupervisor) => {

          this.nomeSupervisor.set(
            painel.utilizador.nome
          );

          this.totalEstagiosAtivos.set(
            painel.resumo.estagios_ativos
          );

          this.estagios.set(
            painel.estagios
          );

          this.carregando.set(false);

        },

        error: erro => {

          console.error(
            'Erro ao carregar o painel do supervisor:',
            erro
          );

          this.erro.set(
            this.mensagemErro(erro)
          );

          this.carregando.set(false);

        }

      });

  }


  // ============================================================
  // INTERPRETAR ERROS DA API
  // ============================================================

  private mensagemErro(erro: any): string {

    if (erro.status === 401) {

      return (
        'A sessão expirou. Inicia sessão novamente.'
      );

    }

    if (erro.status === 403) {

      return (
        erro.error?.message ||
        'Não tens permissão para aceder a este painel.'
      );

    }

    return (
      erro.error?.message ||
      'Não foi possível carregar os estágios.'
    );

  }


  // ============================================================
  // FORMATAR DATAS
  // ============================================================

  formatarData(valor: string | null): string {

    if (!valor) {

      return '—';

    }

    const data = new Date(valor);

    if (Number.isNaN(data.getTime())) {

      return '—';

    }

    return data.toLocaleDateString('pt-PT');

  }


  // ============================================================
  // NAVEGAÇÃO
  // ============================================================

  irParaSecao(secao: string): void {
    this.secaoAtiva.set(secao as SecaoSupervisor);

    document.getElementById(secao)?.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });
  }


  // ============================================================
  // ENCERRAR SESSÃO
  // ============================================================

  /** Foto de perfil do utilizador autenticado. */
  readonly fotoPerfil = this.authService.fotoUrl;

  /**
   * Envia a foto escolhida no topo para o servidor e atualiza a sessão.
   */
  alterarFotoPerfil(ficheiro: File): void {

    this.perfilService
      .atualizarFoto(ficheiro)
      .subscribe({

        next: (resposta) => {

          this.authService.definirFoto(
            resposta.foto_url
          );

        },

        error: (erro: unknown) => {

          console.error(
            'Erro ao atualizar a foto de perfil:',
            erro
          );

        }

      });

  }

  sair(): void {

    this.authService
      .logout()
      .subscribe({

        next: () => {

          this.router.navigate([
            '/acesso'
          ]);

        },

        error: () => {

          // Mesmo que a API falhe, regressar ao acesso.
          this.router.navigate([
            '/acesso'
          ]);

        }

      });

  }

}
