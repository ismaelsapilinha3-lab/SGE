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
  DadosAtividade,
  DadosFeedback,
  DadosRelatorio,
  DocumentoContrato,
  Estagio,
  EstagioEstagiarioService,
  Feedback,
  Relatorio
} from '../../../services/estagio-estagiario.service';


// ============================================================
// TIPOS DAS ABAS
// ============================================================

type AbaEstagio =
  | 'contrato'
  | 'relatorios'
  | 'assiduidade'
  | 'atividades'
  | 'avaliacoes'
  | 'feedback';


interface AbaDefinicao {

  id: AbaEstagio;

  rotulo: string;

}


const ABAS: AbaDefinicao[] = [

  { id: 'contrato', rotulo: 'Contrato' },

  { id: 'relatorios', rotulo: 'Relatórios' },

  { id: 'assiduidade', rotulo: 'Assiduidade' },

  { id: 'atividades', rotulo: 'Atividades' },

  { id: 'avaliacoes', rotulo: 'Avaliações' },

  { id: 'feedback', rotulo: 'Feedback' }

];


// ============================================================
// COMPONENTE
// ============================================================

@Component({
  selector: 'app-estagio-estagiario',
  standalone: true,

  imports: [
    CommonModule,
    FormsModule
  ],

  templateUrl: './estagio-estagiario.html',

  styleUrl: './estagio-estagiario.css'
})
export class EstagioEstagiarioComponent {

  private readonly estagioService =
    inject(EstagioEstagiarioService);

  private readonly platformId = inject(PLATFORM_ID);


  // ==========================================================
  // ABAS
  // ==========================================================

  readonly abas = ABAS;

  readonly aba = signal<AbaEstagio>('contrato');


  // ==========================================================
  // ESTADO GERAL
  // ==========================================================

  readonly carregando = signal(true);

  readonly erro = signal<string | null>(null);

  /** Verdadeiro quando o estagiário ainda não tem estágio associado. */
  readonly semEstagio = signal(false);

  readonly sucesso = signal<string | null>(null);

  readonly erroAcao = signal<string | null>(null);

  readonly processando = signal(false);

  readonly estagio = signal<Estagio | null>(null);

  private readonly abasCarregadas = new Set<AbaEstagio>();


  // ==========================================================
  // RELATÓRIOS
  // ==========================================================

  readonly relatorios = signal<Relatorio[]>([]);

  readonly carregandoRelatorios = signal(false);

  readonly relatorioEmEdicao = signal<Relatorio | null>(null);

  relatorioTipo: Relatorio['tipo'] = 'semanal';

  relatorioPeriodo = '';

  relatorioAtividades = '';

  relatorioResultados = '';

  relatorioConstrangimentos = '';

  relatorioObjetivos = '';


  // ==========================================================
  // ASSIDUIDADE
  // ==========================================================

  readonly assiduidades = signal<Assiduidade[]>([]);

  readonly carregandoAssiduidade = signal(false);

  readonly faltaEmJustificacao =
    signal<Assiduidade | null>(null);

  justificacaoTexto = '';


  // ==========================================================
  // ATIVIDADES
  // ==========================================================

  readonly atividades = signal<Atividade[]>([]);

  readonly carregandoAtividades = signal(false);

  readonly atividadeEmEdicao = signal<Atividade | null>(null);

  atividadeData = '';

  atividadeTitulo = '';

  atividadeDescricao = '';


  // ==========================================================
  // AVALIAÇÕES
  // ==========================================================

  readonly avaliacoes = signal<Avaliacao[]>([]);

  readonly carregandoAvaliacoes = signal(false);

  readonly mediaNotas = computed(() => {

    const notas = this.avaliacoes()
      .map(avaliacao => Number(avaliacao.nota))
      .filter(nota => Number.isFinite(nota));

    if (notas.length === 0) {

      return null;

    }

    const soma = notas.reduce(
      (total, nota) => total + nota,
      0
    );

    return soma / notas.length;

  });


  // ==========================================================
  // FEEDBACK
  // ==========================================================

  readonly feedback = signal<Feedback | null>(null);

  readonly carregandoFeedback = signal(false);

  feedbackNota: number | null = null;

  feedbackComentario = '';

  feedbackSugestoes = '';


  // ==========================================================
  // CONTRATO
  // ==========================================================

  readonly contratos = computed(() => {

    const documentos = this.estagio()?.documentos ?? [];

    return [...documentos]
      .filter(documento => documento.tipo_documento === 'contrato')
      .sort((a, b) => b.versao - a.versao);

  });

  readonly contratoAtual = computed(() => {

    return this.contratos()[0] ?? null;

  });


  // ==========================================================
  // PERMISSÕES DE ESCRITA
  // ==========================================================

  readonly estagioAtivo = computed(() => {

    return this.estagio()?.estado === 'ativo';

  });

  readonly aceitaRegistosDeFecho = computed(() => {

    const estado = this.estagio()?.estado;

    return estado !== null && estado !== 'cancelado';

  });


  // ==========================================================
  // CONSTRUTOR
  // ==========================================================

  constructor() {

    if (isPlatformBrowser(this.platformId)) {

      this.carregarEstagio();

    }

  }


  // ==========================================================
  // CARREGAR ESTÁGIO
  // ==========================================================

  private carregarEstagio(): void {

    this.carregando.set(true);

    this.erro.set(null);

    this.semEstagio.set(false);

    this.estagioService.obter().subscribe({

      next: resposta => {

        this.estagio.set(resposta.estagio);

        this.carregando.set(false);

        this.carregarDadosAba(this.aba());

      },

      error: erro => {

        console.error('Erro ao carregar o estágio:', erro);

        this.estagio.set(null);

        this.carregando.set(false);

        const status = this.obterStatus(erro);

        if (status === 404) {

          this.semEstagio.set(true);

          return;

        }

        this.erro.set(
          this.mensagemErro(
            erro,
            'Não foi possível carregar os dados do estágio.'
          )
        );

      }

    });

  }


  recarregar(): void {

    this.abasCarregadas.clear();

    this.carregarEstagio();

  }


  // ==========================================================
  // NAVEGAÇÃO ENTRE ABAS
  // ==========================================================

  definirAba(aba: AbaEstagio): void {

    this.aba.set(aba);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.carregarDadosAba(aba);

  }


  private carregarDadosAba(aba: AbaEstagio): void {

    if (!this.estagio()) {

      return;

    }

    if (aba === 'contrato') {

      return;

    }

    if (this.abasCarregadas.has(aba)) {

      return;

    }

    this.abasCarregadas.add(aba);

    switch (aba) {

      case 'relatorios':
        this.carregarRelatorios();
        break;

      case 'assiduidade':
        this.carregarAssiduidade();
        break;

      case 'atividades':
        this.carregarAtividades();
        break;

      case 'avaliacoes':
        this.carregarAvaliacoes();
        break;

      case 'feedback':
        this.carregarFeedback();
        break;

    }

  }


  // ==========================================================
  // CONTRATO
  // ==========================================================

  selecionarContrato(event: Event): void {

    const input = event.target as HTMLInputElement;

    const ficheiro = input.files?.[0];

    input.value = '';

    if (!ficheiro) {

      return;

    }

    if (this.processando()) {

      return;

    }

    const extensao = ficheiro.name
      .split('.')
      .pop()
      ?.toLowerCase();

    if (
      !extensao ||
      !['pdf', 'jpg', 'jpeg', 'png'].includes(extensao)
    ) {

      this.erroAcao.set(
        'Seleciona um ficheiro PDF, JPG ou PNG.'
      );

      return;

    }

    if (ficheiro.size > 5 * 1024 * 1024) {

      this.erroAcao.set(
        'O ficheiro não pode ultrapassar 5 MB.'
      );

      return;

    }

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.estagioService.submeterContrato(ficheiro).subscribe({

      next: resposta => {

        this.processando.set(false);

        this.sucesso.set(
          resposta.message ||
          'Contrato carregado com sucesso.'
        );

        this.carregarEstagio();

      },

      error: erro => {

        console.error('Erro ao carregar o contrato:', erro);

        this.processando.set(false);

        this.erroAcao.set(
          this.mensagemErro(
            erro,
            'Não foi possível carregar o contrato.'
          )
        );

      }

    });

  }


  descarregarContrato(documento: DocumentoContrato): void {

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
      .descarregarContrato(documento.id_documento_estagio)
      .subscribe({

        next: blob => {

          const url = URL.createObjectURL(blob);

          janela.location.href = url;

          window.setTimeout(() => URL.revokeObjectURL(url), 60000);

        },

        error: erro => {

          janela.close();

          console.error('Erro ao abrir o contrato:', erro);

          this.erroAcao.set(
            'Não foi possível abrir este documento.'
          );

        }

      });

  }


  // ==========================================================
  // RELATÓRIOS
  // ==========================================================

  private carregarRelatorios(): void {

    this.carregandoRelatorios.set(true);

    this.estagioService.relatorios().subscribe({

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


  novoRelatorio(): void {

    this.limparFormularioRelatorio();

    this.sucesso.set(null);

    this.erroAcao.set(null);

  }


  private limparFormularioRelatorio(): void {

    this.relatorioEmEdicao.set(null);

    this.relatorioTipo = 'semanal';

    this.relatorioPeriodo = '';

    this.relatorioAtividades = '';

    this.relatorioResultados = '';

    this.relatorioConstrangimentos = '';

    this.relatorioObjetivos = '';

  }


  editarRelatorio(relatorio: Relatorio): void {

    this.relatorioEmEdicao.set(relatorio);

    this.relatorioTipo = relatorio.tipo;

    this.relatorioPeriodo =
      relatorio.periodo_referencia?.substring(0, 10) ?? '';

    this.relatorioAtividades = relatorio.atividades;

    this.relatorioResultados = relatorio.resultados;

    this.relatorioConstrangimentos =
      relatorio.constrangimentos ?? '';

    this.relatorioObjetivos = relatorio.objetivos ?? '';

    this.sucesso.set(null);

    this.erroAcao.set(null);

  }


  cancelarRelatorio(): void {

    this.relatorioEmEdicao.set(null);

    this.erroAcao.set(null);

  }


  guardarRelatorio(): void {

    if (this.processando()) {

      return;

    }

    if (
      !this.relatorioPeriodo ||
      !this.relatorioAtividades.trim() ||
      !this.relatorioResultados.trim()
    ) {

      this.erroAcao.set(
        'Preenche o período, as atividades e os resultados.'
      );

      return;

    }

    const dados: DadosRelatorio = {

      tipo: this.relatorioTipo,

      periodo_referencia: this.relatorioPeriodo,

      atividades: this.relatorioAtividades.trim(),

      resultados: this.relatorioResultados.trim(),

      constrangimentos:
        this.relatorioConstrangimentos.trim() || null,

      objetivos: this.relatorioObjetivos.trim() || null

    };

    const emEdicao = this.relatorioEmEdicao();

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    const pedido = emEdicao
      ? this.estagioService.atualizarRelatorio(
        emEdicao.id_relatorio,
        dados
      )
      : this.estagioService.criarRelatorio(dados);

    pedido.subscribe({

      next: resposta => {

        this.processando.set(false);

        this.sucesso.set(
          resposta.message || 'Relatório guardado.'
        );

        this.limparFormularioRelatorio();

        this.carregarRelatorios();

      },

      error: erro => {

        console.error('Erro ao guardar relatório:', erro);

        this.processando.set(false);

        this.erroAcao.set(
          this.mensagemErro(
            erro,
            'Não foi possível guardar o relatório.'
          )
        );

      }

    });

  }


  submeterRelatorio(relatorio: Relatorio): void {

    if (this.processando()) {

      return;

    }

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.estagioService
      .submeterRelatorio(relatorio.id_relatorio)
      .subscribe({

        next: resposta => {

          this.processando.set(false);

          this.sucesso.set(
            resposta.message || 'Relatório submetido.'
          );

          if (
            this.relatorioEmEdicao()?.id_relatorio ===
            relatorio.id_relatorio
          ) {

            this.limparFormularioRelatorio();

          }

          this.carregarRelatorios();

        },

        error: erro => {

          console.error('Erro ao submeter relatório:', erro);

          this.processando.set(false);

          this.erroAcao.set(
            this.mensagemErro(
              erro,
              'Não foi possível submeter o relatório.'
            )
          );

        }

      });

  }


  // ==========================================================
  // ASSIDUIDADE
  // ==========================================================

  private carregarAssiduidade(): void {

    this.carregandoAssiduidade.set(true);

    this.estagioService.assiduidades().subscribe({

      next: resposta => {

        this.assiduidades.set(resposta.assiduidades ?? []);

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


  podeJustificar(assiduidade: Assiduidade): boolean {

    return this.estagioAtivo() &&
      assiduidade.estado === 'falta' &&
      assiduidade.justificacao_estado !== 'pendente';

  }


  abrirJustificacao(assiduidade: Assiduidade): void {

    this.faltaEmJustificacao.set(assiduidade);

    this.justificacaoTexto = assiduidade.justificacao ?? '';

    this.sucesso.set(null);

    this.erroAcao.set(null);

  }


  cancelarJustificacao(): void {

    this.faltaEmJustificacao.set(null);

    this.justificacaoTexto = '';

    this.erroAcao.set(null);

  }


  guardarJustificacao(): void {

    const assiduidade = this.faltaEmJustificacao();

    if (!assiduidade || this.processando()) {

      return;

    }

    const texto = this.justificacaoTexto.trim();

    if (texto.length < 5) {

      this.erroAcao.set(
        'A justificação deve ter pelo menos 5 caracteres.'
      );

      return;

    }

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.estagioService
      .justificarFalta(assiduidade.id_assiduidade, texto)
      .subscribe({

        next: resposta => {

          this.processando.set(false);

          this.sucesso.set(
            resposta.message ||
            'Justificação submetida para validação.'
          );

          this.faltaEmJustificacao.set(null);

          this.justificacaoTexto = '';

          this.carregarAssiduidade();

        },

        error: erro => {

          console.error('Erro ao justificar falta:', erro);

          this.processando.set(false);

          this.erroAcao.set(
            this.mensagemErro(
              erro,
              'Não foi possível submeter a justificação.'
            )
          );

        }

      });

  }


  // ==========================================================
  // ATIVIDADES
  // ==========================================================

  private carregarAtividades(): void {

    this.carregandoAtividades.set(true);

    this.estagioService.atividades().subscribe({

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


  novaAtividade(): void {

    this.limparFormularioAtividade();

    this.sucesso.set(null);

    this.erroAcao.set(null);

  }


  private limparFormularioAtividade(): void {

    this.atividadeEmEdicao.set(null);

    this.atividadeData = '';

    this.atividadeTitulo = '';

    this.atividadeDescricao = '';

  }


  editarAtividade(atividade: Atividade): void {

    this.atividadeEmEdicao.set(atividade);

    this.atividadeData =
      atividade.data?.substring(0, 10) ?? '';

    this.atividadeTitulo = atividade.titulo;

    this.atividadeDescricao = atividade.descricao;

    this.sucesso.set(null);

    this.erroAcao.set(null);

  }


  cancelarAtividade(): void {

    this.atividadeEmEdicao.set(null);

    this.erroAcao.set(null);

  }


  guardarAtividade(): void {

    if (this.processando()) {

      return;

    }

    if (
      !this.atividadeData ||
      !this.atividadeTitulo.trim() ||
      !this.atividadeDescricao.trim()
    ) {

      this.erroAcao.set(
        'Preenche a data, o título e a descrição.'
      );

      return;

    }

    const dados: DadosAtividade = {

      data: this.atividadeData,

      titulo: this.atividadeTitulo.trim(),

      descricao: this.atividadeDescricao.trim()

    };

    const emEdicao = this.atividadeEmEdicao();

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    const pedido = emEdicao
      ? this.estagioService.atualizarAtividade(
        emEdicao.id_atividade,
        dados
      )
      : this.estagioService.criarAtividade(dados);

    pedido.subscribe({

      next: resposta => {

        this.processando.set(false);

        this.sucesso.set(
          resposta.message || 'Atividade guardada.'
        );

        this.limparFormularioAtividade();

        this.carregarAtividades();

      },

      error: erro => {

        console.error('Erro ao guardar atividade:', erro);

        this.processando.set(false);

        this.erroAcao.set(
          this.mensagemErro(
            erro,
            'Não foi possível guardar a atividade.'
          )
        );

      }

    });

  }


  removerAtividade(atividade: Atividade): void {

    if (this.processando()) {

      return;

    }

    if (
      isPlatformBrowser(this.platformId) &&
      !window.confirm(
        `Pretendes remover a atividade "${atividade.titulo}"?`
      )
    ) {

      return;

    }

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.estagioService
      .removerAtividade(atividade.id_atividade)
      .subscribe({

        next: resposta => {

          this.processando.set(false);

          this.sucesso.set(
            resposta.message || 'Atividade removida.'
          );

          if (
            this.atividadeEmEdicao()?.id_atividade ===
            atividade.id_atividade
          ) {

            this.limparFormularioAtividade();

          }

          this.carregarAtividades();

        },

        error: erro => {

          console.error('Erro ao remover atividade:', erro);

          this.processando.set(false);

          this.erroAcao.set(
            this.mensagemErro(
              erro,
              'Não foi possível remover a atividade.'
            )
          );

        }

      });

  }


  // ==========================================================
  // AVALIAÇÕES
  // ==========================================================

  private carregarAvaliacoes(): void {

    this.carregandoAvaliacoes.set(true);

    this.estagioService.avaliacoes().subscribe({

      next: resposta => {

        this.avaliacoes.set(resposta.avaliacoes ?? []);

        this.carregandoAvaliacoes.set(false);

      },

      error: erro => {

        console.error('Erro ao carregar avaliações:', erro);

        this.avaliacoes.set([]);

        this.carregandoAvaliacoes.set(false);

        this.erroAcao.set(
          'Não foi possível carregar as avaliações.'
        );

      }

    });

  }


  // ==========================================================
  // FEEDBACK
  // ==========================================================

  private carregarFeedback(): void {

    this.carregandoFeedback.set(true);

    this.estagioService.feedback().subscribe({

      next: resposta => {

        this.aplicarFeedback(resposta.feedback);

        this.carregandoFeedback.set(false);

      },

      error: erro => {

        console.error('Erro ao carregar feedback:', erro);

        this.carregandoFeedback.set(false);

        this.erroAcao.set(
          'Não foi possível carregar o feedback.'
        );

      }

    });

  }


  private aplicarFeedback(feedback: Feedback | null): void {

    this.feedback.set(feedback);

    this.feedbackNota = feedback?.nota_geral ?? null;

    this.feedbackComentario = feedback?.comentario ?? '';

    this.feedbackSugestoes = feedback?.sugestoes ?? '';

  }


  guardarFeedback(): void {

    if (this.processando()) {

      return;

    }

    const nota = this.feedbackNota;

    if (
      nota === null ||
      !Number.isInteger(nota) ||
      nota < 0 ||
      nota > 10
    ) {

      this.erroAcao.set(
        'A nota geral deve ser um número inteiro entre 0 e 10.'
      );

      return;

    }

    const dados: DadosFeedback = {

      nota_geral: nota,

      comentario: this.feedbackComentario.trim() || null,

      sugestoes: this.feedbackSugestoes.trim() || null

    };

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.estagioService.guardarFeedback(dados).subscribe({

      next: resposta => {

        this.processando.set(false);

        this.sucesso.set(
          resposta.message || 'Feedback guardado.'
        );

        this.aplicarFeedback(resposta.feedback);

      },

      error: erro => {

        console.error('Erro ao guardar feedback:', erro);

        this.processando.set(false);

        this.erroAcao.set(
          this.mensagemErro(
            erro,
            'Não foi possível guardar o feedback.'
          )
        );

      }

    });

  }


  // ==========================================================
  // APRESENTAÇÃO
  // ==========================================================

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
        return 'est-dot--ok';

      case 'atraso':
        return 'est-dot--aviso';

      case 'falta':
        return 'est-dot--falta';

      case 'falta_justificada':
        return 'est-dot--justificada';

      default:
        return '';

    }

  }


  rotuloEstadoContrato(estado: string): string {

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

  private obterStatus(erro: unknown): number | null {

    if (
      typeof erro === 'object' &&
      erro !== null &&
      'status' in erro &&
      typeof (erro as { status?: unknown }).status === 'number'
    ) {

      return (erro as { status: number }).status;

    }

    return null;

  }


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
