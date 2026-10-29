import { CommonModule } from '@angular/common';

import {
  Component,
  OnDestroy,
  OnInit,
  inject,
  signal
} from '@angular/core';

import { ActivatedRoute, Router } from '@angular/router';

import { Subscription } from 'rxjs';

import {
  SupervisorService,
  PainelSupervisor,
  EstagioSupervisionado,
  EntrevistaSupervisor
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
  | 'entrevistas'
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
export class PainelSupervisorComponent implements OnInit, OnDestroy {

  private readonly supervisorService =
    inject(SupervisorService);

  private readonly authService =
    inject(AuthService);

  private readonly perfilService =
    inject(PerfilService);

  private readonly router =
    inject(Router);

  private readonly route =
    inject(ActivatedRoute);

  private readonly subscriptions =
    new Subscription();


  // ============================================================
  // ESTADO DO PAINEL
  // ============================================================

  carregando = signal(true);

  erro = signal('');

  nomeSupervisor = signal('');

  totalEstagiosAtivos = signal(0);

  estagios = signal<EstagioSupervisionado[]>([]);

  entrevistas = signal<EntrevistaSupervisor[]>([]);

  totalEntrevistasPendentes = signal(0);


  // ============================================================
  // NAVEGAÇÃO LATERAL
  // ============================================================

  secaoAtiva = signal<SecaoSupervisor>('estagios');

  /** Estágio pedido pelo destino de uma notificação. */
  readonly estagioSolicitado = signal<number | null>(null);

  /** Aba pedida pelo destino de uma notificação. */
  readonly abaSolicitada = signal<string | null>(null);

  readonly gruposNavegacao: GrupoNavegacaoPainel[] = [
    {
      titulo: 'ACOMPANHAMENTO',
      itens: [
        {
          id: 'entrevistas',
          rotulo: 'Entrevistas',
          icone: '•'
        },
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

    this.subscriptions.add(
      this.route.queryParamMap.subscribe(parametros => {

        const secao = parametros.get('secao');

        if (
          secao === 'estagios'
          || secao === 'entrevistas'
          || secao === 'acompanhamento'
        ) {

          this.secaoAtiva.set(secao);

          if (typeof document !== 'undefined') {

            window.setTimeout(() => {

              document
                .getElementById(secao)
                ?.scrollIntoView({
                  behavior: 'smooth',
                  block: 'start'
                });

            });

          }

        }

        const idEstagio = Number(parametros.get('estagio'));

        if (!Number.isInteger(idEstagio) || idEstagio <= 0) {

          return;

        }

        this.estagioSolicitado.set(idEstagio);

        this.abaSolicitada.set(parametros.get('aba'));

        this.secaoAtiva.set('acompanhamento');

        this.deslocarParaAcompanhamento();

      })
    );

    this.carregarPainel();
  }


  ngOnDestroy(): void {

    this.subscriptions.unsubscribe();

  }


  /**
   * Leva o utilizador à secção de acompanhamento depois de a vista
   * estar renderizada.
   */
  private deslocarParaAcompanhamento(): void {

    if (typeof document === 'undefined') {

      return;

    }

    window.setTimeout(() => {

      document
        .getElementById('acompanhamento')
        ?.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });

    });

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

          this.entrevistas.set(
            painel.entrevistas ?? []
          );

          this.totalEntrevistasPendentes.set(
            painel.resumo.entrevistas_pendentes ?? 0
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
  // RESULTADO DA ENTREVISTA
  // ============================================================

  nomeResultadoEntrevista(
    resultado: EntrevistaSupervisor['resultado']
  ): string {

    switch (resultado) {

      case 'aprovado':
        return 'Aprovada';

      case 'reprovado':
        return 'Reprovada';

      default:
        return 'Pendente';

    }

  }


  classeResultadoEntrevista(
    resultado: EntrevistaSupervisor['resultado']
  ): string {

    switch (resultado) {

      case 'aprovado':
        return 'sup-tag sup-tag-aprovada';

      case 'reprovado':
        return 'sup-tag sup-tag-reprovada';

      default:
        return 'sup-tag sup-tag-pendente';

    }

  }


  formatarDataHora(valor: string | null): string {

    if (!valor) {

      return '—';

    }

    const data = new Date(valor);

    if (Number.isNaN(data.getTime())) {

      return '—';

    }

    return data.toLocaleString('pt-PT', {
      dateStyle: 'short',
      timeStyle: 'short'
    });

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

    this.limparDestinoNotificacao();
  }


  /**
   * Remove o destino da notificação da URL para que o mesmo link volte
   * a funcionar depois de o utilizador navegar manualmente.
   */
  private limparDestinoNotificacao(): void {

    if (
      !this.route.snapshot.queryParamMap.has('estagio')
      && !this.route.snapshot.queryParamMap.has('aba')
      && !this.route.snapshot.queryParamMap.has('secao')
    ) {

      return;

    }

    this.estagioSolicitado.set(null);

    this.abaSolicitada.set(null);

    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: {
        estagio: null,
        aba: null,
        secao: null
      },
      queryParamsHandling: 'merge',
      replaceUrl: true
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
