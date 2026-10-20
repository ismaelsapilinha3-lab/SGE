import { CommonModule, isPlatformBrowser } from '@angular/common';

import {
  Component,
  OnDestroy,
  OnInit,
  PLATFORM_ID,
  computed,
  inject,
  signal
} from '@angular/core';

import {
  DomSanitizer,
  SafeResourceUrl
} from '@angular/platform-browser';

import { Router } from '@angular/router';

import { Subscription } from 'rxjs';

import {
  AdminService,
  DashboardAdmin,
  Candidatura,
  CandidaturasResponse,
  Departamento,
  DepartamentoDados,
  Documento,
  Estagiario,
  EstagiariosResponse
} from '../../../services/admin.service';

import { AuthService } from '../../../core/auth.service';

import {
  LayoutPainelComponent,
  GrupoNavegacaoPainel
} from '../../shared/layout-painel/layout-painel';

import {
  EstagioAdminComponent
} from '../../shared/estagio-admin/estagio-admin';


type SecaoAdmin =
  | 'dashboard'
  | 'candidaturas'
  | 'departamentos'
  | 'estagiarios'
  | 'estagios'
  | 'assiduidade'
  | 'utilizadores';


type EstadoEstagio =
  | 'ativo'
  | 'suspenso'
  | 'concluido'
  | 'cancelado';


@Component({
  selector: 'app-painel-admin',
  standalone: true,

  imports: [
    CommonModule,
    LayoutPainelComponent,
    EstagioAdminComponent
  ],

  templateUrl: './painel-admin.html',
  styleUrl: './painel-admin.css'
})
export class PainelAdmin implements OnInit, OnDestroy {

  // ============================================================
  // SECÇÃO ATIVA
  // ============================================================

  secaoAtiva = signal<SecaoAdmin>('dashboard');

  readonly gruposNavegacao: GrupoNavegacaoPainel[] = [
    {
      titulo: 'GESTÃO',
      itens: [
        {
          id: 'dashboard',
          rotulo: 'Dashboard',
          icone: '▦'
        },
        {
          id: 'candidaturas',
          rotulo: 'Candidaturas',
          icone: '▤'
        },
        {
          id: 'departamentos',
          rotulo: 'Departamentos',
          icone: '⌂'
        },
        {
          id: 'estagiarios',
          rotulo: 'Estagiários',
          icone: '♙'
        },
        {
          id: 'estagios',
          rotulo: 'Estágios',
          icone: '▣'
        },
        {
          id: 'assiduidade',
          rotulo: 'Assiduidade',
          icone: '◷',
          desativado: true,
          nota: 'Em breve'
        }
      ]
    },
    {
      titulo: 'SISTEMA',
      itens: [
        {
          id: 'utilizadores',
          rotulo: 'Utilizadores',
          icone: '♙'
        }
      ]
    }
  ];


  // ============================================================
  // SERVIÇOS
  // ============================================================

  private readonly adminService = inject(AdminService);

  private readonly authService = inject(AuthService);

  private readonly router = inject(Router);

  private readonly sanitizer = inject(DomSanitizer);

  private readonly platformId = inject(PLATFORM_ID);

  private readonly subscriptions = new Subscription();


  // ============================================================
  // DASHBOARD
  // ============================================================

  dashboard = signal<DashboardAdmin>({
    total_candidaturas: 0,
    em_analise: 0,
    aprovadas: 0,
    recusadas: 0
  });


  // ============================================================
  // CANDIDATURAS
  // ============================================================

  candidaturas = signal<Candidatura[]>([]);

  candidaturaSelecionada = signal<Candidatura | null>(null);


  // ============================================================
  // ESTAGIÁRIOS
  // ============================================================

  estagiarios = signal<Estagiario[]>([]);

  pesquisaEstagiarios = signal('');

  estadoEstagiarios = signal('');

  carregandoEstagiarios = signal(false);

  erroEstagiarios = signal('');


  // ============================================================
  // DEPARTAMENTOS
  // ============================================================

  departamentos = signal<Departamento[]>([]);

  mostrarFormularioDepartamento = signal(false);

  departamentoEmEdicao = signal<Departamento | null>(null);

  nomeDepartamento = signal('');

  descricaoDepartamento = signal('');

  ativoDepartamento = signal(true);

  guardandoDepartamento = signal(false);

  erroFormularioDepartamento = signal('');

  sucessoDepartamento = signal('');


  // ============================================================
  // PESQUISA E FILTROS
  // ============================================================

  pesquisa = signal('');

  estadoSelecionado = signal('');

  departamentoSelecionado = signal<number | null>(null);


  // ============================================================
  // PAGINAÇÃO
  // ============================================================

  paginaAtual = signal(1);

  ultimaPagina = signal(1);

  totalCandidaturas = signal(0);

  porPagina = signal(15);


  // ============================================================
  // CARREGAMENTOS
  // ============================================================

  carregandoDashboard = signal(false);

  carregandoCandidaturas = signal(false);

  carregandoDepartamentos = signal(false);

  carregandoDetalhes = signal(false);

  abrindoDocumento = signal(false);


  // ============================================================
  // ERROS
  // ============================================================

  erroDashboard = signal('');

  erroCandidaturas = signal('');

  erroDepartamentos = signal('');

  erroDetalhes = signal('');

  erroDocumento = signal('');


  // ============================================================
  // ENCAMINHAMENTO
  // ============================================================

  processandoEncaminhamento = signal(false);

  erroEncaminhamento = signal('');

  sucessoEncaminhamento = signal('');

  mostrarEncaminhamento = signal(false);

  departamentoEncaminhamento = signal<number | null>(null);


  // ============================================================
  // PREVIEW DE DOCUMENTO
  // ============================================================

  documentoPreviewAberto = signal(false);

  documentoPreviewUrl = signal<string | null>(null);

  documentoPreviewPdfUrl = signal<SafeResourceUrl | null>(null);

  documentoPreviewTipo = signal('');

  documentoPreviewNome = signal('');

  private documentoPreviewBlobUrl: string | null = null;


  // ============================================================
  // GESTÃO DO ESTÁGIO
  // ============================================================

  estagioEmGestao = signal<Estagiario | null>(null);

  estadoEstagioFormulario = signal<EstadoEstagio>('ativo');

  inicioEstagioFormulario = signal('');

  fimEstagioFormulario = signal('');

  motivoSuspensaoFormulario = signal('');

  aGuardarEstadoEstagio = signal(false);

  erroGestaoEstagio = signal('');

  sucessoGestaoEstagio = signal('');

  duracaoMesesFormulario = signal(2);

  // ============================================================
  // GRÁFICOS DE ACOMPANHAMENTO
  // ============================================================

  /** Percentagem de candidaturas em análise. */
  readonly percentEmAnalise = computed(() =>
    this.calcularPercentagem(this.dashboard().em_analise)
  );

  /** Percentagem de candidaturas aprovadas. */
  readonly percentAprovadas = computed(() =>
    this.calcularPercentagem(this.dashboard().aprovadas)
  );

  /** Percentagem de candidaturas recusadas. */
  readonly percentRecusadas = computed(() =>
    this.calcularPercentagem(this.dashboard().recusadas)
  );

  /** Total de candidaturas já decididas (aprovadas + recusadas). */
  private readonly candidaturasDecididas = computed(
    () => (this.dashboard().aprovadas || 0) + (this.dashboard().recusadas || 0)
  );

  /** Taxa de aprovação sobre as candidaturas decididas. */
  readonly taxaAprovacao = computed(() => {
    const decididas = this.candidaturasDecididas();

    if (decididas === 0) {
      return 0;
    }

    return Math.round(((this.dashboard().aprovadas || 0) / decididas) * 100);
  });

  /** Taxa de recusa sobre as candidaturas decididas. */
  readonly taxaRejeicao = computed(() => {
    const decididas = this.candidaturasDecididas();

    if (decididas === 0) {
      return 0;
    }

    return Math.round(((this.dashboard().recusadas || 0) / decididas) * 100);
  });

  /**
   * Converte um valor absoluto numa percentagem do total de
   * candidaturas, arredondada e protegida contra divisão por zero.
   */
  private calcularPercentagem(valor: number): number {
    const total = this.dashboard().total_candidaturas || 0;

    if (total === 0) {
      return 0;
    }

    return Math.round(((valor || 0) / total) * 100);
  }


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

    const sub = this.adminService.dashboard().subscribe({
      next: (dados) => {
        this.dashboard.set(dados);

        this.carregandoDashboard.set(false);
      },

      error: (erro: unknown) => {
        console.error('Erro ao carregar dashboard:', erro);

        this.carregandoDashboard.set(false);

        this.erroDashboard.set(
          'Não foi possível carregar os indicadores.'
        );
      }
    });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // CARREGAR DEPARTAMENTOS
  // ============================================================

  carregarDepartamentos(): void {
    this.carregandoDepartamentos.set(true);

    this.erroDepartamentos.set('');

    const sub = this.adminService.listarDepartamentos().subscribe({
      next: (dados) => {
        const ordenados = [...(dados ?? [])].sort((a, b) => {
          const totalA = Number(a.estagiarios_ativos ?? 0);

          const totalB = Number(b.estagiarios_ativos ?? 0);

          return totalB - totalA ||
            a.nome.localeCompare(b.nome, 'pt');
        });

        this.departamentos.set(ordenados);

        this.carregandoDepartamentos.set(false);
      },

      error: (erro: unknown) => {
        console.error('Erro ao carregar departamentos:', erro);

        this.departamentos.set([]);

        this.carregandoDepartamentos.set(false);

        this.erroDepartamentos.set(
          'Não foi possível carregar os departamentos.'
        );
      }
    });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // NOVO DEPARTAMENTO
  // ============================================================

  novoDepartamento(): void {
    this.departamentoEmEdicao.set(null);

    this.nomeDepartamento.set('');

    this.descricaoDepartamento.set('');

    this.ativoDepartamento.set(true);

    this.erroFormularioDepartamento.set('');

    this.sucessoDepartamento.set('');

    this.mostrarFormularioDepartamento.set(true);
  }


  // ============================================================
  // EDITAR DEPARTAMENTO
  // ============================================================

  editarDepartamento(departamento: Departamento): void {
    this.departamentoEmEdicao.set(departamento);

    this.nomeDepartamento.set(departamento.nome);

    this.descricaoDepartamento.set(
      departamento.descricao ?? ''
    );

    this.ativoDepartamento.set(
      departamento.ativo ?? true
    );

    this.erroFormularioDepartamento.set('');

    this.sucessoDepartamento.set('');

    this.mostrarFormularioDepartamento.set(true);
  }


  // ============================================================
  // ALTERAR NOME DO DEPARTAMENTO
  // ============================================================

  alterarNomeDepartamento(event: Event): void {
    const input = event.target as HTMLInputElement;

    this.nomeDepartamento.set(input.value);
  }


  // ============================================================
  // ALTERAR DESCRIÇÃO DO DEPARTAMENTO
  // ============================================================

  alterarDescricaoDepartamento(event: Event): void {
    const textarea = event.target as HTMLTextAreaElement;

    this.descricaoDepartamento.set(textarea.value);
  }


  // ============================================================
  // ALTERAR ESTADO DO DEPARTAMENTO
  // ============================================================

  alterarAtivoDepartamento(event: Event): void {
    const input = event.target as HTMLInputElement;

    this.ativoDepartamento.set(input.checked);
  }


  // ============================================================
  // CANCELAR FORMULÁRIO DE DEPARTAMENTO
  // ============================================================

  cancelarFormularioDepartamento(): void {
    this.mostrarFormularioDepartamento.set(false);

    this.departamentoEmEdicao.set(null);

    this.nomeDepartamento.set('');

    this.descricaoDepartamento.set('');

    this.ativoDepartamento.set(true);

    this.erroFormularioDepartamento.set('');
  }


  // ============================================================
  // GUARDAR DEPARTAMENTO
  // ============================================================

  guardarDepartamento(): void {
    if (this.guardandoDepartamento()) {
      return;
    }

    const nome = this.nomeDepartamento().trim();

    const descricao = this.descricaoDepartamento().trim();

    this.erroFormularioDepartamento.set('');

    this.sucessoDepartamento.set('');

    if (!nome) {
      this.erroFormularioDepartamento.set(
        'Introduz o nome do departamento.'
      );

      return;
    }

    if (nome.length > 255) {
      this.erroFormularioDepartamento.set(
        'O nome não pode ultrapassar 255 caracteres.'
      );

      return;
    }

    const dados: DepartamentoDados = {
      nome,
      descricao: descricao || null
    };

    const departamento = this.departamentoEmEdicao();

    this.guardandoDepartamento.set(true);

    if (departamento) {
      dados.ativo = this.ativoDepartamento();

      const sub = this.adminService
        .atualizarDepartamento(
          departamento.id_departamento,
          dados
        )
        .subscribe({
          next: (resposta) => {
            this.guardandoDepartamento.set(false);

            this.mostrarFormularioDepartamento.set(false);

            this.departamentoEmEdicao.set(null);

            this.sucessoDepartamento.set(
              resposta.mensagem ||
              'Departamento atualizado com sucesso.'
            );

            this.carregarDepartamentos();
          },

          error: (erro: unknown) => {
            console.error(
              'Erro ao editar departamento:',
              erro
            );

            this.guardandoDepartamento.set(false);

            this.erroFormularioDepartamento.set(
              this.mensagemErroDepartamento(
                erro,
                'Não foi possível atualizar o departamento.'
              )
            );
          }
        });

      this.subscriptions.add(sub);

      return;
    }

    const sub = this.adminService.criarDepartamento(dados).subscribe({
      next: (resposta) => {
        this.guardandoDepartamento.set(false);

        this.mostrarFormularioDepartamento.set(false);

        this.departamentoEmEdicao.set(null);

        this.sucessoDepartamento.set(
          resposta.mensagem ||
          'Departamento criado com sucesso.'
        );

        this.carregarDepartamentos();
      },

      error: (erro: unknown) => {
        console.error('Erro ao criar departamento:', erro);

        this.guardandoDepartamento.set(false);

        this.erroFormularioDepartamento.set(
          this.mensagemErroDepartamento(
            erro,
            'Não foi possível criar o departamento.'
          )
        );
      }
    });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // MENSAGEM DE ERRO DO LARAVEL
  // ============================================================

  private mensagemErroDepartamento(
    erro: unknown,
    mensagemPadrao: string
  ): string {
    if (
      typeof erro !== 'object' ||
      erro === null ||
      !('error' in erro)
    ) {
      return mensagemPadrao;
    }

    const resposta = (
      erro as {
        error?: {
          message?: string;
          errors?: Record<string, string[]>;
        };
      }
    ).error;

    const errosValidacao = resposta?.errors;

    if (errosValidacao) {
      const primeiroErro = Object.values(errosValidacao)
        .flat()
        .find((mensagem) => typeof mensagem === 'string');

      if (primeiroErro) {
        return primeiroErro;
      }
    }

    return resposta?.message || mensagemPadrao;
  }


  // ============================================================
  // CARREGAR CANDIDATURAS
  // ============================================================

  carregarCandidaturas(): void {
    this.carregandoCandidaturas.set(true);

    this.erroCandidaturas.set('');

    const sub = this.adminService.listarCandidaturas(
      this.pesquisa(),
      this.estadoSelecionado(),
      this.departamentoSelecionado() ?? undefined,
      this.paginaAtual(),
      this.porPagina()
    ).subscribe({
      next: (resposta: CandidaturasResponse) => {
        this.candidaturas.set(resposta.data ?? []);

        this.totalCandidaturas.set(resposta.total ?? 0);

        this.ultimaPagina.set(
          Math.max(resposta.last_page ?? 1, 1)
        );

        this.paginaAtual.set(
          resposta.current_page ?? 1
        );

        this.carregandoCandidaturas.set(false);
      },

      error: (erro: unknown) => {
        console.error('Erro ao carregar candidaturas:', erro);

        this.candidaturas.set([]);

        this.totalCandidaturas.set(0);

        this.ultimaPagina.set(1);

        this.carregandoCandidaturas.set(false);

        this.erroCandidaturas.set(
          'Não foi possível carregar as candidaturas.'
        );
      }
    });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // CARREGAR ESTAGIÁRIOS
  // ============================================================

  carregarEstagiarios(): void {
    this.carregandoEstagiarios.set(true);

    this.erroEstagiarios.set('');

    const sub = this.adminService.listarEstagiarios(
      this.pesquisaEstagiarios(),
      this.estadoEstagiarios()
    ).subscribe({
      next: (resposta: EstagiariosResponse) => {
        this.estagiarios.set(resposta.data ?? []);

        this.carregandoEstagiarios.set(false);
      },

      error: (erro: unknown) => {
        console.error('Erro ao carregar estagiários:', erro);

        this.estagiarios.set([]);

        this.erroEstagiarios.set(
          'Não foi possível carregar os estagiários. Confirma a API Laravel.'
        );

        this.carregandoEstagiarios.set(false);
      }
    });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // DIAS RESTANTES DO ESTÁGIO
  // ============================================================

  diasRestantes(estagiario: Estagiario): number | null {
    if (!estagiario.data_fim_prevista) {
      return null;
    }

    const hoje = new Date();

    hoje.setHours(0, 0, 0, 0);

    const fim = new Date(
      estagiario.data_fim_prevista + 'T00:00:00'
    );

    if (Number.isNaN(fim.getTime())) {
      return null;
    }

    return Math.ceil(
      (fim.getTime() - hoje.getTime()) / 86400000
    );
  }


  // ============================================================
  // PESQUISAR ESTAGIÁRIOS
  // ============================================================

  pesquisarEstagiarios(): void {
    this.carregarEstagiarios();
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

    if (this.secaoAtiva() === 'estagiarios') {
      this.carregarEstagiarios();
    }
  }


  // ============================================================
  // PESQUISAR CANDIDATURAS
  // ============================================================

  pesquisar(): void {
    this.paginaAtual.set(1);

    this.carregarCandidaturas();
  }


  // ============================================================
  // ALTERAR ESTADO DA CANDIDATURA
  // ============================================================

  alterarEstado(event: Event): void {
    const select = event.target as HTMLSelectElement;

    this.estadoSelecionado.set(select.value);

    this.paginaAtual.set(1);

    this.carregarCandidaturas();
  }


  // ============================================================
  // ALTERAR DEPARTAMENTO DO FILTRO
  // ============================================================

  alterarDepartamento(event: Event): void {
    const select = event.target as HTMLSelectElement;

    const valor = select.value;

    this.departamentoSelecionado.set(
      valor ? Number(valor) : null
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

    this.departamentoSelecionado.set(null);

    this.paginaAtual.set(1);

    this.carregarCandidaturas();
  }


  // ============================================================
  // NOME DO ESTADO
  // ============================================================

  nomeEstado(estado: Candidatura['estado']): string {
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

  classeEstado(estado: Candidatura['estado']): string {
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

  iniciais(nome: string): string {
    if (!nome?.trim()) {
      return 'U';
    }

    const partes = nome.trim().split(/\s+/).filter(Boolean);

    if (partes.length === 1) {
      return partes[0].substring(0, 2).toUpperCase();
    }

    return (
      partes[0][0] +
      partes[partes.length - 1][0]
    ).toUpperCase();
  }


  // ============================================================
  // PAGINAÇÃO
  // ============================================================

  paginaAnterior(): void {
    const pagina = this.paginaAtual();

    if (pagina <= 1) {
      return;
    }

    this.paginaAtual.set(pagina - 1);

    this.carregarCandidaturas();
  }


  proximaPagina(): void {
    const pagina = this.paginaAtual();

    if (pagina >= this.ultimaPagina()) {
      return;
    }

    this.paginaAtual.set(pagina + 1);

    this.carregarCandidaturas();
  }


  irParaPagina(pagina: number): void {
    if (
      pagina < 1 ||
      pagina > this.ultimaPagina() ||
      pagina === this.paginaAtual()
    ) {
      return;
    }

    this.paginaAtual.set(pagina);

    this.carregarCandidaturas();
  }


  paginasVisiveis(): number[] {
    const total = this.ultimaPagina();

    const atual = this.paginaAtual();

    const paginas: number[] = [];

    const inicio = Math.max(1, atual - 2);

    const fim = Math.min(total, atual + 2);

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

  visualizarCandidatura(candidatura: Candidatura): void {
    this.abrirDetalhes(candidatura);
  }


  // ============================================================
  // ABRIR DETALHES
  // ============================================================

  abrirDetalhes(candidatura: Candidatura | number): void {
    const id = typeof candidatura === 'number'
      ? candidatura
      : candidatura.id_candidatura;

    this.carregandoDetalhes.set(true);

    this.erroDetalhes.set('');

    this.erroDocumento.set('');

    const sub = this.adminService.obterCandidatura(id).subscribe({
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

    this.subscriptions.add(sub);
  }


  // ============================================================
  // FECHAR DETALHES
  // ============================================================

  fecharDetalhes(): void {
    this.candidaturaSelecionada.set(null);

    this.carregandoDetalhes.set(false);

    this.erroDetalhes.set('');

    this.erroDocumento.set('');

    this.mostrarEncaminhamento.set(false);

    this.erroEncaminhamento.set('');

    this.sucessoEncaminhamento.set('');

    this.departamentoEncaminhamento.set(null);
  }


  // ============================================================
  // ENCAMINHAR CANDIDATURA
  // ============================================================

  abrirEncaminhamento(): void {
    this.erroEncaminhamento.set('');

    this.sucessoEncaminhamento.set('');

    this.departamentoEncaminhamento.set(null);

    this.mostrarEncaminhamento.set(true);
  }


  cancelarEncaminhamento(): void {
    this.mostrarEncaminhamento.set(false);

    this.erroEncaminhamento.set('');

    this.departamentoEncaminhamento.set(null);
  }


  alterarDepartamentoEncaminhamento(event: Event): void {
    const select = event.target as HTMLSelectElement;

    const valor = select.value;

    this.departamentoEncaminhamento.set(
      valor ? Number(valor) : null
    );
  }


  confirmarEncaminhamento(): void {
    const candidatura = this.candidaturaSelecionada();

    if (!candidatura) {
      return;
    }

    if (candidatura.estado !== 'em_analise') {
      this.erroEncaminhamento.set(
        'Só é possível encaminhar candidaturas em análise.'
      );

      return;
    }

    if (candidatura.encaminhada_em) {
      this.erroEncaminhamento.set(
        'Esta candidatura já foi encaminhada.'
      );

      return;
    }

    const idDepartamento = this.departamentoEncaminhamento();

    if (!idDepartamento) {
      this.erroEncaminhamento.set(
        'Selecciona a direção para encaminhar a candidatura.'
      );

      return;
    }

    this.processandoEncaminhamento.set(true);

    this.erroEncaminhamento.set('');

    this.sucessoEncaminhamento.set('');

    const sub = this.adminService
      .encaminharCandidatura(
        candidatura.id_candidatura,
        { id_departamento: idDepartamento }
      )
      .subscribe({
        next: (resposta) => {
          this.processandoEncaminhamento.set(false);

          this.sucessoEncaminhamento.set(resposta.message);

          this.candidaturaSelecionada.set(resposta.candidatura);

          this.carregarDashboard();

          this.carregarCandidaturas();
        },

        error: (erro: unknown) => {
          console.error('Erro ao encaminhar candidatura:', erro);

          this.processandoEncaminhamento.set(false);

          this.erroEncaminhamento.set(
            'Não foi possível encaminhar a candidatura.'
          );
        }
      });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // VISUALIZAR DOCUMENTO
  // ============================================================

  visualizarDocumento(documento: Documento): void {
    if (this.abrindoDocumento()) {
      return;
    }

    this.abrindoDocumento.set(true);

    this.erroDocumento.set('');

    this.limparPreviewDocumento();

    const sub = this.adminService
      .visualizarDocumento(documento.id_documento)
      .subscribe({
        next: (blob: Blob) => {
          try {
            const tipo = this.inferirMimeType(documento, blob);

            const url = URL.createObjectURL(blob);

            this.documentoPreviewBlobUrl = url;

            this.documentoPreviewTipo.set(tipo);

            this.documentoPreviewNome.set(
              documento.nome_arquivo
            );

            if (tipo === 'application/pdf') {
              this.documentoPreviewPdfUrl.set(
                this.sanitizer
                  .bypassSecurityTrustResourceUrl(url)
              );

              this.documentoPreviewUrl.set(null);
            } else {
              this.documentoPreviewUrl.set(url);

              this.documentoPreviewPdfUrl.set(null);
            }

            this.documentoPreviewAberto.set(true);
          } catch (erro: unknown) {
            console.error(
              'Erro ao preparar documento:',
              erro
            );

            this.erroDocumento.set(
              'Não foi possível preparar o documento para visualização.'
            );
          }

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
      blob.type !== 'application/octet-stream'
    ) {
      return blob.type;
    }

    const nome = documento.nome_arquivo.toLowerCase();

    if (nome.endsWith('.pdf')) {
      return 'application/pdf';
    }

    if (nome.endsWith('.png')) {
      return 'image/png';
    }

    if (nome.endsWith('.jpg') || nome.endsWith('.jpeg')) {
      return 'image/jpeg';
    }

    if (nome.endsWith('.gif')) {
      return 'image/gif';
    }

    if (nome.endsWith('.webp')) {
      return 'image/webp';
    }

    return blob.type || 'application/octet-stream';
  }


  // ============================================================
  // FECHAR PREVIEW
  // ============================================================

  fecharPreviewDocumento(): void {
    this.documentoPreviewAberto.set(false);

    this.limparPreviewDocumento();

    this.documentoPreviewTipo.set('');

    this.documentoPreviewNome.set('');

    this.abrindoDocumento.set(false);
  }


  // ============================================================
  // LIMPAR PREVIEW
  // ============================================================

  private limparPreviewDocumento(): void {
    if (this.documentoPreviewBlobUrl) {
      URL.revokeObjectURL(this.documentoPreviewBlobUrl);

      this.documentoPreviewBlobUrl = null;
    }

    this.documentoPreviewUrl.set(null);

    this.documentoPreviewPdfUrl.set(null);
  }


  // ============================================================
  // NAVEGAÇÃO ENTRE SECÇÕES
  // ============================================================

  irParaSecao(secao: string): void {

    // A gestão de utilizadores é uma página independente.
    if (secao === 'utilizadores') {
      this.router.navigate(['/admin/utilizadores']);

      return;
    }

    if (secao === 'assiduidade') {
      return;
    }

    this.secaoAtiva.set(secao as SecaoAdmin);

    if (secao === 'estagiarios') {
      this.carregarEstagiarios();
    }

    if (!isPlatformBrowser(this.platformId)) {
      return;
    }

    document.getElementById(secao)?.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });
  }


  // ============================================================
  // LOGOUT
  // ============================================================

  sair(): void {
    const sub = this.authService.logout().subscribe({
      next: () => {
        this.router.navigate(['/acesso']);
      },

      error: (erro: unknown) => {
        console.error('Erro ao encerrar sessão:', erro);

        this.router.navigate(['/acesso']);
      }
    });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // CALCULAR DURAÇÃO DO ESTÁGIO
  // ============================================================

  calcularDuracaoMeses(inicio: string, fim: string): number {
    if (!inicio || !fim) {
      return 2;
    }

    const [ai, mi, di] = inicio.split('-').map(Number);

    const [af, mf, df] = fim.split('-').map(Number);

    let meses = (af - ai) * 12 + (mf - mi);

    if (df < di) {
      meses--;
    }

    return Math.max(1, Math.min(60, meses || 1));
  }


  // ============================================================
  // CALCULAR DATA DE FIM DO ESTÁGIO
  // ============================================================

  calcularDataFimEstagio(
    inicio: string,
    meses: number
  ): string {
    if (
      !inicio ||
      !Number.isFinite(meses) ||
      meses < 1
    ) {
      return '';
    }

    const [ano, mes, dia] = inicio.split('-').map(Number);

    const data = new Date(ano, mes - 1, 1);

    data.setMonth(data.getMonth() + Math.floor(meses));

    const ultimoDia = new Date(
      data.getFullYear(),
      data.getMonth() + 1,
      0
    ).getDate();

    data.setDate(Math.min(dia, ultimoDia));

    const a = data.getFullYear();

    const m = String(data.getMonth() + 1).padStart(2, '0');

    const d = String(data.getDate()).padStart(2, '0');

    return `${a}-${m}-${d}`;
  }


  // ============================================================
  // ABRIR GESTÃO DO ESTÁGIO
  // ============================================================

  abrirGestaoEstagio(estagiario: Estagiario): void {
    this.estagioEmGestao.set(estagiario);

    const estados: readonly EstadoEstagio[] = [
      'ativo',
      'suspenso',
      'concluido',
      'cancelado'
    ];

    const estadoRecebido = estagiario.estado_estagio;

    const estado: EstadoEstagio = estados.includes(
      estadoRecebido as EstadoEstagio
    )
      ? estadoRecebido as EstadoEstagio
      : 'ativo';

    this.estadoEstagioFormulario.set(estado);

    this.inicioEstagioFormulario.set(
      estagiario.data_inicio ?? ''
    );

    this.fimEstagioFormulario.set(
      estagiario.data_fim_prevista ?? ''
    );

    this.duracaoMesesFormulario.set(
      this.calcularDuracaoMeses(
        estagiario.data_inicio ?? '',
        estagiario.data_fim_prevista ?? ''
      )
    );

    this.motivoSuspensaoFormulario.set(
      estagiario.motivo_suspensao ?? ''
    );

    this.erroGestaoEstagio.set('');

    this.sucessoGestaoEstagio.set('');
  }


  // ============================================================
  // FECHAR GESTÃO DO ESTÁGIO
  // ============================================================

  fecharGestaoEstagio(): void {
    if (!this.aGuardarEstadoEstagio()) {
      this.estagioEmGestao.set(null);

      this.erroGestaoEstagio.set('');
    }
  }


  // ============================================================
  // GUARDAR ESTADO DO ESTÁGIO
  // ============================================================

  guardarEstadoEstagio(): void {
    const estagiario = this.estagioEmGestao();

    if (
      !estagiario ||
      this.aGuardarEstadoEstagio()
    ) {
      return;
    }

    const estado = this.estadoEstagioFormulario();

    const inicio = this.inicioEstagioFormulario();

    const fim = this.calcularDataFimEstagio(
      inicio,
      this.duracaoMesesFormulario()
    );

    const motivo = this.motivoSuspensaoFormulario().trim();

    if (!estagiario.id_estagio && (!inicio || !fim)) {
      this.erroGestaoEstagio.set(
        'Indica a data de início e a data prevista de fim.'
      );

      return;
    }

    if (inicio && fim && fim < inicio) {
      this.erroGestaoEstagio.set(
        'A data prevista de fim não pode ser anterior à data de início.'
      );

      return;
    }

    if (estado === 'suspenso' && !motivo) {
      this.erroGestaoEstagio.set(
        'Indica o motivo da suspensão.'
      );

      return;
    }

    this.aGuardarEstadoEstagio.set(true);

    this.erroGestaoEstagio.set('');

    const sub = this.adminService
      .atualizarEstadoEstagio(
        estagiario.id_candidatura,
        {
          estado,

          data_inicio: inicio || undefined,

          data_fim_prevista: fim || undefined,

          motivo_suspensao: estado === 'suspenso'
            ? motivo
            : undefined
        }
      )
      .subscribe({
        next: (resposta) => {
          this.sucessoGestaoEstagio.set(
            resposta.message || 'Estado atualizado.'
          );

          this.aGuardarEstadoEstagio.set(false);

          this.estagioEmGestao.set(null);

          this.carregarEstagiarios();
        },

        error: (erro: unknown) => {
          const resposta = erro as {
            error?: {
              message?: string;
            };
          };

          this.erroGestaoEstagio.set(
            resposta.error?.message ||
            'Não foi possível atualizar o estágio. Verifica a API e as permissões.'
          );

          this.aGuardarEstadoEstagio.set(false);
        }
      });

    this.subscriptions.add(sub);
  }

}
