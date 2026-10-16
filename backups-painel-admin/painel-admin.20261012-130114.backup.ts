
import { CommonModule, isPlatformBrowser } from '@angular/common';

import {
  Component,
  OnDestroy,
  OnInit,
  PLATFORM_ID,
  inject,
  signal
} from '@angular/core';

import {
  DomSanitizer,
  SafeResourceUrl
} from '@angular/platform-browser';

import { Router } from '@angular/router';

import {
  Subscription
} from 'rxjs';

import {
  AdminService,
  DashboardAdmin,
  Candidatura,
  CandidaturasResponse,
  Departamento,
  Documento
} from '../../../services/admin.service';

import {
  AuthService
} from '../../../core/auth.service';


@Component({
  selector: 'app-painel-admin',

  standalone: true,

  imports: [
    CommonModule
  ],

  templateUrl: './painel-admin.html',

  styleUrl: './painel-admin.css'
})
export class PainelAdmin implements OnInit, OnDestroy {

  // ============================================================
  // SERVIÇOS
  // ============================================================

  private readonly adminService =
    inject(AdminService);

  private readonly authService =
    inject(AuthService);

  private readonly router =
    inject(Router);

  private readonly sanitizer =
    inject(DomSanitizer);

  private readonly platformId =
    inject(PLATFORM_ID);

  private readonly subscriptions =
    new Subscription();


  // ============================================================
  // DASHBOARD
  // ============================================================

  dashboard =
    signal<DashboardAdmin>({
      total_candidaturas: 0,
      em_analise: 0,
      aprovadas: 0,
      recusadas: 0
    });


  // ============================================================
  // CANDIDATURAS
  // ============================================================

  candidaturas =
    signal<Candidatura[]>([]);

  candidaturaSelecionada =
    signal<Candidatura | null>(null);


  // ============================================================
  // DEPARTAMENTOS / DIREÇÕES
  // ============================================================

  departamentos =
    signal<Departamento[]>([]);


  // ============================================================
  // PESQUISA E FILTROS
  // ============================================================

  pesquisa =
    signal('');

  estadoSelecionado =
    signal('');

  departamentoSelecionado =
    signal<number | null>(null);


  // ============================================================
  // PAGINAÇÃO
  // ============================================================

  paginaAtual =
    signal(1);

  ultimaPagina =
    signal(1);

  totalCandidaturas =
    signal(0);

  porPagina =
    signal(15);


  // ============================================================
  // CARREGAMENTOS
  // ============================================================

  carregandoDashboard =
    signal(false);

  carregandoCandidaturas =
    signal(false);

  carregandoDepartamentos =
    signal(false);

  carregandoDetalhes =
    signal(false);

  processandoDecisao =
    signal(false);

  abrindoDocumento =
    signal(false);


  // ============================================================
  // ERROS
  // ============================================================

  erroDashboard =
    signal('');

  erroCandidaturas =
    signal('');

  erroDepartamentos =
    signal('');

  erroDetalhes =
    signal('');

  erroDecisao =
    signal('');

  erroDocumento =
    signal('');


  // ============================================================
  // SUCESSO
  // ============================================================

  sucessoDecisao =
    signal('');


  // ============================================================
  // RECUSA
  // ============================================================

  mostrarRecusa =
    signal(false);

  motivoRecusa =
    signal('');


  // ============================================================
  // PREVIEW DE DOCUMENTO
  // ============================================================

  documentoPreviewAberto =
    signal(false);

  documentoPreviewUrl =
    signal<string | null>(null);

  documentoPreviewPdfUrl =
    signal<SafeResourceUrl | null>(null);

  documentoPreviewTipo =
    signal('');

  documentoPreviewNome =
    signal('');

  /*
   * Guardamos o Blob URL original do PDF para
   * poder revogá-lo corretamente.
   */
  private documentoPreviewBlobUrl:
    string | null = null;


  // ============================================================
  // CICLO DE VIDA
  // ============================================================

  ngOnInit(): void {

    this.carregarDashboard();

    this.carregarDepartamentos();

    this.carregarCandidaturas();
  }


  ngOnDestroy(): void {

    this.fecharPreviewDocumento();

    this.subscriptions.unsubscribe();
  }


  // ============================================================
  // CARREGAR DASHBOARD
  // ============================================================

  carregarDashboard(): void {

    this.carregandoDashboard.set(true);

    this.erroDashboard.set('');

    const sub =
      this.adminService
        .dashboard()
        .subscribe({

          next: (dados) => {

            this.dashboard.set(dados);

            this.carregandoDashboard.set(false);
          },

          error: (erro: unknown) => {

            console.error(
              'Erro ao carregar dashboard:',
              erro
            );

            this.carregandoDashboard.set(false);

            this.erroDashboard.set(
              'Não foi possível carregar os indicadores.'
            );
          }

        });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // CARREGAR DEPARTAMENTOS / DIREÇÕES
  // ============================================================

  carregarDepartamentos(): void {

    this.carregandoDepartamentos.set(true);

    this.erroDepartamentos.set('');

    const sub =
      this.adminService
        .listarDepartamentos()
        .subscribe({

          next: (dados) => {

            this.departamentos.set(
              dados ?? []
            );

            this.carregandoDepartamentos.set(false);
          },

          error: (erro: unknown) => {

            console.error(
              'Erro ao carregar direções:',
              erro
            );

            this.departamentos.set([]);

            this.carregandoDepartamentos.set(false);

            this.erroDepartamentos.set(
              'Não foi possível carregar as direções.'
            );
          }

        });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // CARREGAR CANDIDATURAS
  // ============================================================

  carregarCandidaturas(): void {

    this.carregandoCandidaturas.set(true);

    this.erroCandidaturas.set('');

    const sub =
      this.adminService
        .listarCandidaturas(

          this.pesquisa(),

          this.estadoSelecionado(),

          this.departamentoSelecionado()
            ?? undefined,

          this.paginaAtual(),

          this.porPagina()

        )
        .subscribe({

          next: (
            resposta: CandidaturasResponse
          ) => {

            this.candidaturas.set(
              resposta.data ?? []
            );

            this.totalCandidaturas.set(
              resposta.total ?? 0
            );

            this.ultimaPagina.set(
              Math.max(
                resposta.last_page ?? 1,
                1
              )
            );

            this.paginaAtual.set(
              resposta.current_page ?? 1
            );

            this.carregandoCandidaturas.set(
              false
            );
          },

          error: (erro: unknown) => {

            console.error(
              'Erro ao carregar candidaturas:',
              erro
            );

            this.candidaturas.set([]);

            this.totalCandidaturas.set(0);

            this.ultimaPagina.set(1);

            this.carregandoCandidaturas.set(
              false
            );

            this.erroCandidaturas.set(
              'Não foi possível carregar as candidaturas.'
            );
          }

        });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // ATUALIZAR PAINEL
  // ============================================================

  atualizarPainel(): void {

    this.erroDashboard.set('');

    this.erroCandidaturas.set('');

    this.erroDepartamentos.set('');

    this.carregarDashboard();

    this.carregarDepartamentos();

    this.carregarCandidaturas();
  }


  // ============================================================
  // PESQUISAR
  // ============================================================

  pesquisar(): void {

    this.paginaAtual.set(1);

    this.carregarCandidaturas();
  }


  // ============================================================
  // ALTERAR ESTADO
  // ============================================================

  alterarEstado(event: Event): void {

    const select =
      event.target as HTMLSelectElement;

    this.estadoSelecionado.set(
      select.value
    );

    this.paginaAtual.set(1);

    this.carregarCandidaturas();
  }


  // ============================================================
  // ALTERAR DIREÇÃO
  // ============================================================

  alterarDepartamento(
    event: Event
  ): void {

    const select =
      event.target as HTMLSelectElement;

    const valor =
      select.value;

    this.departamentoSelecionado.set(
      valor
        ? Number(valor)
        : null
    );

    this.paginaAtual.set(1);

    this.carregarCandidaturas();
  }


  // ============================================================
  // LIMPAR FILTROS
  // ============================================================

  limparFiltros(): void {

    this.pesquisa.set('');

    this.estadoSelecionado.set('');

    this.departamentoSelecionado.set(
      null
    );

    this.paginaAtual.set(1);

    this.carregarCandidaturas();
  }


  // ============================================================
  // NOME DO ESTADO
  // ============================================================

  nomeEstado(
    estado: Candidatura['estado']
  ): string {

    switch (estado) {

      case 'em_analise':
        return 'Em análise';

      case 'aprovada':
        return 'Aprovada';

      case 'recusada':
        return 'Recusada';

      case 'encerrada':
        return 'Encerrada';

      default:
        return estado;
    }
  }


  // ============================================================
  // CLASSE DO ESTADO
  // ============================================================

  classeEstado(
    estado: Candidatura['estado']
  ): string {

    switch (estado) {

      case 'em_analise':
        return 'estado-em-analise';

      case 'aprovada':
        return 'estado-aprovada';

      case 'recusada':
        return 'estado-recusada';

      case 'encerrada':
        return 'estado-encerrada';

      default:
        return '';
    }
  }


  // ============================================================
  // INICIAIS DO CANDIDATO
  // ============================================================

  iniciais(
    nome: string
  ): string {

    if (!nome?.trim()) {

      return 'U';
    }

    const partes =
      nome
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (partes.length === 1) {

      return partes[0]
        .substring(0, 2)
        .toUpperCase();
    }

    return (
      partes[0][0] +
      partes[partes.length - 1][0]
    ).toUpperCase();
  }


  // ============================================================
  // PAGINA ANTERIOR
  // ============================================================

  paginaAnterior(): void {

    const pagina =
      this.paginaAtual();

    if (pagina <= 1) {

      return;
    }

    this.paginaAtual.set(
      pagina - 1
    );

    this.carregarCandidaturas();
  }


  // ============================================================
  // PRÓXIMA PÁGINA
  // ============================================================

  proximaPagina(): void {

    const pagina =
      this.paginaAtual();

    if (
      pagina >= this.ultimaPagina()
    ) {

      return;
    }

    this.paginaAtual.set(
      pagina + 1
    );

    this.carregarCandidaturas();
  }


  // ============================================================
  // IR PARA PÁGINA
  // ============================================================

  irParaPagina(
    pagina: number
  ): void {

    if (
      pagina < 1 ||
      pagina > this.ultimaPagina() ||
      pagina === this.paginaAtual()
    ) {

      return;
    }

    this.paginaAtual.set(
      pagina
    );

    this.carregarCandidaturas();
  }


  // ============================================================
  // PÁGINAS VISÍVEIS
  // ============================================================

  paginasVisiveis(): number[] {

    const total =
      this.ultimaPagina();

    const atual =
      this.paginaAtual();

    const paginas: number[] = [];

    const inicio =
      Math.max(
        1,
        atual - 2
      );

    const fim =
      Math.min(
        total,
        atual + 2
      );

    for (
      let pagina = inicio;
      pagina <= fim;
      pagina++
    ) {

      paginas.push(pagina);
    }

    return paginas;
  }


  // ============================================================
  // VISUALIZAR CANDIDATURA
  // ============================================================

  visualizarCandidatura(
    candidatura: Candidatura
  ): void {

    this.abrirDetalhes(
      candidatura
    );
  }


  // ============================================================
  // ABRIR DETALHES
  // ============================================================

  abrirDetalhes(
    candidatura: Candidatura | number
  ): void {

    const id =
      typeof candidatura === 'number'
        ? candidatura
        : candidatura.id_candidatura;

    this.carregandoDetalhes.set(
      true
    );

    this.erroDetalhes.set('');

    this.erroDocumento.set('');

    this.erroDecisao.set('');

    this.sucessoDecisao.set('');

    this.mostrarRecusa.set(false);

    this.motivoRecusa.set('');

    /*
     * Mantemos o modal aberto quando já existe
     * uma candidatura selecionada.
     */
    const sub =
      this.adminService
        .obterCandidatura(id)
        .subscribe({

          next: (dados) => {

            this.candidaturaSelecionada.set(
              dados
            );

            this.carregandoDetalhes.set(
              false
            );
          },

          error: (erro: unknown) => {

            console.error(
              'Erro ao carregar detalhes:',
              erro
            );

            this.carregandoDetalhes.set(
              false
            );

            this.erroDetalhes.set(
              'Não foi possível carregar os detalhes da candidatura.'
            );
          }

        });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // FECHAR DETALHES
  // ============================================================

  fecharDetalhes(): void {

    this.candidaturaSelecionada.set(
      null
    );

    this.carregandoDetalhes.set(
      false
    );

    this.mostrarRecusa.set(
      false
    );

    this.motivoRecusa.set(
      ''
    );

    this.erroDetalhes.set(
      ''
    );

    this.erroDecisao.set(
      ''
    );

    this.sucessoDecisao.set(
      ''
    );

    this.erroDocumento.set(
      ''
    );
  }


  // ============================================================
  // ABRIR ÁREA DE RECUSA
  // ============================================================

  abrirRecusa(): void {

    const candidatura =
      this.candidaturaSelecionada();

    if (!candidatura) {

      return;
    }

    if (
      candidatura.estado !== 'em_analise'
    ) {

      this.erroDecisao.set(
        'Esta candidatura já não está em análise.'
      );

      return;
    }

    this.erroDecisao.set('');

    this.sucessoDecisao.set('');

    this.motivoRecusa.set('');

    this.mostrarRecusa.set(
      true
    );
  }


  // ============================================================
  // CANCELAR RECUSA
  // ============================================================

  cancelarRecusa(): void {

    this.mostrarRecusa.set(
      false
    );

    this.motivoRecusa.set(
      ''
    );

    this.erroDecisao.set(
      ''
    );
  }


  // ============================================================
  // ALTERAR MOTIVO DA RECUSA
  // ============================================================

  alterarMotivoRecusa(
    event: Event
  ): void {

    const textarea =
      event.target as HTMLTextAreaElement;

    this.motivoRecusa.set(
      textarea.value
    );
  }


  // ============================================================
  // APROVAR CANDIDATURA
  // ============================================================

  aprovarSelecionada(): void {

    const candidatura =
      this.candidaturaSelecionada();

    if (!candidatura) {

      return;
    }

    if (
      candidatura.estado !== 'em_analise'
    ) {

      this.erroDecisao.set(
        'Esta candidatura já não está em análise.'
      );

      return;
    }

    const confirmar =
      window.confirm(
        `Tem certeza que deseja aprovar a candidatura de ${candidatura.nome}?`
      );

    if (!confirmar) {

      return;
    }

    this.processandoDecisao.set(
      true
    );

    this.erroDecisao.set('');

    this.sucessoDecisao.set('');

    const sub =
      this.adminService
        .aprovarCandidatura(
          candidatura.id_candidatura
        )
        .subscribe({

          next: () => {

            this.processandoDecisao.set(
              false
            );

            this.sucessoDecisao.set(
              'Candidatura aprovada com sucesso.'
            );

            this.mostrarRecusa.set(
              false
            );

            this.atualizarCandidaturaLocal(
              'aprovada'
            );

            this.carregarDashboard();

            this.carregarCandidaturas();
          },

          error: (erro: unknown) => {

            console.error(
              'Erro ao aprovar candidatura:',
              erro
            );

            this.processandoDecisao.set(
              false
            );

            this.erroDecisao.set(
              'Não foi possível aprovar a candidatura.'
            );
          }

        });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // RECUSAR CANDIDATURA
  // ============================================================

  recusarSelecionada(): void {

    const candidatura =
      this.candidaturaSelecionada();

    if (!candidatura) {

      return;
    }

    if (
      candidatura.estado !== 'em_analise'
    ) {

      this.erroDecisao.set(
        'Esta candidatura já não está em análise.'
      );

      return;
    }

    const motivo =
      this.motivoRecusa()
        .trim();

    if (
      motivo.length < 5
    ) {

      this.erroDecisao.set(
        'O motivo da recusa deve ter pelo menos 5 caracteres.'
      );

      return;
    }

    if (
      motivo.length > 1000
    ) {

      this.erroDecisao.set(
        'O motivo da recusa não pode ultrapassar 1000 caracteres.'
      );

      return;
    }

    this.processandoDecisao.set(
      true
    );

    this.erroDecisao.set('');

    this.sucessoDecisao.set('');

    const sub =
      this.adminService
        .recusarCandidatura(
          candidatura.id_candidatura,
          motivo
        )
        .subscribe({

          next: () => {

            this.processandoDecisao.set(
              false
            );

            this.mostrarRecusa.set(
              false
            );

            this.sucessoDecisao.set(
              'Candidatura recusada com sucesso.'
            );

            this.atualizarCandidaturaLocal(
              'recusada',
              motivo
            );

            this.carregarDashboard();

            this.carregarCandidaturas();
          },

          error: (erro: unknown) => {

            console.error(
              'Erro ao recusar candidatura:',
              erro
            );

            this.processandoDecisao.set(
              false
            );

            this.erroDecisao.set(
              'Não foi possível recusar a candidatura.'
            );
          }

        });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // ATUALIZAR CANDIDATURA LOCALMENTE
  // ============================================================

  private atualizarCandidaturaLocal(
    estado: 'aprovada' | 'recusada',
    motivo?: string
  ): void {

    const atual =
      this.candidaturaSelecionada();

    if (!atual) {

      return;
    }

    this.candidaturaSelecionada.set({

      ...atual,

      estado,

      motivo_recusa:
        estado === 'recusada'
          ? motivo ?? null
          : null,

      data_decisao:
        new Date().toISOString()

    });
  }


  // ============================================================
  // VISUALIZAR DOCUMENTO
  // ============================================================

  visualizarDocumento(
    documento: Documento
  ): void {

    if (
      this.abrindoDocumento()
    ) {

      return;
    }

    this.abrindoDocumento.set(
      true
    );

    this.erroDocumento.set('');

    this.limparPreviewDocumento();

    const sub =
      this.adminService
        .visualizarDocumento(
          documento.id_documento
        )
        .subscribe({

          next: (blob: Blob) => {

            try {

              const tipo =
                this.inferirMimeType(
                  documento,
                  blob
                );

              const url =
                URL.createObjectURL(
                  blob
                );

              this.documentoPreviewBlobUrl =
                url;

              this.documentoPreviewTipo.set(
                tipo
              );

              this.documentoPreviewNome.set(
                documento.nome_arquivo
              );

              if (
                tipo === 'application/pdf'
              ) {

                const safeUrl =
                  this.sanitizer
                    .bypassSecurityTrustResourceUrl(
                      url
                    );

                this.documentoPreviewPdfUrl.set(
                  safeUrl
                );

                this.documentoPreviewUrl.set(
                  null
                );

              } else {

                this.documentoPreviewUrl.set(
                  url
                );

                this.documentoPreviewPdfUrl.set(
                  null
                );
              }

              this.documentoPreviewAberto.set(
                true
              );

            } catch (erro: unknown) {

              console.error(
                'Erro ao preparar documento:',
                erro
              );

              this.erroDocumento.set(
                'Não foi possível preparar o documento para visualização.'
              );
            }

            this.abrindoDocumento.set(
              false
            );
          },

          error: (erro: unknown) => {

            console.error(
              'Erro ao visualizar documento:',
              erro
            );

            this.abrindoDocumento.set(
              false
            );

            this.erroDocumento.set(
              'Não foi possível abrir o documento.'
            );
          }

        });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // IDENTIFICAR MIME TYPE
  // ============================================================

  private inferirMimeType(
    documento: Documento,
    blob: Blob
  ): string {

    if (
      blob.type &&
      blob.type !==
        'application/octet-stream'
    ) {

      return blob.type;
    }

    const nome =
      documento.nome_arquivo
        .toLowerCase();

    if (
      nome.endsWith('.pdf')
    ) {

      return 'application/pdf';
    }

    if (
      nome.endsWith('.png')
    ) {

      return 'image/png';
    }

    if (
      nome.endsWith('.jpg') ||
      nome.endsWith('.jpeg')
    ) {

      return 'image/jpeg';
    }

    if (
      nome.endsWith('.gif')
    ) {

      return 'image/gif';
    }

    if (
      nome.endsWith('.webp')
    ) {

      return 'image/webp';
    }

    return blob.type ||
      'application/octet-stream';
  }


  // ============================================================
  // FECHAR PREVIEW
  // ============================================================

  fecharPreviewDocumento(): void {

    this.documentoPreviewAberto.set(
      false
    );

    this.limparPreviewDocumento();

    this.documentoPreviewTipo.set(
      ''
    );

    this.documentoPreviewNome.set(
      ''
    );

    this.abrindoDocumento.set(
      false
    );
  }


  // ============================================================
  // LIMPAR PREVIEW
  // ============================================================

  private limparPreviewDocumento(): void {

    if (
      this.documentoPreviewBlobUrl
    ) {

      URL.revokeObjectURL(
        this.documentoPreviewBlobUrl
      );

      this.documentoPreviewBlobUrl =
        null;
    }

    this.documentoPreviewUrl.set(
      null
    );

    this.documentoPreviewPdfUrl.set(
      null
    );
  }


  // ============================================================
  // LOGOUT
  // ============================================================

  sair(): void {

    this.authService
      .logout()
      .subscribe({

        next: () => {

          this.router.navigate([
            '/acesso'
          ]);
        },

        error: (erro: unknown) => {

          console.error(
            'Erro ao encerrar sessão:',
            erro
          );

          this.router.navigate([
            '/acesso'
          ]);
        }

      });
  }


  // ============================================================
  // AUXILIAR — BROWSER
  // ============================================================

  private noBrowser(): boolean {

    return isPlatformBrowser(
      this.platformId
    );
  }

}

