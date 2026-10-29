import {
  Component,
  OnDestroy,
  OnInit,
  inject,
  signal
} from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { PLATFORM_ID } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { Subscription, finalize } from 'rxjs';
import { AuthService } from '../../../core/auth.service';
import {
  AlertaEstagiario,
  DocumentoEstagiario,
  NovoRelatorio,
  PainelEstagio,
  PlanoEstagio,
  RelatorioEstagiario,
  RelatorioEstagiarioService,
  TipoRelatorio
} from '../../../services/relatorio-estagiario.service';

export type SeparadorId = 'geral' | 'relatorios' | 'documentos' | 'plano';

export interface Separador {
  id: SeparadorId;
  rotulo: string;
}

@Component({
  selector: 'app-area-reservada',
  standalone: true,
  templateUrl: './area-reservada.html',
  styleUrl: './area-reservada.css',
  imports: []
})
export class AreaReservadaComponent implements OnInit, OnDestroy {
  private readonly relatorioService = inject(RelatorioEstagiarioService);
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly platformId = inject(PLATFORM_ID);

  private readonly subscriptions = new Subscription();

  carregando = signal(true);
  erro = signal('');
  terminandoSessao = signal(false);

  painel = signal<PainelEstagio | null>(null);
  relatorios = signal<RelatorioEstagiario[]>([]);
  alertas = signal<AlertaEstagiario[]>([]);

  /* Separador activo do painel. */
  separadorAtivo = signal<SeparadorId>('geral');

  /* Os separadores fixos do painel. */
  readonly separadores: Separador[] = [
    { id: 'geral', rotulo: 'Visão geral' },
    { id: 'relatorios', rotulo: 'Relatórios' },
    { id: 'documentos', rotulo: 'Documentos' },
    { id: 'plano', rotulo: 'Plano de estágio' }
  ];

  /* Primeira forma de envio: formulário no portal. */
  tipoRelatorio = signal<TipoRelatorio>('mensal');
  mesReferencia = signal('');
  atividadesRelatorio = signal('');
  resultadosRelatorio = signal('');
  constrangimentosRelatorio = signal('');
  objetivosRelatorio = signal('');
  submetendoRelatorio = signal(false);
  mensagemRelatorio = signal('');
  erroRelatorio = signal('');

  /* Forma de envio escolhida no separador de relatórios. */
  modoEnvio = signal<'formulario' | 'ficheiro'>('formulario');

  /* Segunda forma de envio: ficheiro em PDF ou Word. */
  relatorioEmFicheiro = signal<number | null>(null);
  aCarregarFicheiro = signal(false);
  erroFicheiro = signal('');
  mensagemFicheiro = signal('');

  /* Documentos da candidatura. */
  aCarregarDocumento = signal<number | null>(null);
  erroDocumento = signal('');
  mensagemDocumento = signal('');

  ngOnInit(): void {
    this.subscriptions.add(
      this.route.queryParamMap.subscribe(parametros => {
        this.aplicarSeparadorSolicitado(
          parametros.get('separador')
        );
      })
    );

    this.carregarPainel();
  }

  ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }

  /**
   * Abre o separador indicado pelo destino de uma notificação.
   */
  private aplicarSeparadorSolicitado(valor: string | null): void {
    if (!valor) {
      return;
    }

    const separador = this.separadores.find(
      item => item.id === valor
    );

    if (separador) {
      this.separadorAtivo.set(separador.id);
    }
  }

  get plano(): PlanoEstagio | null {
    return this.painel()?.plano_estagio ?? null;
  }

  get documentos(): DocumentoEstagiario[] {
    return this.painel()?.documentos ?? [];
  }

  /* Contadores mostrados nas etiquetas dos separadores. */
  get totalRelatorios(): number {
    return this.relatorios().length;
  }

  get totalDocumentos(): number {
    return this.documentos.length;
  }

  get totalAvisos(): number {
    return this.alertas().length;
  }

  selecionarSeparador(id: SeparadorId): void {
    this.separadorAtivo.set(id);
    this.limparSeparadorSolicitado();
  }

  /**
   * Remove o separador pedido da URL para que o mesmo destino volte a
   * funcionar depois de o utilizador navegar manualmente.
   */
  private limparSeparadorSolicitado(): void {
    if (!this.route.snapshot.queryParamMap.has('separador')) {
      return;
    }

    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: { separador: null },
      queryParamsHandling: 'merge',
      replaceUrl: true
    });
  }

  /* =======================================================
     CARREGAR OS DADOS DO ESTÁGIO
     ======================================================= */

  carregarPainel(): void {
    this.carregando.set(true);
    this.erro.set('');

    this.relatorioService.carregarPainel()
      .pipe(finalize(() => this.carregando.set(false)))
      .subscribe({
        next: (dados) => {
          this.painel.set(dados);
          this.relatorios.set(dados.relatorios ?? []);
          this.alertas.set(dados.alertas ?? []);
        },
        error: (erro: unknown) => {
          const status = this.obterStatus(erro);

          if (status === 401) {
            this.erro.set('A sessão expirou. Entra novamente.');

            this.authService.terminarLocal();
            this.router.navigate(['/acesso']);

            return;
          }

          this.erro.set(
            status === 404
              ? 'Não existe um estágio ativo associado à tua conta.'
              : 'Não foi possível carregar os dados do estágio.'
          );
        }
      });
  }

  /* =======================================================
     PRIMEIRA FORMA: FORMULÁRIO
     ======================================================= */

  submeterRelatorio(): void {
    if (this.submetendoRelatorio()) {
      return;
    }

    const dados: NovoRelatorio = {
      tipo: this.tipoRelatorio(),
      mes_referencia: this.mesReferencia(),
      atividades: this.atividadesRelatorio().trim(),
      resultados: this.resultadosRelatorio().trim(),
      constrangimentos: this.constrangimentosRelatorio().trim(),
      objetivos: this.objetivosRelatorio().trim()
    };

    if (
      !dados.mes_referencia ||
      dados.atividades.length < 10 ||
      dados.resultados.length < 10
    ) {
      this.erroRelatorio.set(
        'Indica o mês e escreve pelo menos 10 caracteres nas actividades e nos resultados.'
      );
      this.mensagemRelatorio.set('');

      return;
    }

    this.submetendoRelatorio.set(true);
    this.erroRelatorio.set('');
    this.mensagemRelatorio.set('');

    this.relatorioService.submeterRelatorio(dados)
      .pipe(finalize(() => this.submetendoRelatorio.set(false)))
      .subscribe({
        next: (resposta) => {
          this.mensagemRelatorio.set(
            resposta.message || 'Relatório submetido com sucesso.'
          );

          this.mesReferencia.set('');
          this.atividadesRelatorio.set('');
          this.resultadosRelatorio.set('');
          this.constrangimentosRelatorio.set('');
          this.objetivosRelatorio.set('');

          this.carregarPainel();
        },
        error: (erro: unknown) => {
          this.erroRelatorio.set(this.lerMensagemErro(erro));
        }
      });
  }

  /* =======================================================
     SEGUNDA FORMA: FICHEIRO PDF / WORD
     ======================================================= */

  selecionarFicheiro(relatorio: RelatorioEstagiario, event: Event): void {
    const input = event.target as HTMLInputElement;
    const ficheiro = input.files?.[0];

    input.value = '';

    if (!ficheiro) {
      return;
    }

    this.enviarFicheiro(
      ficheiro,
      relatorio.tipo,
      relatorio.mes_referencia ?? undefined,
      relatorio.id_relatorio
    );
  }

  enviarNovoFicheiro(event: Event): void {
    const input = event.target as HTMLInputElement;
    const ficheiro = input.files?.[0];

    input.value = '';

    if (!ficheiro) {
      return;
    }

    const mes = this.mesReferencia() || this.mesAtual();

    this.enviarFicheiro(ficheiro, this.tipoRelatorio(), mes);
  }

  private enviarFicheiro(
    ficheiro: File,
    tipo: TipoRelatorio,
    mesReferencia?: string,
    relatorioId?: number
  ): void {
    if (this.aCarregarFicheiro()) {
      return;
    }

    const extensao = ficheiro.name.split('.').pop()?.toLowerCase();

    if (!extensao || !['pdf', 'doc', 'docx'].includes(extensao)) {
      this.erroFicheiro.set(
        'O relatório tem de estar em PDF ou Word (DOC/DOCX).'
      );
      this.mensagemFicheiro.set('');

      return;
    }

    if (ficheiro.size > 10 * 1024 * 1024) {
      this.erroFicheiro.set(
        'O ficheiro do relatório não pode ultrapassar 10 MB.'
      );
      this.mensagemFicheiro.set('');

      return;
    }

    this.aCarregarFicheiro.set(true);
    this.relatorioEmFicheiro.set(relatorioId ?? 0);
    this.erroFicheiro.set('');
    this.mensagemFicheiro.set('');

    this.relatorioService.carregarRelatorio(ficheiro, {
      tipo,
      mes_referencia: mesReferencia,
      relatorio_id: relatorioId
    })
      .pipe(finalize(() => {
        this.aCarregarFicheiro.set(false);
        this.relatorioEmFicheiro.set(null);
      }))
      .subscribe({
        next: (resposta) => {
          this.mensagemFicheiro.set(
            resposta.message || 'Relatório submetido com sucesso.'
          );

          this.carregarPainel();
        },
        error: (erro: unknown) => {
          this.erroFicheiro.set(this.lerMensagemErro(erro));
        }
      });
  }

  /* =======================================================
     VISUALIZAR FICHEIROS
     ======================================================= */

  verRelatorio(relatorio: RelatorioEstagiario): void {
    if (relatorio.ficheiro_url) {
      this.abrirFicheiro(relatorio.ficheiro_url);
    }
  }

  verPlano(plano: PlanoEstagio): void {
    this.abrirFicheiro(plano.url);
  }

  verDocumento(documento: DocumentoEstagiario): void {
    this.abrirFicheiro(documento.url);
  }

  selecionarDocumento(
    documento: DocumentoEstagiario,
    event: Event
  ): void {
    const input = event.target as HTMLInputElement;
    const ficheiro = input.files?.[0];

    input.value = '';

    if (!ficheiro) {
      return;
    }

    if (this.aCarregarDocumento()) {
      return;
    }

    const extensao = ficheiro.name.split('.').pop()?.toLowerCase();

    if (!extensao || !['pdf', 'jpg', 'jpeg', 'png'].includes(extensao)) {
      this.erroDocumento.set(
        'O documento tem de estar em PDF, JPG ou PNG.'
      );
      this.mensagemDocumento.set('');

      return;
    }

    this.aCarregarDocumento.set(documento.id_documento);
    this.erroDocumento.set('');
    this.mensagemDocumento.set('');

    this.relatorioService.substituirDocumento(documento.id_documento, ficheiro)
      .pipe(finalize(() => this.aCarregarDocumento.set(null)))
      .subscribe({
        next: (resposta) => {
          this.mensagemDocumento.set(
            resposta.message || 'Documento substituído com sucesso.'
          );

          this.carregarPainel();
        },
        error: (erro: unknown) => {
          this.erroDocumento.set(this.lerMensagemErro(erro));
        }
      });
  }

  private abrirFicheiro(url: string): void {
    if (!isPlatformBrowser(this.platformId)) {
      return;
    }

    /*
     * A janela é aberta logo no clique para o navegador
     * não bloquear a abertura.
     */
    const janela = window.open('', '_blank');

    if (!janela) {
      this.erro.set('O navegador bloqueou a abertura do documento.');

      return;
    }

    this.relatorioService.obterFicheiro(url).subscribe({
      next: (blob) => {
        const urlFicheiro = URL.createObjectURL(blob);

        janela.location.href = urlFicheiro;

        window.setTimeout(() => URL.revokeObjectURL(urlFicheiro), 60000);
      },
      error: (erro: unknown) => {
        janela.close();

        console.error('Erro ao abrir documento:', erro);

        this.erro.set('Não foi possível abrir este documento.');
      }
    });
  }

  /* =======================================================
     APOIO
     ======================================================= */

  mesAtual(): string {
    const hoje = new Date();

    return `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}`;
  }

  rotuloTipo(tipo: string): string {
    return tipo.charAt(0).toUpperCase() + tipo.slice(1);
  }

  mesLegivel(valor?: string | null): string {
    if (!valor) {
      return '-';
    }

    const partes = valor.split('-');

    if (partes.length < 2) {
      return valor;
    }

    const nomes = [
      'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
      'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
    ];

    const indice = Number(partes[1]) - 1;

    return `${nomes[indice] ?? partes[1]} ${partes[0]}`;
  }

  formatarData(valor?: string | null): string {
    if (!valor) {
      return '-';
    }

    const data = new Date(valor);

    if (Number.isNaN(data.getTime())) {
      return valor;
    }

    return new Intl.DateTimeFormat('pt-PT', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    }).format(data);
  }

  formatarDataHora(valor?: string | null): string {
    if (!valor) {
      return '-';
    }

    const data = new Date(valor.replace(' ', 'T'));

    if (Number.isNaN(data.getTime())) {
      return valor;
    }

    return new Intl.DateTimeFormat('pt-PT', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    }).format(data);
  }

  private obterStatus(erro: unknown): number | null {
    if (
      typeof erro === 'object' &&
      erro !== null &&
      'status' in erro &&
      typeof (erro as { status: unknown }).status === 'number'
    ) {
      return (erro as { status: number }).status;
    }

    return null;
  }

  private lerMensagemErro(erro: unknown): string {
    const resposta = erro as {
      error?: { message?: string; errors?: Record<string, string[]> };
    };

    if (resposta?.error?.errors) {
      const primeiro = Object.values(resposta.error.errors).flat()[0];

      if (primeiro) {
        return primeiro;
      }
    }

    return (
      resposta?.error?.message ||
      'Não foi possível enviar o relatório. Verifica os dados e tenta novamente.'
    );
  }

  terminarSessao(): void {
    if (this.terminandoSessao()) {
      return;
    }

    this.terminandoSessao.set(true);

    this.authService.logout()
      .pipe(finalize(() => this.terminandoSessao.set(false)))
      .subscribe({
        next: () => this.router.navigate(['/acesso']),
        error: () => this.router.navigate(['/acesso'])
      });
  }
}
