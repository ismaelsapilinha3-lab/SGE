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
  EstagioCoordenado,
  EstagioCoordenadorService,
  ResumoAssiduidade,
  SupervisorVinculo
} from '../../../services/estagio-coordenador.service';

import {
  PlanoEstagio,
  PlanoHistorico
} from '../../../services/estagio-supervisor.service';


// ============================================================
// TIPOS DAS ABAS
// ============================================================

type AbaCoordenador =
  | 'plano'
  | 'contrato'
  | 'relatorios'
  | 'assiduidade'
  | 'avaliacoes'
  | 'feedback'
  | 'atividades';


interface AbaDefinicao {

  id: AbaCoordenador;

  rotulo: string;

}


const ABAS: AbaDefinicao[] = [

  { id: 'plano', rotulo: 'Plano' },

  { id: 'contrato', rotulo: 'Contrato' },

  { id: 'relatorios', rotulo: 'Relatórios' },

  { id: 'assiduidade', rotulo: 'Assiduidade' },

  { id: 'avaliacoes', rotulo: 'Avaliações' },

  { id: 'feedback', rotulo: 'Feedback' },

  { id: 'atividades', rotulo: 'Atividades' }

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
  selector: 'app-estagio-coordenador',
  standalone: true,

  imports: [
    CommonModule,
    FormsModule
  ],

  templateUrl: './estagio-coordenador.html',

  styleUrl: './estagio-coordenador.css'
})
export class EstagioCoordenadorComponent {

  private readonly estagioService =
    inject(EstagioCoordenadorService);

  private readonly platformId = inject(PLATFORM_ID);


  // ==========================================================
  // ESTÁGIOS DA DIREÇÃO
  // ==========================================================

  readonly carregando = signal(true);

  readonly erro = signal<string | null>(null);

  readonly estagios = signal<EstagioCoordenado[]>([]);

  readonly estagioSelecionadoId = signal<number | null>(null);

  pesquisaEstagios = '';

  estadoFiltroEstagios = '';

  readonly pagina = signal(1);

  readonly ultimaPagina = signal(1);

  readonly totalEstagios = signal(0);

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

  readonly aba = signal<AbaCoordenador>('plano');

  readonly sucesso = signal<string | null>(null);

  readonly erroAcao = signal<string | null>(null);

  readonly processando = signal(false);

  private readonly abasCarregadas = new Set<AbaCoordenador>();


  // ==========================================================
  // PLANO
  // ==========================================================

  readonly plano = signal<PlanoEstagio | null>(null);

  readonly historicoPlano = signal<PlanoHistorico[]>([]);

  readonly carregandoPlano = signal(false);

  readonly recusaAberta = signal(false);

  motivoRecusaPlano = '';


  // ==========================================================
  // CONTRATO
  // ==========================================================

  readonly documentos = signal<DocumentoContrato[]>([]);

  readonly carregandoDocumentos = signal(false);


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


  // ==========================================================
  // AVALIAÇÕES
  // ==========================================================

  readonly avaliacoes = signal<Avaliacao[]>([]);

  readonly carregandoAvaliacoes = signal(false);

  readonly mediaAvaliacoes = signal<number | null>(null);


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

  readonly planoSubmetido = computed(() => {

    return this.plano()?.estado === 'submetido';

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

    this.estagioService
      .estagios(
        this.pesquisaEstagios,
        this.estadoFiltroEstagios,
        this.pagina()
      )
      .subscribe({

        next: resposta => {

          const estagios = resposta.data ?? [];

          this.estagios.set(estagios);

          this.ultimaPagina.set(
            Math.max(resposta.last_page ?? 1, 1)
          );

          this.totalEstagios.set(resposta.total ?? 0);

          this.carregando.set(false);

          if (estagios.length > 0) {

            const atual = this.estagioSelecionadoId();

            const existe = estagios.some(
              estagio => estagio.id_estagio === atual
            );

            this.selecionarEstagio(
              existe && atual !== null
                ? atual
                : estagios[0].id_estagio
            );

          } else {

            this.estagioSelecionadoId.set(null);

          }

        },

        error: erro => {

          console.error('Erro ao carregar estágios:', erro);

          this.estagios.set([]);

          this.carregando.set(false);

          this.erro.set(
            this.mensagemErro(
              erro,
              'Não foi possível carregar os estágios da direção.'
            )
          );

        }

      });

  }


  aplicarFiltros(): void {

    this.pagina.set(1);

    this.estagioSelecionadoId.set(null);

    this.carregarEstagios();

  }


  paginaAnterior(): void {

    if (this.pagina() <= 1) {

      return;

    }

    this.pagina.set(this.pagina() - 1);

    this.carregarEstagios();

  }


  proximaPagina(): void {

    if (this.pagina() >= this.ultimaPagina()) {

      return;

    }

    this.pagina.set(this.pagina() + 1);

    this.carregarEstagios();

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


  aoMudarEstagio(event: Event): void {

    const select = event.target as HTMLSelectElement;

    const id = Number(select.value);

    if (!Number.isNaN(id)) {

      this.selecionarEstagio(id);

    }

  }


  private limparDados(): void {

    this.plano.set(null);

    this.historicoPlano.set([]);

    this.recusaAberta.set(false);

    this.motivoRecusaPlano = '';

    this.documentos.set([]);

    this.relatorios.set([]);

    this.assiduidades.set([]);

    this.resumoAssiduidade.set(RESUMO_ASSIDUIDADE_VAZIO);

    this.avaliacoes.set([]);

    this.mediaAvaliacoes.set(null);

    this.feedback.set(null);

    this.atividades.set([]);

  }


  // ==========================================================
  // NAVEGAÇÃO ENTRE ABAS
  // ==========================================================

  definirAba(aba: AbaCoordenador): void {

    this.aba.set(aba);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.carregarDadosAba(aba);

  }


  private carregarDadosAba(aba: AbaCoordenador): void {

    const estagio = this.estagioSelecionado();

    if (!estagio || this.abasCarregadas.has(aba)) {

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

        this.historicoPlano.set(resposta.historico ?? []);

        this.carregandoPlano.set(false);

      },

      error: erro => {

        console.error('Erro ao carregar plano:', erro);

        this.plano.set(null);

        this.historicoPlano.set([]);

        this.carregandoPlano.set(false);

        this.erroAcao.set(
          'Não foi possível carregar o plano de estágio.'
        );

      }

    });

  }


  aprovarPlano(): void {

    const idEstagio = this.estagioId();

    if (idEstagio === null || this.processando()) {

      return;

    }

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.estagioService.aprovarPlano(idEstagio).subscribe({

      next: resposta => {

        this.processando.set(false);

        this.sucesso.set(
          resposta.message || 'Plano de estágio aprovado.'
        );

        this.carregarPlano(idEstagio);

      },

      error: erro => {

        console.error('Erro ao aprovar plano:', erro);

        this.processando.set(false);

        this.erroAcao.set(
          this.mensagemErro(
            erro,
            'Não foi possível aprovar o plano de estágio.'
          )
        );

      }

    });

  }


  abrirRecusaPlano(): void {

    this.recusaAberta.set(true);

    this.motivoRecusaPlano = '';

    this.sucesso.set(null);

    this.erroAcao.set(null);

  }


  cancelarRecusaPlano(): void {

    this.recusaAberta.set(false);

    this.motivoRecusaPlano = '';

    this.erroAcao.set(null);

  }


  confirmarRecusaPlano(): void {

    const idEstagio = this.estagioId();

    if (idEstagio === null || this.processando()) {

      return;

    }

    const motivo = this.motivoRecusaPlano.trim();

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
      .recusarPlano(idEstagio, motivo)
      .subscribe({

        next: resposta => {

          this.processando.set(false);

          this.sucesso.set(
            resposta.message || 'Plano de estágio recusado.'
          );

          this.cancelarRecusaPlano();

          this.carregarPlano(idEstagio);

        },

        error: erro => {

          console.error('Erro ao recusar plano:', erro);

          this.processando.set(false);

          this.erroAcao.set(
            this.mensagemErro(
              erro,
              'Não foi possível recusar o plano de estágio.'
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

    this.estagioService.assiduidades(idEstagio).subscribe({

      next: resposta => {

        const registos = resposta.assiduidades ?? [];

        this.assiduidades.set(registos);

        this.resumoAssiduidade.set(
          this.calcularResumoAssiduidade(registos)
        );

        this.carregandoAssiduidade.set(false);

      },

      error: erro => {

        console.error('Erro ao carregar assiduidade:', erro);

        this.assiduidades.set([]);

        this.resumoAssiduidade.set(RESUMO_ASSIDUIDADE_VAZIO);

        this.carregandoAssiduidade.set(false);

        this.erroAcao.set(
          'Não foi possível carregar a assiduidade.'
        );

      }

    });

  }


  private calcularResumoAssiduidade(
    registos: Assiduidade[]
  ): ResumoAssiduidade {

    const resumo: ResumoAssiduidade = { ...RESUMO_ASSIDUIDADE_VAZIO };

    for (const registo of registos) {

      resumo.total += 1;

      switch (registo.estado) {

        case 'presente':
          resumo.presente += 1;
          break;

        case 'atraso':
          resumo.atraso += 1;
          break;

        case 'falta':
          resumo.falta += 1;
          break;

        case 'falta_justificada':
          resumo.falta_justificada += 1;
          break;

      }

      if (registo.justificacao_estado === 'pendente') {

        resumo.justificacoes_pendentes += 1;

      }

    }

    return resumo;

  }


  // ==========================================================
  // AVALIAÇÕES
  // ==========================================================

  private carregarAvaliacoes(idEstagio: number): void {

    this.carregandoAvaliacoes.set(true);

    this.estagioService.avaliacoes(idEstagio).subscribe({

      next: resposta => {

        const avaliacoes = resposta.avaliacoes ?? [];

        this.avaliacoes.set(avaliacoes);

        this.mediaAvaliacoes.set(
          this.calcularMediaAvaliacoes(avaliacoes)
        );

        this.carregandoAvaliacoes.set(false);

      },

      error: erro => {

        console.error('Erro ao carregar avaliações:', erro);

        this.avaliacoes.set([]);

        this.mediaAvaliacoes.set(null);

        this.carregandoAvaliacoes.set(false);

        this.erroAcao.set(
          'Não foi possível carregar as avaliações.'
        );

      }

    });

  }


  private calcularMediaAvaliacoes(
    avaliacoes: Avaliacao[]
  ): number | null {

    const notas = avaliacoes
      .map(avaliacao => Number(avaliacao.nota))
      .filter(nota => Number.isFinite(nota));

    if (notas.length === 0) {

      return null;

    }

    const soma = notas.reduce((total, nota) => total + nota, 0);

    return Math.round((soma / notas.length) * 100) / 100;

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

  nomeEstagio(estagio: EstagioCoordenado): string {

    return estagio.estagiario?.nome
      || estagio.candidatura?.nome
      || `Estágio #${estagio.id_estagio}`;

  }


  nomesSupervisores(estagio: EstagioCoordenado): string {

    const supervisores = estagio.supervisores_ativos ?? [];

    const nomes = supervisores
      .map((vinculo: SupervisorVinculo) => vinculo.supervisor?.nome)
      .filter((nome): nome is string => Boolean(nome));

    return nomes.length > 0
      ? nomes.join(', ')
      : 'Sem supervisor atribuído';

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
        return 'ec-dot--ok';

      case 'atraso':
        return 'ec-dot--aviso';

      case 'falta':
        return 'ec-dot--falta';

      case 'falta_justificada':
        return 'ec-dot--justificada';

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
