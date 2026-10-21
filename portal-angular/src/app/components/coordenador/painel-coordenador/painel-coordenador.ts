import { CommonModule } from '@angular/common';

import {
  Component,
  OnInit,
  inject,
  signal
} from '@angular/core';

import {
  DomSanitizer,
  SafeResourceUrl
} from '@angular/platform-browser';

import { FormsModule } from '@angular/forms';

import { Router } from '@angular/router';

import {
  SupervisorService,
  CriarSupervisor,
  SupervisorListado
} from '../../../services/supervisor';

import {
  CoordenadorService,
  CandidaturaCoordenador,
  DashboardCoordenador
} from '../../../services/coordenador.service';

import {
  EstagioCoordenadorService,
  EstagioCoordenado,
  SupervisorVinculo
} from '../../../services/estagio-coordenador.service';

import { AuthService } from '../../../core/auth.service';

import {
  LayoutPainelComponent,
  GrupoNavegacaoPainel
} from '../../shared/layout-painel/layout-painel';

import {
  EstagioCoordenadorComponent
} from '../../shared/estagio-coordenador/estagio-coordenador';


// ============================================================
// SECÇÕES DO PAINEL (MENU DE BOTÕES)
// ============================================================

type SecaoCoordenador =
  | 'candidaturas'
  | 'estagios'
  | 'supervisores';

interface SecaoDefinicao {

  id: SecaoCoordenador;

  rotulo: string;

  /** Glifo mostrado no botão do menu. */
  icone: string;

}

const SECOES: SecaoDefinicao[] = [

  {
    id: 'candidaturas',
    rotulo: 'Candidaturas',
    icone: '✉'
  },

  {
    id: 'estagios',
    rotulo: 'Estágios',
    icone: '★'
  },

  {
    id: 'supervisores',
    rotulo: 'Supervisores',
    icone: '＋'
  }

];


/**
 * Grupos da navegação lateral, derivados das secções do painel.
 */
const GRUPOS_NAVEGACAO: GrupoNavegacaoPainel[] = [
  {
    titulo: 'GESTÃO DA DIREÇÃO',
    itens: SECOES.map((secao) => ({
      id: secao.id,
      rotulo: secao.rotulo,
      icone: secao.icone
    }))
  }
];


@Component({
  selector: 'app-painel-coordenador',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    LayoutPainelComponent,
    EstagioCoordenadorComponent
  ],
  templateUrl: './painel-coordenador.html',
  styleUrls: ['./painel-coordenador.css']
})
export class PainelCoordenadorComponent implements OnInit {

  private readonly supervisorService =
    inject(SupervisorService);

  private readonly coordenadorService =
    inject(CoordenadorService);

  private readonly estagioService =
    inject(EstagioCoordenadorService);

  private readonly sanitizer =
    inject(DomSanitizer);

  private readonly authService =
    inject(AuthService);

  private readonly router =
    inject(Router);


  // ============================================================
  // MENU DE SECÇÕES
  // ============================================================

  secao = signal<SecaoCoordenador>('candidaturas');

  readonly gruposNavegacao = GRUPOS_NAVEGACAO;


  // ============================================================
  // ESTADO DO PAINEL
  // ============================================================

  guardando = signal(false);

  erro = signal('');

  sucesso = signal('');


  // ============================================================
  // FORMULÁRIO DO SUPERVISOR
  // ============================================================

  nome = '';

  email = '';

  telefone = '';

  password = '';

  passwordConfirmation = '';


  // ============================================================
  // DASHBOARD
  // ============================================================

  dashboard = signal<DashboardCoordenador | null>(null);

  carregandoDashboard = signal(false);

  erroDashboard = signal('');


  // ============================================================
  // CANDIDATURAS
  // ============================================================

  candidaturas = signal<CandidaturaCoordenador[]>([]);

  carregandoCandidaturas = signal(false);

  erroCandidaturas = signal('');

  paginaAtual = signal(1);

  ultimaPagina = signal(1);

  totalCandidaturas = signal(0);

  filtroEstado = signal('');


  // ============================================================
  // DETALHES DA CANDIDATURA
  // ============================================================

  candidaturaSelecionada = signal<CandidaturaCoordenador | null>(null);

  carregandoDetalhes = signal(false);

  erroDetalhes = signal('');


  // ============================================================
  // DECISÃO
  // ============================================================

  processandoDecisao = signal(false);

  erroDecisao = signal('');

  sucessoDecisao = signal('');

  mostrarRecusa = signal(false);

  motivoRecusa = signal('');


  // ============================================================
  // PERFIL
  // ============================================================

  nomeCoordenador = signal('Coordenador');


  // ============================================================
  // CICLO DE VIDA
  // ============================================================

  ngOnInit(): void {
    this.nomeCoordenador.set(
      this.authService.utilizador()?.nome || 'Coordenador'
    );

    this.carregarDashboard();

    this.carregarCandidaturas();

    this.carregarSupervisores();
  }


  alterarSecao(secao: SecaoCoordenador): void {

    this.secao.set(secao);

    this.erro.set('');

    this.sucesso.set('');

    if (secao === 'estagios') {

      this.carregarEstagios();

    }

    if (secao === 'supervisores') {

      this.carregarSupervisores();

    }

  }


  // ============================================================
  // DASHBOARD
  // ============================================================

  carregarDashboard(): void {
    this.carregandoDashboard.set(true);

    this.erroDashboard.set('');

    const sub = this.coordenadorService
      .dashboard()
      .subscribe({
        next: (dados) => {
          this.dashboard.set(dados);

          this.carregandoDashboard.set(false);
        },

        error: (erro: unknown) => {
          console.error('Erro ao carregar dashboard:', erro);

          this.carregandoDashboard.set(false);

          this.erroDashboard.set(
            'Não foi possível carregar o dashboard.'
          );
        }
      });

    // subscriptions cleanup is handled by Angular's destroy
    // for simplicity we rely on unsubscribe on component destroy
  }


  // ============================================================
  // CANDIDATURAS
  // ============================================================

  carregarCandidaturas(): void {
    this.carregandoCandidaturas.set(true);

    this.erroCandidaturas.set('');

    const sub = this.coordenadorService
      .candidaturas(
        '',
        this.filtroEstado(),
        this.paginaAtual()
      )
      .subscribe({
        next: (resposta) => {
          this.candidaturas.set(resposta.data);

          this.paginaAtual.set(resposta.current_page);

          this.ultimaPagina.set(resposta.last_page);

          this.totalCandidaturas.set(resposta.total);

          this.carregandoCandidaturas.set(false);
        },

        error: (erro: unknown) => {
          console.error('Erro ao carregar candidaturas:', erro);

          this.carregandoCandidaturas.set(false);

          this.erroCandidaturas.set(
            'Não foi possível carregar as candidaturas.'
          );
        }
      });
  }


  alterarFiltroEstado(estado: string): void {
    this.filtroEstado.set(estado);

    this.paginaAtual.set(1);

    this.carregarCandidaturas();
  }


  mudarPagina(pagina: number): void {
    if (pagina < 1 || pagina > this.ultimaPagina()) {
      return;
    }

    this.paginaAtual.set(pagina);

    this.carregarCandidaturas();
  }


  // ============================================================
  // DETALHES DA CANDIDATURA
  // ============================================================

  abrirDetalhes(candidatura: CandidaturaCoordenador): void {
    this.carregandoDetalhes.set(true);

    this.erroDetalhes.set('');

    this.erroDecisao.set('');

    this.sucessoDecisao.set('');

    this.mostrarRecusa.set(false);

    this.motivoRecusa.set('');

    const sub = this.coordenadorService
      .obterCandidatura(candidatura.id_candidatura)
      .subscribe({
        next: (dados) => {
          this.candidaturaSelecionada.set(dados);

          this.carregandoDetalhes.set(false);
        },

        error: (erro: unknown) => {
          console.error('Erro ao carregar detalhes:', erro);

          this.carregandoDetalhes.set(false);

          this.erroDetalhes.set(
            'Não foi possível carregar os detalhes da candidatura.'
          );
        }
      });
  }


  fecharDetalhes(): void {
    this.candidaturaSelecionada.set(null);

    this.carregandoDetalhes.set(false);

    this.erroDetalhes.set('');

    this.erroDecisao.set('');

    this.sucessoDecisao.set('');

    this.mostrarRecusa.set(false);

    this.motivoRecusa.set('');
  }


  // ============================================================
  // APROVAR CANDIDATURA
  // ============================================================

  aprovarSelecionada(): void {
    const candidatura = this.candidaturaSelecionada();

    if (!candidatura) {
      return;
    }

    if (candidatura.estado !== 'em_analise') {
      this.erroDecisao.set(
        'Esta candidatura já não está em análise.'
      );

      return;
    }

    this.processandoDecisao.set(true);

    this.erroDecisao.set('');

    this.sucessoDecisao.set('');

    const sub = this.coordenadorService
      .aprovarCandidatura(candidatura.id_candidatura)
      .subscribe({
        next: (resposta) => {
          this.processandoDecisao.set(false);

          this.sucessoDecisao.set(resposta.message);

          this.candidaturaSelecionada.set(resposta.candidatura);

          this.carregarDashboard();

          this.carregarCandidaturas();
        },

        error: (erro: unknown) => {
          console.error('Erro ao aprovar candidatura:', erro);

          this.processandoDecisao.set(false);

          this.erroDecisao.set(
            'Não foi possível aprovar a candidatura.'
          );
        }
      });
  }


  // ============================================================
  // RECUSAR CANDIDATURA
  // ============================================================

  abrirRecusa(): void {
    this.erroDecisao.set('');

    this.sucessoDecisao.set('');

    this.motivoRecusa.set('');

    this.mostrarRecusa.set(true);
  }


  cancelarRecusa(): void {
    this.mostrarRecusa.set(false);

    this.motivoRecusa.set('');

    this.erroDecisao.set('');
  }


  alterarMotivoRecusa(event: Event): void {
    const textarea = event.target as HTMLTextAreaElement;

    this.motivoRecusa.set(textarea.value);
  }


  recusarSelecionada(): void {
    const candidatura = this.candidaturaSelecionada();

    if (!candidatura) {
      return;
    }

    if (candidatura.estado !== 'em_analise') {
      this.erroDecisao.set(
        'Esta candidatura já não está em análise.'
      );

      return;
    }

    const motivo = this.motivoRecusa().trim();

    if (motivo.length < 5) {
      this.erroDecisao.set(
        'O motivo da recusa deve ter pelo menos 5 caracteres.'
      );

      return;
    }

    if (motivo.length > 1000) {
      this.erroDecisao.set(
        'O motivo da recusa não pode ultrapassar 1000 caracteres.'
      );

      return;
    }

    this.processandoDecisao.set(true);

    this.erroDecisao.set('');

    this.sucessoDecisao.set('');

    const sub = this.coordenadorService
      .recusarCandidatura(
        candidatura.id_candidatura,
        motivo
      )
      .subscribe({
        next: (resposta) => {
          this.processandoDecisao.set(false);

          this.mostrarRecusa.set(false);

          this.sucessoDecisao.set(resposta.message);

          this.candidaturaSelecionada.set(resposta.candidatura);

          this.carregarDashboard();

          this.carregarCandidaturas();
        },

        error: (erro: unknown) => {
          console.error('Erro ao recusar candidatura:', erro);

          this.processandoDecisao.set(false);

          this.erroDecisao.set(
            'Não foi possível recusar a candidatura.'
          );
        }
      });
  }


  // ============================================================
  // CRIAR SUPERVISOR
  // ============================================================

  criarSupervisor(): void {

    this.erro.set('');

    this.sucesso.set('');


    // Validar os campos obrigatórios.
    if (
      !this.nome.trim() ||
      !this.email.trim() ||
      !this.password ||
      !this.passwordConfirmation
    ) {

      this.erro.set(
        'Preenche todos os campos obrigatórios.'
      );

      return;
    }


    // Validar o formato do e-mail.
    const emailValido =
      /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
        this.email.trim()
      );

    if (!emailValido) {

      this.erro.set(
        'Introduz um endereço de e-mail válido.'
      );

      return;
    }


    // Validar a palavra-passe.
    if (
      this.password.length < 8 ||
      !/[A-Za-z]/.test(this.password) ||
      !/[0-9]/.test(this.password)
    ) {

      this.erro.set(
        'A palavra-passe deve ter pelo menos 8 caracteres e conter letras e números.'
      );

      return;
    }


    // Confirmar a palavra-passe.
    if (
      this.password !== this.passwordConfirmation
    ) {

      this.erro.set(
        'As palavras-passe não coincidem.'
      );

      return;
    }


    this.guardando.set(true);


    const dados: CriarSupervisor = {

      nome: this.nome.trim(),

      email: this.email.trim(),

      password: this.password,

      password_confirmation: this.passwordConfirmation,

      telefone: this.telefone.trim() || undefined

    };


    const sub = this.supervisorService
      .criar(dados)
      .subscribe({
        next: (resposta) => {
          this.guardando.set(false);

          this.sucesso.set(
            resposta.message ||
            'Conta de supervisor criada com sucesso.'
          );

          this.limparFormulario();
        },

        error: (erro: unknown) => {
          console.error('Erro ao criar supervisor:', erro);

          this.guardando.set(false);

          const mensagem =
            (erro as { error?: { message?: string } })
              ?.error?.message;

          this.erro.set(
            mensagem ||
            'Não foi possível criar a conta de supervisor.'
          );
        }
      });
  }


  limparFormulario(): void {
    this.nome = '';

    this.email = '';

    this.telefone = '';

    this.password = '';

    this.passwordConfirmation = '';

    this.erro.set('');

    this.sucesso.set('');
  }


  // ============================================================
  // VISUALIZAÇÃO DE DOCUMENTOS
  // ============================================================

  abrindoDocumento = signal(false);

  erroDocumento = signal('');

  documentoPreviewAberto = signal(false);

  documentoPreviewUrl = signal<string | null>(null);

  documentoPreviewPdfUrl = signal<SafeResourceUrl | null>(null);

  documentoPreviewTipo = signal('');

  documentoPreviewNome = signal('');

  private documentoPreviewBlobUrl: string | null = null;


  visualizarDocumento(documento: {
    id_documento: number;
    nome_arquivo: string;
    tipo_documento: string;
  }): void {

    if (this.abrindoDocumento()) {
      return;
    }

    this.abrindoDocumento.set(true);

    this.erroDocumento.set('');

    this.limparPreviewDocumento();

    this.coordenadorService
      .visualizarDocumento(documento.id_documento)
      .subscribe({
        next: (blob: Blob) => {

          const tipo = this.inferirMimeType(documento, blob);

          const url = URL.createObjectURL(blob);

          this.documentoPreviewBlobUrl = url;

          this.documentoPreviewTipo.set(tipo);

          this.documentoPreviewNome.set(documento.nome_arquivo);

          if (tipo === 'application/pdf') {

            this.documentoPreviewPdfUrl.set(
              this.sanitizer.bypassSecurityTrustResourceUrl(url)
            );

            this.documentoPreviewUrl.set(null);

          } else {

            this.documentoPreviewUrl.set(url);

            this.documentoPreviewPdfUrl.set(null);

          }

          this.documentoPreviewAberto.set(true);

          this.abrindoDocumento.set(false);

        },

        error: (erro: unknown) => {

          console.error('Erro ao visualizar documento:', erro);

          this.abrindoDocumento.set(false);

          this.erroDocumento.set(
            'Não foi possível abrir o documento.'
          );

        }
      });
  }


  fecharPreviewDocumento(): void {

    this.limparPreviewDocumento();

    this.documentoPreviewAberto.set(false);

  }


  private limparPreviewDocumento(): void {

    if (this.documentoPreviewBlobUrl) {

      URL.revokeObjectURL(this.documentoPreviewBlobUrl);

      this.documentoPreviewBlobUrl = null;

    }

    this.documentoPreviewUrl.set(null);

    this.documentoPreviewPdfUrl.set(null);

    this.documentoPreviewTipo.set('');

    this.documentoPreviewNome.set('');

  }


  private inferirMimeType(
    documento: { nome_arquivo: string; tipo_documento: string },
    blob: Blob
  ): string {

    if (blob.type) {
      return blob.type;
    }

    const extensao = documento.nome_arquivo
      .split('.')
      .pop()
      ?.toLowerCase();

    switch (extensao) {

      case 'pdf':
        return 'application/pdf';

      case 'png':
        return 'image/png';

      case 'jpg':
      case 'jpeg':
        return 'image/jpeg';

      default:
        return 'application/octet-stream';

    }

  }


  // ============================================================
  // SUPERVISORES DA DIREÇÃO
  // ============================================================

  supervisores = signal<SupervisorListado[]>([]);

  carregandoSupervisores = signal(false);

  erroSupervisores = signal('');


  carregarSupervisores(): void {

    this.carregandoSupervisores.set(true);

    this.erroSupervisores.set('');

    this.supervisorService.listar().subscribe({

      next: (resposta) => {

        this.supervisores.set(resposta.supervisores ?? []);

        this.carregandoSupervisores.set(false);

      },

      error: (erro: unknown) => {

        console.error('Erro ao carregar supervisores:', erro);

        this.carregandoSupervisores.set(false);

        this.erroSupervisores.set(
          'Não foi possível carregar os supervisores.'
        );

      }

    });
  }


  // ============================================================
  // ESTÁGIOS E ATRIBUIÇÃO DE SUPERVISORES
  // ============================================================

  estagios = signal<EstagioCoordenado[]>([]);

  carregandoEstagios = signal(false);

  erroEstagios = signal('');

  supervisorSelecionado = signal<Record<number, number | null>>({});

  processandoAtribuicao = signal<number | null>(null);


  carregarEstagios(): void {

    this.carregandoEstagios.set(true);

    this.erroEstagios.set('');

    this.estagioService.estagios().subscribe({

      next: (resposta) => {

        this.estagios.set(resposta.data ?? []);

        this.carregandoEstagios.set(false);

      },

      error: (erro: unknown) => {

        console.error('Erro ao carregar estágios:', erro);

        this.carregandoEstagios.set(false);

        this.erroEstagios.set(
          'Não foi possível carregar os estágios.'
        );

      }

    });
  }


  alterarSupervisorSelecionado(
    idEstagio: number,
    valor: unknown
  ): void {

    const id = valor === '' || valor === null
      ? null
      : Number(valor);

    this.supervisorSelecionado.update((mapa) => ({
      ...mapa,
      [idEstagio]: id
    }));

  }


  atribuirSupervisor(estagio: EstagioCoordenado): void {

    const idSupervisor =
      this.supervisorSelecionado()[estagio.id_estagio];

    if (!idSupervisor) {

      this.erro.set('Seleciona o supervisor a atribuir.');

      return;

    }

    this.erro.set('');

    this.sucesso.set('');

    this.processandoAtribuicao.set(estagio.id_estagio);

    this.estagioService
      .atribuirSupervisor(estagio.id_estagio, idSupervisor)
      .subscribe({

        next: (resposta) => {

          this.processandoAtribuicao.set(null);

          this.sucesso.set(
            resposta.message || 'Supervisor atribuído.'
          );

          this.alterarSupervisorSelecionado(
            estagio.id_estagio,
            ''
          );

          this.carregarEstagios();

        },

        error: (erro: unknown) => {

          console.error('Erro ao atribuir supervisor:', erro);

          this.processandoAtribuicao.set(null);

          this.erro.set(
            (erro as { error?: { message?: string } })
              ?.error?.message ||
            'Não foi possível atribuir o supervisor.'
          );

        }

      });
  }


  removerSupervisor(
    estagio: EstagioCoordenado,
    vinculo: SupervisorVinculo
  ): void {

    this.erro.set('');

    this.sucesso.set('');

    this.processandoAtribuicao.set(estagio.id_estagio);

    this.estagioService
      .removerSupervisor(estagio.id_estagio, vinculo.id_supervisor)
      .subscribe({

        next: (resposta) => {

          this.processandoAtribuicao.set(null);

          this.sucesso.set(
            resposta.message || 'Supervisor removido.'
          );

          this.carregarEstagios();

        },

        error: (erro: unknown) => {

          console.error('Erro ao remover supervisor:', erro);

          this.processandoAtribuicao.set(null);

          this.erro.set(
            (erro as { error?: { message?: string } })
              ?.error?.message ||
            'Não foi possível remover o supervisor.'
          );

        }

      });
  }


  nomeEstagiario(estagio: EstagioCoordenado): string {

    return estagio.estagiario?.nome
      || estagio.candidatura?.nome
      || `Estágio #${estagio.id_estagio}`;

  }


  rotuloEstadoEstagio(estado: string | null): string {

    switch (estado) {

      case 'ativo':
        return 'Ativo';

      case 'suspenso':
        return 'Suspenso';

      case 'concluido':
        return 'Concluído';

      case 'cancelado':
        return 'Cancelado';

      default:
        return estado || '—';

    }

  }


  supervisoresDoEstagio(estagio: EstagioCoordenado): SupervisorVinculo[] {

    return estagio.supervisores_ativos ?? [];

  }


  sair(): void {
    this.authService.logout();

    this.router.navigate(['/telainicial']);
  }

}
