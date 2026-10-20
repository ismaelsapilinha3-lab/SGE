import {
  CommonModule,
  isPlatformBrowser
} from '@angular/common';

import {
  Component,
  PLATFORM_ID,
  computed,
  inject,
  signal
} from '@angular/core';

import {
  FormsModule
} from '@angular/forms';

import {
  Assiduidade,
  Atividade,
  Avaliacao,
  DocumentoContrato,
  Feedback,
  Relatorio
} from '../../../services/estagio-estagiario.service';

import {
  DadosAssiduidade,
  DadosAvaliacao,
  DadosPlano,
  EstagioSupervisorService,
  EstagioSupervisionado,
  FiltrosAssiduidade,
  PlanoEstagio,
  ResumoAssiduidade,
  ResumoAvaliacoes
} from '../../../services/estagio-supervisor.service';


// ============================================================
// TIPOS DAS ABAS
// ============================================================

type AbaSupervisor =
  | 'plano'
  | 'contrato'
  | 'relatorios'
  | 'assiduidade'
  | 'avaliacoes'
  | 'feedback'
  | 'atividades';


interface AbaDefinicao {

  id: AbaSupervisor;

  rotulo: string;

  /** Glifo mostrado no botão do menu de gestão. */
  icone: string;

  /** Texto curto que explica a funcionalidade do botão. */
  descricao: string;

}


const ABAS: AbaDefinicao[] = [

  {
    id: 'plano',
    rotulo: 'Plano de estágio',
    icone: '🗒',
    descricao: 'Criar e submeter o plano de atividades.'
  },

  {
    id: 'contrato',
    rotulo: 'Contrato',
    icone: '📄',
    descricao: 'Validar ou rejeitar os documentos do contrato.'
  },

  {
    id: 'relatorios',
    rotulo: 'Relatórios',
    icone: '📊',
    descricao: 'Consultar os relatórios enviados pelo estagiário.'
  },

  {
    id: 'assiduidade',
    rotulo: 'Assiduidade',
    icone: '🗓',
    descricao: 'Registar presenças, faltas e justificações.'
  },

  {
    id: 'avaliacoes',
    rotulo: 'Avaliações',
    icone: '⭐',
    descricao: 'Avaliar o desempenho do estagiário.'
  },

  {
    id: 'feedback',
    rotulo: 'Feedback',
    icone: '💬',
    descricao: 'Ler o feedback partilhado pelo estagiário.'
  },

  {
    id: 'atividades',
    rotulo: 'Atividades',
    icone: '✅',
    descricao: 'Acompanhar as atividades registadas.'
  }

];


const RESUMO_ASSIDUIDADE_VAZIO: ResumoAssiduidade = {

  presente: 0,

  atraso: 0,

  falta: 0,

  falta_justificada: 0,

  justificacoes_pendentes: 0,

  total: 0

};


// ============================================================
// COMPONENTE
// ============================================================

@Component({
  selector: 'app-estagio-supervisor',
  standalone: true,

  imports: [
    CommonModule,
    FormsModule
  ],

  templateUrl: './estagio-supervisor.html',

  styleUrl: './estagio-supervisor.css'
})
export class EstagioSupervisorComponent {

  private readonly estagioService =
    inject(EstagioSupervisorService);

  private readonly platformId = inject(PLATFORM_ID);


  // ==========================================================
  // ESTÁGIOS ATRIBUÍDOS
  // ==========================================================

  readonly carregando = signal(true);

  readonly erro = signal<string | null>(null);

  readonly estagios = signal<EstagioSupervisionado[]>([]);

  readonly estagioSelecionadoId = signal<number | null>(null);

  readonly estagioSelecionado = computed(() => {

    const id = this.estagioSelecionadoId();

    if (id === null) {

      return null;

    }

    return this.estagios().find(
      estagio => estagio.id_estagio === id
    ) ?? null;

  });


  // ==========================================================
  // ESTADO GERAL
  // ==========================================================

  readonly abas = ABAS;

  readonly aba = signal<AbaSupervisor>('plano');

  readonly sucesso = signal<string | null>(null);

  readonly erroAcao = signal<string | null>(null);

  readonly processando = signal(false);

  private readonly abasCarregadas = new Set<AbaSupervisor>();


  // ==========================================================
  // PLANO
  // ==========================================================

  readonly plano = signal<PlanoEstagio | null>(null);

  readonly carregandoPlano = signal(false);

  planoObjetivos = '';

  planoAtividadesPrevistas = '';

  planoCronograma = '';

  planoDataInicio = '';

  planoDataFim = '';


  // ==========================================================
  // CONTRATO
  // ==========================================================

  readonly documentos = signal<DocumentoContrato[]>([]);

  readonly carregandoDocumentos = signal(false);

  readonly documentoEmRejeicao =
    signal<DocumentoContrato | null>(null);

  motivoRejeicao = '';


  // ==========================================================
  // RELATÓRIOS
  // ==========================================================

  readonly relatorios = signal<Relatorio[]>([]);

  readonly carregandoRelatorios = signal(false);


  // ==========================================================
  // ASSIDUIDADE
  // ==========================================================

  readonly assiduidades = signal<Assiduidade[]>([]);

  readonly carregandoAssiduidade = signal(false);

  readonly resumoAssiduidade =
    signal<ResumoAssiduidade>(RESUMO_ASSIDUIDADE_VAZIO);

  mesFiltro = '';

  estadoFiltro = '';

  assiduidadeData = '';

  assiduidadeEstado = signal<Assiduidade['estado']>('presente');

  assiduidadeHoraEntrada = '';

  assiduidadeHoraSaida = '';

  assiduidadeJustificacao = '';

  readonly justificacaoEmRecusa =
    signal<Assiduidade | null>(null);

  motivoRecusaJustificacao = '';


  // ==========================================================
  // AVALIAÇÕES
  // ==========================================================

  readonly avaliacoes = signal<Avaliacao[]>([]);

  readonly carregandoAvaliacoes = signal(false);

  readonly resumoAvaliacoes =
    signal<ResumoAvaliacoes | null>(null);

  avaliacaoTipo: Avaliacao['tipo'] = 'mensal';

  avaliacaoPeriodo = '';

  avaliacaoNota: number | null = null;

  avaliacaoNotaAtividades: number | null = null;

  avaliacaoNotaAssiduidade: number | null = null;

  avaliacaoNotaPontualidade: number | null = null;

  avaliacaoComentarios = '';

  avaliacaoPontosFortes = '';

  avaliacaoAreasMelhoria = '';


  // ==========================================================
  // FEEDBACK E ATIVIDADES
  // ==========================================================

  readonly feedback = signal<Feedback | null>(null);

  readonly carregandoFeedback = signal(false);

  readonly atividades = signal<Atividade[]>([]);

  readonly carregandoAtividades = signal(false);


  // ==========================================================
  // PERMISSÕES
  // ==========================================================

  readonly estagioAtivo = computed(() => {

    return this.estagioSelecionado()?.estado === 'ativo';

  });

  readonly aceitaRegistosDeFecho = computed(() => {

    const estado = this.estagioSelecionado()?.estado;

    return estado !== null && estado !== undefined
      && estado !== 'cancelado';

  });

  readonly assiduidadeEhFalta = computed(() => {

    const estado = this.assiduidadeEstado();

    return estado === 'falta'
      || estado === 'falta_justificada';

  });


  // ==========================================================
  // CONSTRUTOR
  // ==========================================================

  constructor() {

    if (isPlatformBrowser(this.platformId)) {

      this.carregarEstagios();

    }

  }


  // ==========================================================
  // CARREGAR ESTÁGIOS
  // ==========================================================

  private carregarEstagios(): void {

    this.carregando.set(true);

    this.erro.set(null);

    this.estagioService.estagios().subscribe({

      next: resposta => {

        const estagios = resposta.estagios ?? [];

        this.estagios.set(estagios);

        this.carregando.set(false);

        if (estagios.length > 0) {

          this.selecionarEstagio(estagios[0].id_estagio);

        }

      },

      error: erro => {

        console.error('Erro ao carregar estágios:', erro);

        this.estagios.set([]);

        this.carregando.set(false);

        this.erro.set(
          this.mensagemErro(
            erro,
            'Não foi possível carregar os estágios atribuídos.'
          )
        );

      }

    });

  }


  selecionarEstagio(id: number): void {

    if (this.estagioSelecionadoId() === id) {

      return;

    }

    this.estagioSelecionadoId.set(id);

    this.abasCarregadas.clear();

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.limparDados();

    this.carregarDadosAba(this.aba());

  }


  recarregar(): void {

    this.abasCarregadas.clear();

    this.estagioSelecionadoId.set(null);

    this.carregarEstagios();

  }


  aoMudarEstagio(event: Event): void {

    const select = event.target as HTMLSelectElement;

    const id = Number(select.value);

    if (!Number.isNaN(id)) {

      this.selecionarEstagio(id);

    }

  }


  private limparDados(): void {

    this.plano.set(null);

    this.documentos.set([]);

    this.relatorios.set([]);

    this.assiduidades.set([]);

    this.resumoAssiduidade.set(RESUMO_ASSIDUIDADE_VAZIO);

    this.avaliacoes.set([]);

    this.resumoAvaliacoes.set(null);

    this.feedback.set(null);

    this.atividades.set([]);

  }


  // ==========================================================
  // NAVEGAÇÃO ENTRE ABAS
  // ==========================================================

  definirAba(aba: AbaSupervisor): void {

    this.aba.set(aba);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.carregarDadosAba(aba);

  }


  private carregarDadosAba(aba: AbaSupervisor): void {

    const estagio = this.estagioSelecionado();

    if (!estagio) {

      return;

    }

    if (this.abasCarregadas.has(aba)) {

      return;

    }

    this.abasCarregadas.add(aba);

    switch (aba) {

      case 'plano':
        this.carregarPlano(estagio.id_estagio);
        break;

      case 'contrato':
        this.carregarDocumentos(estagio.id_estagio);
        break;

      case 'relatorios':
        this.carregarRelatorios(estagio.id_estagio);
        break;

      case 'assiduidade':
        this.carregarAssiduidade(estagio.id_estagio);
        break;

      case 'avaliacoes':
        this.carregarAvaliacoes(estagio.id_estagio);
        break;

      case 'feedback':
        this.carregarFeedback(estagio.id_estagio);
        break;

      case 'atividades':
        this.carregarAtividades(estagio.id_estagio);
        break;

    }

  }


  private estagioId(): number | null {

    return this.estagioSelecionado()?.id_estagio ?? null;

  }


  // ==========================================================
  // PLANO
  // ==========================================================

  private carregarPlano(idEstagio: number): void {

    this.carregandoPlano.set(true);

    this.estagioService.plano(idEstagio).subscribe({

      next: resposta => {

        this.plano.set(resposta.plano);

        this.preencherFormularioPlano(resposta.plano);

        this.carregandoPlano.set(false);

      },

      error: erro => {

        console.error('Erro ao carregar o plano:', erro);

        this.plano.set(null);

        this.carregandoPlano.set(false);

        this.erroAcao.set(
          'Não foi possível carregar o plano de estágio.'
        );

      }

    });

  }


  private preencherFormularioPlano(plano: PlanoEstagio | null): void {

    const estagio = this.estagioSelecionado();

    this.planoObjetivos = plano?.objetivos ?? '';

    this.planoAtividadesPrevistas =
      plano?.atividades_previstas ?? '';

    this.planoCronograma = plano?.cronograma ?? '';

    this.planoDataInicio = (
      plano?.data_inicio ??
      estagio?.data_inicio ??
      ''
    ).substring(0, 10);

    this.planoDataFim = (
      plano?.data_fim ??
      estagio?.data_fim_prevista ??
      ''
    ).substring(0, 10);

  }


  guardarPlano(): void {

    const idEstagio = this.estagioId();

    if (idEstagio === null || this.processando()) {

      return;

    }

    if (
      !this.planoObjetivos.trim() ||
      !this.planoAtividadesPrevistas.trim() ||
      !this.planoCronograma.trim() ||
      !this.planoDataInicio ||
      !this.planoDataFim
    ) {

      this.erroAcao.set(
        'Preenche os objetivos, as atividades previstas, o cronograma e as datas.'
      );

      return;

    }

    if (this.planoDataFim < this.planoDataInicio) {

      this.erroAcao.set(
        'A data de fim não pode ser anterior à data de início.'
      );

      return;

    }

    const dados: DadosPlano = {

      objetivos: this.planoObjetivos.trim(),

      atividades_previstas: this.planoAtividadesPrevistas.trim(),

      cronograma: this.planoCronograma.trim(),

      data_inicio: this.planoDataInicio,

      data_fim: this.planoDataFim

    };

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.estagioService.submeterPlano(idEstagio, dados).subscribe({

      next: resposta => {

        this.processando.set(false);

        this.sucesso.set(
          resposta.message || 'Plano de estágio submetido.'
        );

        this.carregarPlano(idEstagio);

      },

      error: erro => {

        console.error('Erro ao submeter o plano:', erro);

        this.processando.set(false);

        this.erroAcao.set(
          this.mensagemErro(
            erro,
            'Não foi possível submeter o plano de estágio.'
          )
        );

      }

    });

  }


  // ==========================================================
  // CONTRATO
  // ==========================================================

  private carregarDocumentos(idEstagio: number): void {

    this.carregandoDocumentos.set(true);

    this.estagioService.documentos(idEstagio).subscribe({

      next: resposta => {

        this.documentos.set(resposta.documentos ?? []);

        this.carregandoDocumentos.set(false);

      },

      error: erro => {

        console.error('Erro ao carregar documentos:', erro);

        this.documentos.set([]);

        this.carregandoDocumentos.set(false);

        this.erroAcao.set(
          'Não foi possível carregar os documentos.'
        );

      }

    });

  }


  validarDocumento(documento: DocumentoContrato): void {

    const idEstagio = this.estagioId();

    if (idEstagio === null || this.processando()) {

      return;

    }

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.estagioService
      .validarDocumento(idEstagio, documento.id_documento_estagio)
      .subscribe({

        next: resposta => {

          this.processando.set(false);

          this.sucesso.set(
            resposta.message || 'Documento validado.'
          );

          this.carregarDocumentos(idEstagio);

        },

        error: erro => {

          console.error('Erro ao validar documento:', erro);

          this.processando.set(false);

          this.erroAcao.set(
            this.mensagemErro(
              erro,
              'Não foi possível validar o documento.'
            )
          );

        }

      });

  }


  abrirRejeicao(documento: DocumentoContrato): void {

    this.documentoEmRejeicao.set(documento);

    this.motivoRejeicao = '';

    this.sucesso.set(null);

    this.erroAcao.set(null);

  }


  cancelarRejeicao(): void {

    this.documentoEmRejeicao.set(null);

    this.motivoRejeicao = '';

    this.erroAcao.set(null);

  }


  confirmarRejeicao(): void {

    const idEstagio = this.estagioId();

    const documento = this.documentoEmRejeicao();

    if (idEstagio === null || !documento || this.processando()) {

      return;

    }

    const motivo = this.motivoRejeicao.trim();

    if (motivo.length < 5) {

      this.erroAcao.set(
        'Indica o motivo da rejeição (pelo menos 5 caracteres).'
      );

      return;

    }

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.estagioService
      .rejeitarDocumento(
        idEstagio,
        documento.id_documento_estagio,
        motivo
      )
      .subscribe({

        next: resposta => {

          this.processando.set(false);

          this.sucesso.set(
            resposta.message || 'Documento rejeitado.'
          );

          this.cancelarRejeicao();

          this.carregarDocumentos(idEstagio);

        },

        error: erro => {

          console.error('Erro ao rejeitar documento:', erro);

          this.processando.set(false);

          this.erroAcao.set(
            this.mensagemErro(
              erro,
              'Não foi possível rejeitar o documento.'
            )
          );

        }

      });

  }


  descarregarDocumento(documento: DocumentoContrato): void {

    if (!isPlatformBrowser(this.platformId)) {

      return;

    }

    const janela = window.open('', '_blank');

    if (!janela) {

      this.erroAcao.set(
        'O navegador bloqueou a abertura do documento.'
      );

      return;

    }

    this.estagioService
      .descarregarDocumento(documento.id_documento_estagio)
      .subscribe({

        next: blob => {

          const url = URL.createObjectURL(blob);

          janela.location.href = url;

          window.setTimeout(
            () => URL.revokeObjectURL(url),
            60000
          );

        },

        error: erro => {

          janela.close();

          console.error('Erro ao abrir documento:', erro);

          this.erroAcao.set(
            'Não foi possível abrir este documento.'
          );

        }

      });

  }


  // ==========================================================
  // RELATÓRIOS
  // ==========================================================

  private carregarRelatorios(idEstagio: number): void {

    this.carregandoRelatorios.set(true);

    this.estagioService.relatorios(idEstagio).subscribe({

      next: resposta => {

        this.relatorios.set(resposta.relatorios ?? []);

        this.carregandoRelatorios.set(false);

      },

      error: erro => {

        console.error('Erro ao carregar relatórios:', erro);

        this.relatorios.set([]);

        this.carregandoRelatorios.set(false);

        this.erroAcao.set(
          'Não foi possível carregar os relatórios.'
        );

      }

    });

  }


  // ==========================================================
  // ASSIDUIDADE
  // ==========================================================

  private carregarAssiduidade(idEstagio: number): void {

    this.carregandoAssiduidade.set(true);

    const filtros: FiltrosAssiduidade = {

      mes: this.mesFiltro || null,

      estado: this.estadoFiltro || null

    };

    this.estagioService
      .assiduidades(idEstagio, filtros)
      .subscribe({

        next: resposta => {

          this.assiduidades.set(resposta.assiduidades ?? []);

          this.resumoAssiduidade.set(
            resposta.resumo ?? RESUMO_ASSIDUIDADE_VAZIO
          );

          this.carregandoAssiduidade.set(false);

        },

        error: erro => {

          console.error('Erro ao carregar assiduidade:', erro);

          this.assiduidades.set([]);

          this.carregandoAssiduidade.set(false);

          this.erroAcao.set(
            'Não foi possível carregar a assiduidade.'
          );

        }

      });

  }


  aplicarFiltrosAssiduidade(): void {

    const idEstagio = this.estagioId();

    if (idEstagio !== null) {

      this.carregarAssiduidade(idEstagio);

    }

  }


  limparFiltrosAssiduidade(): void {

    this.mesFiltro = '';

    this.estadoFiltro = '';

    this.aplicarFiltrosAssiduidade();

  }


  guardarAssiduidade(): void {

    const idEstagio = this.estagioId();

    if (idEstagio === null || this.processando()) {

      return;

    }

    if (!this.assiduidadeData) {

      this.erroAcao.set('Indica a data do registo.');

      return;

    }

    const falta = this.assiduidadeEhFalta();

    if (
      this.assiduidadeEstado() === 'falta_justificada' &&
      this.assiduidadeJustificacao.trim().length < 5
    ) {

      this.erroAcao.set(
        'Descreve a justificação da falta (pelo menos 5 caracteres).'
      );

      return;

    }

    const dados: DadosAssiduidade = {

      data: this.assiduidadeData,

      estado: this.assiduidadeEstado(),

      hora_entrada: falta
        ? null
        : (this.assiduidadeHoraEntrada || null),

      hora_saida: falta
        ? null
        : (this.assiduidadeHoraSaida || null),

      justificacao:
        this.assiduidadeEstado() === 'falta_justificada'
          ? this.assiduidadeJustificacao.trim()
          : null

    };

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.estagioService
      .registarAssiduidade(idEstagio, dados)
      .subscribe({

        next: resposta => {

          this.processando.set(false);

          this.sucesso.set(
            resposta.message || 'Assiduidade registada.'
          );

          this.limparFormularioAssiduidade();

          this.carregarAssiduidade(idEstagio);

        },

        error: erro => {

          console.error('Erro ao registar assiduidade:', erro);

          this.processando.set(false);

          this.erroAcao.set(
            this.mensagemErro(
              erro,
              'Não foi possível registar a assiduidade.'
            )
          );

        }

      });

  }


  private limparFormularioAssiduidade(): void {

    this.assiduidadeData = '';

    this.assiduidadeEstado.set('presente');

    this.assiduidadeHoraEntrada = '';

    this.assiduidadeHoraSaida = '';

    this.assiduidadeJustificacao = '';

  }


  removerAssiduidade(assiduidade: Assiduidade): void {

    const idEstagio = this.estagioId();

    if (idEstagio === null || this.processando()) {

      return;

    }

    if (
      isPlatformBrowser(this.platformId) &&
      !window.confirm(
        `Remover o registo de ${this.formatarData(assiduidade.data)}?`
      )
    ) {

      return;

    }

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.estagioService
      .removerAssiduidade(idEstagio, assiduidade.id_assiduidade)
      .subscribe({

        next: resposta => {

          this.processando.set(false);

          this.sucesso.set(
            resposta.message || 'Registo removido.'
          );

          this.carregarAssiduidade(idEstagio);

        },

        error: erro => {

          console.error('Erro ao remover assiduidade:', erro);

          this.processando.set(false);

          this.erroAcao.set(
            this.mensagemErro(
              erro,
              'Não foi possível remover o registo.'
            )
          );

        }

      });

  }


  aprovarJustificacao(assiduidade: Assiduidade): void {

    const idEstagio = this.estagioId();

    if (idEstagio === null || this.processando()) {

      return;

    }

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.estagioService
      .aprovarJustificacao(idEstagio, assiduidade.id_assiduidade)
      .subscribe({

        next: resposta => {

          this.processando.set(false);

          this.sucesso.set(
            resposta.message || 'Justificação aprovada.'
          );

          this.carregarAssiduidade(idEstagio);

        },

        error: erro => {

          console.error('Erro ao aprovar justificação:', erro);

          this.processando.set(false);

          this.erroAcao.set(
            this.mensagemErro(
              erro,
              'Não foi possível aprovar a justificação.'
            )
          );

        }

      });

  }


  abrirRecusaJustificacao(assiduidade: Assiduidade): void {

    this.justificacaoEmRecusa.set(assiduidade);

    this.motivoRecusaJustificacao = '';

    this.sucesso.set(null);

    this.erroAcao.set(null);

  }


  cancelarRecusaJustificacao(): void {

    this.justificacaoEmRecusa.set(null);

    this.motivoRecusaJustificacao = '';

    this.erroAcao.set(null);

  }


  confirmarRecusaJustificacao(): void {

    const idEstagio = this.estagioId();

    const assiduidade = this.justificacaoEmRecusa();

    if (idEstagio === null || !assiduidade || this.processando()) {

      return;

    }

    const motivo = this.motivoRecusaJustificacao.trim();

    if (motivo.length < 5) {

      this.erroAcao.set(
        'Indica o motivo da recusa (pelo menos 5 caracteres).'
      );

      return;

    }

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.estagioService
      .recusarJustificacao(
        idEstagio,
        assiduidade.id_assiduidade,
        motivo
      )
      .subscribe({

        next: resposta => {

          this.processando.set(false);

          this.sucesso.set(
            resposta.message || 'Justificação recusada.'
          );

          this.cancelarRecusaJustificacao();

          this.carregarAssiduidade(idEstagio);

        },

        error: erro => {

          console.error('Erro ao recusar justificação:', erro);

          this.processando.set(false);

          this.erroAcao.set(
            this.mensagemErro(
              erro,
              'Não foi possível recusar a justificação.'
            )
          );

        }

      });

  }


  // ==========================================================
  // AVALIAÇÕES
  // ==========================================================

  private carregarAvaliacoes(idEstagio: number): void {

    this.carregandoAvaliacoes.set(true);

    this.estagioService.avaliacoes(idEstagio).subscribe({

      next: resposta => {

        this.avaliacoes.set(resposta.avaliacoes ?? []);

        this.resumoAvaliacoes.set(resposta.resumo ?? null);

        this.carregandoAvaliacoes.set(false);

      },

      error: erro => {

        console.error('Erro ao carregar avaliações:', erro);

        this.avaliacoes.set([]);

        this.resumoAvaliacoes.set(null);

        this.carregandoAvaliacoes.set(false);

        this.erroAcao.set(
          'Não foi possível carregar as avaliações.'
        );

      }

    });

  }


  private validarNota(
    valor: number | null,
    campo: string
  ): string | null {

    if (valor === null || valor === undefined) {

      return null;

    }

    if (!Number.isFinite(valor) || valor < 0 || valor > 20) {

      return `O campo "${campo}" deve estar entre 0 e 20.`;

    }

    return null;

  }


  guardarAvaliacao(): void {

    const idEstagio = this.estagioId();

    if (idEstagio === null || this.processando()) {

      return;

    }

    if (!this.avaliacaoPeriodo) {

      this.erroAcao.set('Indica o período de referência.');

      return;

    }

    const nota = this.avaliacaoNota;

    if (
      nota === null ||
      !Number.isFinite(nota) ||
      nota < 0 ||
      nota > 20
    ) {

      this.erroAcao.set('A nota final deve estar entre 0 e 20.');

      return;

    }

    const erroSubnota =
      this.validarNota(this.avaliacaoNotaAtividades, 'Atividades') ??
      this.validarNota(this.avaliacaoNotaAssiduidade, 'Assiduidade') ??
      this.validarNota(this.avaliacaoNotaPontualidade, 'Pontualidade');

    if (erroSubnota) {

      this.erroAcao.set(erroSubnota);

      return;

    }

    if (
      this.avaliacaoTipo === 'semanal' &&
      (
        this.avaliacaoPontosFortes.trim() ||
        this.avaliacaoAreasMelhoria.trim()
      )
    ) {

      this.erroAcao.set(
        'Os pontos fortes e as áreas de melhoria só se aplicam às avaliações mensal e final.'
      );

      return;

    }

    const dados: DadosAvaliacao = {

      tipo: this.avaliacaoTipo,

      periodo_referencia: this.avaliacaoPeriodo,

      nota: nota,

      nota_atividades: this.avaliacaoNotaAtividades,

      nota_assiduidade: this.avaliacaoNotaAssiduidade,

      nota_pontualidade: this.avaliacaoNotaPontualidade,

      comentarios: this.avaliacaoComentarios.trim() || null,

      pontos_fortes:
        this.avaliacaoTipo === 'semanal'
          ? null
          : (this.avaliacaoPontosFortes.trim() || null),

      areas_melhoria:
        this.avaliacaoTipo === 'semanal'
          ? null
          : (this.avaliacaoAreasMelhoria.trim() || null)

    };

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.estagioService
      .registarAvaliacao(idEstagio, dados)
      .subscribe({

        next: resposta => {

          this.processando.set(false);

          this.sucesso.set(
            resposta.message || 'Avaliação registada.'
          );

          this.limparFormularioAvaliacao();

          this.carregarAvaliacoes(idEstagio);

        },

        error: erro => {

          console.error('Erro ao registar avaliação:', erro);

          this.processando.set(false);

          this.erroAcao.set(
            this.mensagemErro(
              erro,
              'Não foi possível registar a avaliação.'
            )
          );

        }

      });

  }


  private limparFormularioAvaliacao(): void {

    this.avaliacaoPeriodo = '';

    this.avaliacaoNota = null;

    this.avaliacaoNotaAtividades = null;

    this.avaliacaoNotaAssiduidade = null;

    this.avaliacaoNotaPontualidade = null;

    this.avaliacaoComentarios = '';

    this.avaliacaoPontosFortes = '';

    this.avaliacaoAreasMelhoria = '';

  }


  removerAvaliacao(avaliacao: Avaliacao): void {

    const idEstagio = this.estagioId();

    if (idEstagio === null || this.processando()) {

      return;

    }

    if (
      isPlatformBrowser(this.platformId) &&
      !window.confirm(
        `Remover a avaliação ${this.rotuloTipo(avaliacao.tipo)} de ${this.formatarData(avaliacao.periodo_referencia)}?`
      )
    ) {

      return;

    }

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.estagioService
      .removerAvaliacao(idEstagio, avaliacao.id_avaliacao)
      .subscribe({

        next: resposta => {

          this.processando.set(false);

          this.sucesso.set(
            resposta.message || 'Avaliação removida.'
          );

          this.carregarAvaliacoes(idEstagio);

        },

        error: erro => {

          console.error('Erro ao remover avaliação:', erro);

          this.processando.set(false);

          this.erroAcao.set(
            this.mensagemErro(
              erro,
              'Não foi possível remover a avaliação.'
            )
          );

        }

      });

  }


  // ==========================================================
  // FEEDBACK E ATIVIDADES
  // ==========================================================

  private carregarFeedback(idEstagio: number): void {

    this.carregandoFeedback.set(true);

    this.estagioService.feedback(idEstagio).subscribe({

      next: resposta => {

        this.feedback.set(resposta.feedback);

        this.carregandoFeedback.set(false);

      },

      error: erro => {

        console.error('Erro ao carregar feedback:', erro);

        this.feedback.set(null);

        this.carregandoFeedback.set(false);

        this.erroAcao.set(
          'Não foi possível carregar o feedback.'
        );

      }

    });

  }


  private carregarAtividades(idEstagio: number): void {

    this.carregandoAtividades.set(true);

    this.estagioService.atividades(idEstagio).subscribe({

      next: resposta => {

        this.atividades.set(resposta.atividades ?? []);

        this.carregandoAtividades.set(false);

      },

      error: erro => {

        console.error('Erro ao carregar atividades:', erro);

        this.atividades.set([]);

        this.carregandoAtividades.set(false);

        this.erroAcao.set(
          'Não foi possível carregar as atividades.'
        );

      }

    });

  }


  // ==========================================================
  // APRESENTAÇÃO
  // ==========================================================

  nomeEstagio(estagio: EstagioSupervisionado): string {

    return estagio.estagiario?.nome
      || estagio.candidatura?.nome
      || `Estágio #${estagio.id_estagio}`;

  }


  rotuloTipo(tipo: string | null): string {

    switch (tipo) {

      case 'semanal':
        return 'Semanal';

      case 'mensal':
        return 'Mensal';

      case 'final':
        return 'Final';

      default:
        return tipo || '—';

    }

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


  rotuloEstadoPlano(estado: string | null): string {

    switch (estado) {

      case 'submetido':
        return 'Aguarda aprovação';

      case 'aprovado':
        return 'Aprovado';

      case 'recusado':
        return 'Recusado';

      case 'substituido':
        return 'Substituído';

      default:
        return estado || 'Sem plano';

    }

  }


  rotuloEstadoDocumento(estado: string): string {

    switch (estado) {

      case 'validado':
        return 'Validado';

      case 'rejeitado':
        return 'Rejeitado';

      case 'pendente':
        return 'Pendente';

      default:
        return estado;

    }

  }


  rotuloAssiduidade(estado: string): string {

    switch (estado) {

      case 'presente':
        return 'Presente';

      case 'atraso':
        return 'Atraso';

      case 'falta':
        return 'Falta';

      case 'falta_justificada':
        return 'Falta justificada';

      default:
        return estado;

    }

  }


  classeAssiduidade(estado: string): string {

    switch (estado) {

      case 'presente':
        return 'es-dot--ok';

      case 'atraso':
        return 'es-dot--aviso';

      case 'falta':
        return 'es-dot--falta';

      case 'falta_justificada':
        return 'es-dot--justificada';

      default:
        return '';

    }

  }


  rotuloJustificacao(estado: string | null): string {

    switch (estado) {

      case 'pendente':
        return 'Em análise';

      case 'aprovada':
        return 'Aprovada';

      case 'recusada':
        return 'Recusada';

      default:
        return 'Sem justificação';

    }

  }


  formatarData(valor: string | null | undefined): string {

    if (!valor) {

      return '—';

    }

    const data = new Date(valor);

    if (Number.isNaN(data.getTime())) {

      return valor;

    }

    return data.toLocaleDateString('pt-PT');

  }


  formatarHora(valor: string | null): string {

    if (!valor) {

      return '—';

    }

    return valor.substring(0, 5);

  }


  formatarNota(valor: string | null): string {

    if (valor === null || valor === undefined || valor === '') {

      return '—';

    }

    return valor;

  }


  // ==========================================================
  // AUXILIARES DE ERRO
  // ==========================================================

  private mensagemErro(
    erro: any,
    mensagemPadrao: string
  ): string {

    if (erro?.status === 422 && erro?.error?.errors) {

      const mensagens = Object.values(
        erro.error.errors
      ) as string[][];

      const primeira = mensagens
        .flat()
        .find(mensagem => typeof mensagem === 'string');

      if (primeira) {

        return primeira;

      }

    }

    if (erro?.status === 401) {

      return 'A sessão expirou. Inicia sessão novamente.';

    }

    if (erro?.status === 403) {

      return (
        erro?.error?.message ||
        'Não tens permissão para esta operação.'
      );

    }

    return erro?.error?.message || mensagemPadrao;

  }

}
