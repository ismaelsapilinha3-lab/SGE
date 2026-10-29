import {
  CommonModule,
  isPlatformBrowser
} from '@angular/common';

import {
  Component,
  ElementRef,
  HostListener,
  PLATFORM_ID,
  inject,
  signal
} from '@angular/core';

import { Router } from '@angular/router';

import {
  Notificacao,
  NotificacaoService
} from '../../../services/notificacao.service';


/**
 * Sino de notificações partilhado pelos painéis do estagiário,
 * supervisor, coordenador e administrador.
 *
 * Mostra o total de notificações por ler, abre a lista e permite
 * marcar como lidas ou remover, sempre contra a API Laravel.
 */
@Component({
  selector: 'app-sino-notificacoes',
  standalone: true,

  imports: [
    CommonModule
  ],

  templateUrl: './sino-notificacoes.html',

  styleUrl: './sino-notificacoes.css'
})
export class SinoNotificacoesComponent {

  private readonly notificacaoService =
    inject(NotificacaoService);

  private readonly elemento =
    inject(ElementRef);

  private readonly platformId =
    inject(PLATFORM_ID);

  private readonly router =
    inject(Router);


  // ============================================================
  // ESTADO
  // ============================================================

  aberto = signal(false);

  carregando = signal(false);

  processando = signal(false);

  erro = signal('');

  notificacoes = signal<Notificacao[]>([]);

  naoLidas = signal(0);


  // ============================================================
  // CICLO DE VIDA
  // ============================================================

  constructor() {

    if (isPlatformBrowser(this.platformId)) {

      this.carregar();

    }

  }


  // ============================================================
  // FECHAR AO CLICAR FORA DO SINO
  // ============================================================

  @HostListener('document:click', [
    '$event'
  ])
  aoClicarFora(evento: Event): void {

    if (!this.aberto()) {

      return;

    }

    const alvo = evento.target as Node | null;

    if (alvo && this.elemento.nativeElement.contains(alvo)) {

      return;

    }

    this.fechar();

  }


  // ============================================================
  // ABRIR E FECHAR
  // ============================================================

  alternar(): void {

    if (this.aberto()) {

      this.fechar();

      return;

    }

    this.aberto.set(true);

    this.carregar();

  }


  fechar(): void {

    this.aberto.set(false);

  }


  // ============================================================
  // ABRIR DESTINO DA NOTIFICAÇÃO
  // ============================================================

  abrirDestino(notificacao: Notificacao): void {

    if (!notificacao.url) {

      return;

    }

    this.marcarComoLida(notificacao);

    this.fechar();

    this.router.navigateByUrl(notificacao.url);

  }


  // ============================================================
  // CARREGAR NOTIFICAÇÕES
  // ============================================================

  carregar(): void {

    this.carregando.set(true);

    this.erro.set('');


    this.notificacaoService
      .listar()
      .subscribe({

        next: resposta => {

          this.notificacoes.set(
            resposta.notificacoes ?? []
          );

          this.naoLidas.set(
            resposta.resumo?.nao_lidas ?? 0
          );

          this.carregando.set(false);

        },

        error: erro => {

          console.error(
            'Erro ao carregar notificações:',
            erro
          );

          this.carregando.set(false);

          this.erro.set(
            this.mensagemErro(
              erro,
              'Não foi possível carregar as notificações.'
            )
          );

        }

      });

  }


  // ============================================================
  // MARCAR COMO LIDA
  // ============================================================

  marcarComoLida(notificacao: Notificacao): void {

    if (notificacao.lida_em) {

      return;

    }

    this.erro.set('');


    this.notificacaoService
      .marcarComoLida(notificacao.id_notificacao)
      .subscribe({

        next: () => {

          this.notificacoes.update(
            lista => lista.map(
              item => item.id_notificacao
                === notificacao.id_notificacao
                ? {
                  ...item,

                  lida_em:
                    new Date().toISOString()
                }
                : item
            )
          );

          this.naoLidas.update(
            total => Math.max(0, total - 1)
          );

        },

        error: erro => {

          console.error(
            'Erro ao marcar notificação como lida:',
            erro
          );

          this.erro.set(
            this.mensagemErro(
              erro,
              'Não foi possível marcar a notificação como lida.'
            )
          );

        }

      });

  }


  // ============================================================
  // MARCAR TODAS COMO LIDAS
  // ============================================================

  marcarTodas(): void {

    if (this.processando() || this.naoLidas() === 0) {

      return;

    }

    this.processando.set(true);

    this.erro.set('');


    this.notificacaoService
      .marcarTodasComoLidas()
      .subscribe({

        next: () => {

          const agora = new Date().toISOString();

          this.notificacoes.update(
            lista => lista.map(
              notificacao => ({
                ...notificacao,

                lida_em:
                  notificacao.lida_em ?? agora
              })
            )
          );

          this.naoLidas.set(0);

          this.processando.set(false);

        },

        error: erro => {

          console.error(
            'Erro ao marcar todas as notificações:',
            erro
          );

          this.processando.set(false);

          this.erro.set(
            this.mensagemErro(
              erro,
              'Não foi possível marcar as notificações como lidas.'
            )
          );

        }

      });

  }


  // ============================================================
  // REMOVER
  // ============================================================

  remover(notificacao: Notificacao): void {

    if (this.processando()) {

      return;

    }

    this.processando.set(true);

    this.erro.set('');


    this.notificacaoService
      .remover(notificacao.id_notificacao)
      .subscribe({

        next: () => {

          const estavaPorLer =
            !notificacao.lida_em;

          this.notificacoes.update(
            lista => lista.filter(
              item => item.id_notificacao
                !== notificacao.id_notificacao
            )
          );

          if (estavaPorLer) {

            this.naoLidas.update(
              total => Math.max(0, total - 1)
            );

          }

          this.processando.set(false);

        },

        error: erro => {

          console.error(
            'Erro ao remover notificação:',
            erro
          );

          this.processando.set(false);

          this.erro.set(
            this.mensagemErro(
              erro,
              'Não foi possível remover a notificação.'
            )
          );

        }

      });

  }


  // ============================================================
  // AUXILIARES
  // ============================================================

  formatarData(valor: string | null): string {

    if (!valor) {

      return '—';

    }

    const data = new Date(valor);

    if (Number.isNaN(data.getTime())) {

      return '—';

    }

    return data.toLocaleString('pt-PT', {

      day: '2-digit',

      month: 'short',

      hour: '2-digit',

      minute: '2-digit'

    });

  }


  private mensagemErro(
    erro: any,
    mensagemPadrao: string
  ): string {

    if (
      erro?.status === 422 &&
      erro?.error?.errors
    ) {

      const mensagens = Object.values(
        erro.error.errors
      ) as string[][];

      const primeira = mensagens
        .flat()
        .find(
          mensagem => typeof mensagem === 'string'
        );

      if (primeira) {

        return primeira;

      }

    }

    if (erro?.status === 401) {

      return (
        'A sessão expirou. Inicia sessão novamente.'
      );

    }

    return (
      erro?.error?.message || mensagemPadrao
    );

  }

}
