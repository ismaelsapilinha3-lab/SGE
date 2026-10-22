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
  EstagiariosPorDepartamentoItem,
  EstagiariosResponse,
  RelatorioAdmin,
  RelatoriosAdminResponse
} from '../../../services/admin.service';

import { AuthService } from '../../../core/auth.service';

import { PerfilService } from '../../../services/perfil.service';

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
  | 'relatorios'
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
          id: 'relatorios',
          rotulo: 'Relatórios',
          icone: '▥'
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

  private readonly perfilService = inject(PerfilService);

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
  // ESTAGIÁRIOS POR DEPARTAMENTO (DASHBOARD EM ESCADA)
  // ============================================================

  estagiariosDepartamento = signal<EstagiariosPorDepartamentoItem[]>([]);

  totalEstagiariosDepartamento = signal(0);

  estadosEscada = signal<string[]>([]);

  totaisPorEstado = signal<Record<string, number>>({});

  carregandoEstagiariosDepartamento = signal(false);

  erroEstagiariosDepartamento = signal('');

  /** Etiqueta e cor de cada estado do estágio na escada. */
  private readonly configuracaoEstados: Record<
    string,
    { etiqueta: string; cor: string }
  > = {
    sem_estagio: { etiqueta: 'Aprovados por iniciar', cor: '#7A8AA8' },
    ativo: { etiqueta: 'Estágio ativo', cor: '#137A47' },
    suspenso: { etiqueta: 'Estágio suspenso', cor: '#FF6B00' },
    concluido: { etiqueta: 'Concluído', cor: '#1A3D6B' },
    cancelado: { etiqueta: 'Cancelado', cor: '#C4323F' }
  };

  /** Ordem dos estados dentro da barra, de baixo para cima. */
  private readonly ordemEstados = [
    'sem_estagio',
    'ativo',
    'suspenso',
    'concluido',
    'cancelado'
  ];

  /**
   * Escada: departamentos ordenados do maior para o menor, com a
   * altura da barra em percentagem e a barra dividida por estado
   * do estágio.
   */
  escadaDepartamentos = computed(() => {
    const lista = [...this.estagiariosDepartamento()].sort(
      (a, b) =>
        b.total_estagiarios - a.total_estagiarios ||
        a.nome.localeCompare(b.nome)
    );

    const maior = Math.max(
      0,
      ...lista.map(item => item.total_estagiarios)
    );

    return lista.map(item => {
      /*
       * Estados extra (caso apareça algum não previsto) vão para o
       * topo, para não desaparecerem da barra.
       */
      const estados = [
        ...this.ordemEstados,
        ...Object.keys(item.por_estado ?? {}).filter(
          estado => !this.ordemEstados.includes(estado)
        )
      ];

      /* Invertido: o template desenha de cima para baixo. */
      const segmentos = estados
        .slice()
        .reverse()
        .map(estado => ({
          estado,
          quantidade: Number(item.por_estado?.[estado] ?? 0),
          etiqueta: this.etiquetaEstado(estado),
          cor: this.corEstado(estado)
        }))
        .filter(segmento => segmento.quantidade > 0);

      return {
        ...item,
        altura:
          maior === 0
            ? 0
            : Math.max(
                10,
                Math.round((item.total_estagiarios / maior) * 100)
              ),
        segmentos,
        destaque: maior > 0 && item.total_estagiarios === maior
      };
    });
  });

  /** Legenda da escada, com o total de estagiários por estado. */
  legendaEscada = computed(() => {
    const totais = this.totaisPorEstado();

    const estados = this.estadosEscada().length > 0
      ? this.estadosEscada()
      : this.ordemEstados;

    return estados.map(estado => ({
      estado,
      etiqueta: this.etiquetaEstado(estado),
      cor: this.corEstado(estado),
      total: Number(totais[estado] ?? 0)
    }));
  });

  private etiquetaEstado(estado: string): string {
    return this.configuracaoEstados[estado]?.etiqueta ?? estado;
  }

  private corEstado(estado: string): string {
    return this.configuracaoEstados[estado]?.cor ?? '#7A8AA8';
  }


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

  porPagina = signal(50);


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

  // ==========================================================
  // RELATORIOS ADMINISTRATIVOS
  // ==========================================================

  relatorios = signal<RelatorioAdmin[]>([]);
  carregandoRelatorios = signal(false);
  erroRelatorios = signal('');
  sucessoRelatorio = signal('');
  pesquisaRelatorios = signal('');
  estadoRelatorios = signal('');
  tipoRelatorios = signal('');
  relatoriosEmAtraso = signal<RelatorioAdmin[]>([]);
  relatorioSelecionado = signal<RelatorioAdmin | null>(null);
  comentarioAvaliacaoRelatorio = signal('');
  avaliandoRelatorioId = signal<number | null>(null);
  erroAvaliacaoRelatorio = signal('');

  /*
   * Ficheiro do relatório mostrado no painel.
   */
  aCarregarFicheiroRelatorio = signal<number | null>(null);

  /*
   * Plano de estágio do estagiário em gestão.
   */
  planoEstagioAtual = signal<{
    id_plano_estagio: number;
    nome_arquivo: string;
    formato_legivel: string;
    versao: number;
    data_upload_legivel?: string | null;
  } | null>(null);

  aCarregarPlano = signal(false);
  erroPlanoEstagio = signal('');
  sucessoPlanoEstagio = signal('');

  /*
   * Dia limite de entrega dos relatórios.
   */
  diaLimiteRelatorioFormulario = signal(5);

  ngOnInit(): void {
    this.carregarDashboard();

    this.carregarEstagiariosPorDepartamento();

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
          'Não foi possível carregar os dados do dashboard.'
        );
      }
    });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // CARREGAR ESTAGIÁRIOS POR DEPARTAMENTO
  // ============================================================

  carregarEstagiariosPorDepartamento(): void {
    this.carregandoEstagiariosDepartamento.set(true);

    this.erroEstagiariosDepartamento.set('');

    const sub = this.adminService
      .estagiariosPorDepartamento()
      .subscribe({
        next: (dados) => {
          this.estagiariosDepartamento.set(dados.departamentos);

          this.estadosEscada.set(dados.estados ?? []);

          this.totaisPorEstado.set(dados.totais_estado ?? {});

          this.totalEstagiariosDepartamento.set(
            dados.total_estagiarios
          );

          this.carregandoEstagiariosDepartamento.set(false);
        },

        error: (erro: unknown) => {
          console.error(
            'Erro ao carregar estagiários por departamento:',
            erro
          );

          this.carregandoEstagiariosDepartamento.set(false);

          this.erroEstagiariosDepartamento.set(
            'Não foi possível carregar os estagiários por departamento.'
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

            this.carregarEstagiariosPorDepartamento();
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

        this.carregarEstagiariosPorDepartamento();
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

    this.erroEstagiariosDepartamento.set('');

    this.erroCandidaturas.set('');

    this.erroDepartamentos.set('');

    this.carregarDashboard();

    this.carregarEstagiariosPorDepartamento();

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
  // RÓTULO E NOTA DO ESTADO DO ESTÁGIO
  // ============================================================

  rotuloEstadoEstagio(estado?: string | null): string {
    switch (estado) {
      case 'ativo':
        return 'Ativo';

      case 'aprovada':
        return 'A aguardar início';

      case 'suspenso':
        return 'Suspenso';

      case 'concluido':
        return 'Concluído';

      case 'cancelado':
        return 'Cancelado';

      default:
        return 'Sem estágio';
    }
  }


  notaEstadoEstagio(estagiario: Estagiario): string {
    switch (estagiario.estado_estagio) {
      case 'ativo': {
        const dias = this.diasRestantes(estagiario);

        if (dias === null) {
          return 'Sem data de término';
        }

        if (dias <= 0) {
          return 'Prazo terminado';
        }

        return `${dias} dia(s) restante(s)`;
      }

      case 'suspenso':
        return estagiario.motivo_suspensao || 'Motivo não registado';

      case 'aprovada':
        return 'A aguardar início do estágio';

      case 'concluido':
        return 'Estágio concluído';

      case 'cancelado':
        return 'Estágio cancelado';

      default:
        return 'Sem estágio associado';
    }
  }


  // ============================================================
  // FORMATAR DATA (YYYY-MM-DD -> DD/MM/YYYY)
  // ============================================================

  formatarData(data?: string | null): string {
    if (!data) {
      return '—';
    }

    const partes = data.substring(0, 10).split('-');

    if (partes.length !== 3) {
      return data;
    }

    return `${partes[2]}/${partes[1]}/${partes[0]}`;
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

          this.carregarEstagiariosPorDepartamento();

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


  // ==========================================================
  // CARREGAR RELATORIOS
  // ==========================================================

  carregarRelatorios(): void {
    this.carregandoRelatorios.set(true);
    this.erroRelatorios.set('');
    this.sucessoRelatorio.set('');

    const sub = this.adminService.listarRelatoriosAdmin(
      this.pesquisaRelatorios(),
      this.estadoRelatorios(),
      this.tipoRelatorios(),
      1
    ).subscribe({
      next: (resposta: RelatoriosAdminResponse) => {
        this.relatorios.set(resposta.data ?? []);
        this.carregandoRelatorios.set(false);
      },
      error: (erro: unknown) => {
        console.error('Erro ao carregar relatórios:', erro);
        this.relatorios.set([]);
        this.erroRelatorios.set(
          'Não foi possível carregar os relatórios. Confirma a API e a autenticação.'
        );
        this.carregandoRelatorios.set(false);
      }
    });

    this.subscriptions.add(sub);

    const subAtrasos = this.adminService.listarRelatoriosAtraso().subscribe({
      next: (resposta) => {
        this.relatoriosEmAtraso.set(resposta.data ?? []);
      },
      error: () => {
        this.relatoriosEmAtraso.set([]);
      }
    });

    this.subscriptions.add(subAtrasos);
  }

  pesquisarRelatorios(): void {
    this.carregarRelatorios();
  }

  // ==========================================================
  // ABRIR E FECHAR DETALHES DO RELATORIO
  // ==========================================================

  abrirRelatorio(relatorio: RelatorioAdmin): void {
    this.relatorioSelecionado.set(relatorio);
    this.comentarioAvaliacaoRelatorio.set(
      relatorio.comentario_admin ?? ''
    );
    this.erroAvaliacaoRelatorio.set('');
    this.sucessoRelatorio.set('');
  }

  fecharRelatorio(): void {
    this.relatorioSelecionado.set(null);
    this.comentarioAvaliacaoRelatorio.set('');
    this.erroAvaliacaoRelatorio.set('');
  }

  // ==========================================================
  // APROVAR OU REJEITAR RELATORIO
  // ==========================================================

  avaliarRelatorio(estado: 'aprovado' | 'rejeitado'): void {
    const relatorio = this.relatorioSelecionado();

    if (!relatorio || this.avaliandoRelatorioId() !== null) {
      return;
    }

    const comentario = this.comentarioAvaliacaoRelatorio().trim();

    if (estado === 'rejeitado' && !comentario) {
      this.erroAvaliacaoRelatorio.set(
        'Indica o motivo da rejeição antes de continuar.'
      );
      return;
    }

    this.avaliandoRelatorioId.set(relatorio.id_relatorio);
    this.erroAvaliacaoRelatorio.set('');
    this.sucessoRelatorio.set('');

    const sub = this.adminService.avaliarRelatorioAdmin(
      relatorio.id_relatorio,
      estado,
      comentario
    ).subscribe({
      next: (resposta) => {
        const atualizado: RelatorioAdmin = {
          ...relatorio,
          estado_avaliacao: resposta.data.estado_avaliacao,
          comentario_admin: resposta.data.comentario_admin
        };

        this.relatorios.update(lista =>
          lista.map(item =>
            item.id_relatorio === atualizado.id_relatorio
              ? atualizado
              : item
          )
        );

        this.relatorioSelecionado.set(atualizado);
        this.comentarioAvaliacaoRelatorio.set(
          atualizado.comentario_admin ?? ''
        );
        this.sucessoRelatorio.set(resposta.message);
        this.avaliandoRelatorioId.set(null);
      },
      error: (erro: unknown) => {
        console.error('Erro ao avaliar relatório:', erro);
        this.erroAvaliacaoRelatorio.set(
          'Não foi possível guardar a avaliação. Verifica os dados e tenta novamente.'
        );
        this.avaliandoRelatorioId.set(null);
      }
    });

    this.subscriptions.add(sub);
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

    if (secao === 'relatorios') {
      this.carregarRelatorios();
    }

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

  /** Foto de perfil do utilizador autenticado. */
  readonly fotoPerfil = this.authService.fotoUrl;

  /**
   * Envia a foto escolhida no topo para o servidor e atualiza a sessão.
   */
  alterarFotoPerfil(ficheiro: File): void {
    const sub = this.perfilService.atualizarFoto(ficheiro).subscribe({
      next: (resposta) => {
        this.authService.definirFoto(resposta.foto_url);
      },

      error: (erro: unknown) => {
        console.error('Erro ao atualizar a foto de perfil:', erro);
      }
    });

    this.subscriptions.add(sub);
  }

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


  calcularDuracaoMeses(inicio: string, fim: string): number {
    if (!inicio || !fim) return 6;

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

  alterarInicioEstagio(inicio: string): void {
    this.inicioEstagioFormulario.set(inicio);
    this.fimEstagioFormulario.set(
      this.calcularDataFimEstagio(inicio, this.duracaoMesesFormulario())
    );
  }

  alterarDuracaoEstagio(valor: string): void {
    if (valor.trim() === '') {
      this.duracaoMesesFormulario.set(0);
      this.fimEstagioFormulario.set('');
      return;
    }

    const meses = Number(valor);

    if (!Number.isInteger(meses) || meses < 1) {
      this.duracaoMesesFormulario.set(0);
      this.fimEstagioFormulario.set('');
      return;
    }

    this.duracaoMesesFormulario.set(meses);
    this.fimEstagioFormulario.set(
      this.calcularDataFimEstagio(this.inicioEstagioFormulario(), meses)
    );
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

    const inicio = estagiario.data_inicio ?? '';
    const duracaoGuardada = estagiario.duracao_meses;
    const duracao = duracaoGuardada && duracaoGuardada >= 1
      ? duracaoGuardada
      : (inicio && estagiario.data_fim_prevista
          ? this.calcularDuracaoMeses(inicio, estagiario.data_fim_prevista)
          : 6);

    this.estadoEstagioFormulario.set(estado);

    this.inicioEstagioFormulario.set(inicio);
    this.duracaoMesesFormulario.set(duracao);
    this.fimEstagioFormulario.set(
      this.calcularDataFimEstagio(inicio, duracao)
    );
    this.motivoSuspensaoFormulario.set(estagiario.motivo_suspensao ?? '');

    this.diaLimiteRelatorioFormulario.set(
      estagiario.dia_limite_relatorio ?? 5
    );

    this.planoEstagioAtual.set(this.planosPorEstagio[estagiario.id_estagio ?? -1] ?? null);
    this.carregarPlanoDoEstagiario(estagiario);

    this.erroPlanoEstagio.set('');
    this.sucessoPlanoEstagio.set('');

    this.erroGestaoEstagio.set('');

    this.sucessoGestaoEstagio.set('');
  }


  // ============================================================
  // DIA LIMITE DE ENTREGA DOS RELATORIOS
  // ============================================================

  alterarDiaLimiteRelatorio(valor: string): void {
    const numero = Number(valor);

    if (Number.isNaN(numero)) {
      this.diaLimiteRelatorioFormulario.set(5);

      return;
    }

    this.diaLimiteRelatorioFormulario.set(
      Math.min(28, Math.max(1, Math.trunc(numero)))
    );
  }


  // ============================================================
  // PLANO DE ESTAGIO DO ESTAGIARIO EM GESTAO
  // ============================================================

  private readonly planosPorEstagio: Record<
    number,
    {
      id_plano_estagio: number;
      nome_arquivo: string;
      formato_legivel: string;
      versao: number;
      data_upload_legivel?: string | null;
    }
  > = {};

  private carregarPlanoDoEstagiario(estagiario: Estagiario): void {
    if (!estagiario.id_estagio) {
      this.planoEstagioAtual.set(null);

      return;
    }

    this.adminService.obterPlanoEstagio(estagiario.id_estagio).subscribe({
      next: (plano) => {
        this.planoEstagioAtual.set(plano);

        if (plano) {
          this.planosPorEstagio[estagiario.id_estagio as number] = plano;
        } else {
          delete this.planosPorEstagio[estagiario.id_estagio as number];
        }
      },
      error: () => {
        this.planoEstagioAtual.set(null);
      }
    });
  }

  carregarPlanoEstagio(event: Event): void {
    const input = event.target as HTMLInputElement;

    const ficheiro = input.files?.[0];

    input.value = '';

    const estagiario = this.estagioEmGestao();

    if (!ficheiro || !estagiario?.id_estagio || this.aCarregarPlano()) {
      return;
    }

    const extensao = ficheiro.name.split('.').pop()?.toLowerCase();

    if (!extensao || !['pdf', 'doc', 'docx'].includes(extensao)) {
      this.erroPlanoEstagio.set(
        'Selecciona um documento PDF ou Word (DOC/DOCX).'
      );
      this.sucessoPlanoEstagio.set('');

      return;
    }

    if (ficheiro.size > 10 * 1024 * 1024) {
      this.erroPlanoEstagio.set(
        'O plano não pode ultrapassar 10 MB.'
      );
      this.sucessoPlanoEstagio.set('');

      return;
    }

    this.aCarregarPlano.set(true);
    this.erroPlanoEstagio.set('');
    this.sucessoPlanoEstagio.set('');

    const sub = this.adminService
      .carregarPlanoEstagio(estagiario.id_estagio, ficheiro)
      .subscribe({
        next: (resposta) => {
          this.aCarregarPlano.set(false);

          this.sucessoPlanoEstagio.set(resposta.message);

          this.planoEstagioAtual.set(resposta.data);

          this.planosPorEstagio[estagiario.id_estagio as number] =
            resposta.data;
        },
        error: (erro: unknown) => {
          console.error('Erro ao carregar o plano de estágio:', erro);

          this.aCarregarPlano.set(false);

          const resposta = erro as {
            error?: { message?: string };
          };

          this.erroPlanoEstagio.set(
            resposta.error?.message ||
              'Não foi possível carregar o plano de estágio.'
          );
        }
      });

    this.subscriptions.add(sub);
  }


  // ============================================================
  // VISUALIZAR O FICHEIRO DE UM RELATORIO
  // ============================================================

  verFicheiroRelatorio(relatorio: RelatorioAdmin): void {
    if (!isPlatformBrowser(this.platformId)) {
      return;
    }

    const janela = window.open('', '_blank');

    if (!janela) {
      this.erroAvaliacaoRelatorio.set(
        'O navegador bloqueou a abertura do ficheiro.'
      );

      return;
    }

    this.aCarregarFicheiroRelatorio.set(relatorio.id_relatorio);

    const sub = this.adminService
      .visualizarFicheiroRelatorio(relatorio.id_relatorio)
      .subscribe({
        next: (blob) => {
          const url = URL.createObjectURL(blob);

          janela.location.href = url;

          window.setTimeout(() => URL.revokeObjectURL(url), 60000);

          this.aCarregarFicheiroRelatorio.set(null);
        },
        error: (erro: unknown) => {
          console.error('Erro ao abrir o ficheiro do relatório:', erro);

          janela.close();

          this.aCarregarFicheiroRelatorio.set(null);

          this.erroAvaliacaoRelatorio.set(
            'Não foi possível abrir o ficheiro do relatório.'
          );
        }
      });

    this.subscriptions.add(sub);
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

          duracao_meses: this.duracaoMesesFormulario() || undefined,

          data_fim_prevista: fim || undefined,

          dia_limite_relatorio: this.diaLimiteRelatorioFormulario(),

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

          this.carregarEstagiariosPorDepartamento();

          this.carregarDepartamentos();
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
