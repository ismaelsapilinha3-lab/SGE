
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


@Component({
  selector: 'app-painel-admin',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './painel-admin.html',
  styleUrl: './painel-admin.css'
})
export class PainelAdmin implements OnInit, OnDestroy {

  // ============================================================
  // SECÇÃO ATIVA
  // ============================================================

  secaoAtiva = signal<
    'dashboard' | 'candidaturas' | 'departamentos' | 'estagiarios' | 'estagiarios'
  >('dashboard');


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
  // DEPARTAMENTOS
  // ============================================================

  estagiarios = signal<Estagiario[]>([]);
  pesquisaEstagiarios = signal('');
  estadoEstagiarios = signal('');
  carregandoEstagiarios = signal(false);
  erroEstagiarios = signal('');
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
  processandoDecisao = signal(false);
  abrindoDocumento = signal(false);


  // ============================================================
  // ERROS
  // ============================================================

  erroDashboard = signal('');
  erroCandidaturas = signal('');
  erroDepartamentos = signal('');
  erroDetalhes = signal('');
  erroDecisao = signal('');
  erroDocumento = signal('');


  // ============================================================
  // SUCESSO
  // ============================================================

  sucessoDecisao = signal('');


  // ============================================================
  // RECUSA
  // ============================================================

  mostrarRecusa = signal(false);
  motivoRecusa = signal('');


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
  // Ordenar pelo número de estagiários ativos, do maior para o menor.
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
    this.descricaoDepartamento.set(departamento.descricao ?? '');
    this.ativoDepartamento.set(departamento.ativo ?? true);
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
  // Cria um departamento novo ou atualiza um existente.
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
            console.error('Erro ao editar departamento:', erro);
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
  // MENSAGEM DE ERRO DEVOLVIDA PELO LARAVEL
  // ============================================================

  private mensagemErroDepartamento(
    erro: unknown,
    mensagemPadrao: string
  ): string {
    if (typeof erro !== 'object' || erro === null || !('error' in erro)) {
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
        this.ultimaPagina.set(Math.max(resposta.last_page ?? 1, 1));
        this.paginaAtual.set(resposta.current_page ?? 1);
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

  diasRestantes(estagiario: Estagiario): number | null {
    if (!estagiario.data_fim_prevista) {
      return null;
    }

    const hoje = new Date();
    hoje.setHours(0, 0, 0, 0);

    const fim = new Date(estagiario.data_fim_prevista + 'T00:00:00');

    if (Number.isNaN(fim.getTime())) {
      return null;
    }

    return Math.ceil((fim.getTime() - hoje.getTime()) / 86400000);
  }
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
  }


  // ============================================================
  // PESQUISAR
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
  // PÁGINA ANTERIOR
  // ============================================================

  paginaAnterior(): void {
    const pagina = this.paginaAtual();

    if (pagina <= 1) {
      return;
    }

    this.paginaAtual.set(pagina - 1);
    this.carregarCandidaturas();
  }


  // ============================================================
  // PRÓXIMA PÁGINA
  // ============================================================

  proximaPagina(): void {
    const pagina = this.paginaAtual();

    if (pagina >= this.ultimaPagina()) {
      return;
    }

    this.paginaAtual.set(pagina + 1);
    this.carregarCandidaturas();
  }


  // ============================================================
  // IR PARA PÁGINA
  // ============================================================

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


  // ============================================================
  // PÁGINAS VISÍVEIS
  // ============================================================

  paginasVisiveis(): number[] {
    const total = this.ultimaPagina();
    const atual = this.paginaAtual();
    const paginas: number[] = [];

    const inicio = Math.max(1, atual - 2);
    const fim = Math.min(total, atual + 2);

    for (let pagina = inicio; pagina <= fim; pagina++) {
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
    this.erroDecisao.set('');
    this.sucessoDecisao.set('');
    this.mostrarRecusa.set(false);
    this.motivoRecusa.set('');

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
    this.mostrarRecusa.set(false);
    this.motivoRecusa.set('');
    this.erroDetalhes.set('');
    this.erroDecisao.set('');
    this.sucessoDecisao.set('');
    this.erroDocumento.set('');
  }


  // ============================================================
  // ABRIR ÁREA DE RECUSA
  // ============================================================

  abrirRecusa(): void {
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

    this.erroDecisao.set('');
    this.sucessoDecisao.set('');
    this.motivoRecusa.set('');
    this.mostrarRecusa.set(true);
  }


  // ============================================================
  // CANCELAR RECUSA
  // ============================================================

  cancelarRecusa(): void {
    this.mostrarRecusa.set(false);
    this.motivoRecusa.set('');
    this.erroDecisao.set('');
  }


  // ============================================================
  // ALTERAR MOTIVO DA RECUSA
  // ============================================================

  alterarMotivoRecusa(event: Event): void {
    const textarea = event.target as HTMLTextAreaElement;
    this.motivoRecusa.set(textarea.value);
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

    if (!isPlatformBrowser(this.platformId)) {
      return;
    }

    const confirmar = window.confirm(
      `Tem certeza que deseja aprovar a candidatura de ${candidatura.nome}?`
    );

    if (!confirmar) {
      return;
    }

    this.processandoDecisao.set(true);
    this.erroDecisao.set('');
    this.sucessoDecisao.set('');

    const sub = this.adminService
      .aprovarCandidatura(candidatura.id_candidatura)
      .subscribe({
        next: () => {
          this.processandoDecisao.set(false);
          this.sucessoDecisao.set(
            'Candidatura aprovada com sucesso.'
          );
          this.mostrarRecusa.set(false);
          this.atualizarCandidaturaLocal('aprovada');
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

    this.subscriptions.add(sub);
  }


  // ============================================================
  // RECUSAR CANDIDATURA
  // ============================================================

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

    const sub = this.adminService
      .recusarCandidatura(candidatura.id_candidatura, motivo)
      .subscribe({
        next: () => {
          this.processandoDecisao.set(false);
          this.mostrarRecusa.set(false);
          this.sucessoDecisao.set(
            'Candidatura recusada com sucesso.'
          );
          this.atualizarCandidaturaLocal('recusada', motivo);
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

    this.subscriptions.add(sub);
  }


  // ============================================================
  // ATUALIZAR CANDIDATURA LOCALMENTE
  // ============================================================

  private atualizarCandidaturaLocal(
    estado: 'aprovada' | 'recusada',
    motivo?: string
  ): void {
    const atual = this.candidaturaSelecionada();

    if (!atual) {
      return;
    }

    this.candidaturaSelecionada.set({
      ...atual,
      estado,
      motivo_recusa: estado === 'recusada' ? motivo ?? null : null,
      data_decisao: new Date().toISOString()
    });
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
          } catch (erro: unknown) {
            console.error('Erro ao preparar documento:', erro);
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

  private inferirMimeType(documento: Documento, blob: Blob): string {
    if (blob.type && blob.type !== 'application/octet-stream') {
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

  irParaSecao(
    secao: 'dashboard' | 'candidaturas' | 'departamentos' | 'estagiarios' | 'estagiarios'
  ): void {
    this.secaoAtiva.set(secao);

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
    this.authService.logout().subscribe({
      next: () => {
        this.router.navigate(['/acesso']);
      },
      error: (erro: unknown) => {
        console.error('Erro ao encerrar sessão:', erro);
        this.router.navigate(['/acesso']);
      }
    });
  }


  estagioEmGestao = signal<Estagiario | null>(null);
  estadoEstagioFormulario = signal<'ativo' | 'suspenso' | 'concluido' | 'cancelado'>('ativo');
  inicioEstagioFormulario = signal('');
  fimEstagioFormulario = signal('');
  motivoSuspensaoFormulario = signal('');
  aGuardarEstadoEstagio = signal(false);
  erroGestaoEstagio = signal('');
  sucessoGestaoEstagio = signal('');

  duracaoMesesFormulario = signal(2);

  calcularDuracaoMeses(inicio: string, fim: string): number {
    if (!inicio || !fim) return 2;
    const [ai, mi, di] = inicio.split('-').map(Number);
    const [af, mf, df] = fim.split('-').map(Number);
    let meses = (af - ai) * 12 + (mf - mi);
    if (df < di) meses--;
    return Math.max(1, Math.min(60, meses || 1));
  }

  calcularDataFimEstagio(inicio: string, meses: number): string {
    if (!inicio || !Number.isFinite(meses) || meses < 1) return '';
    const [ano, mes, dia] = inicio.split('-').map(Number);
    const data = new Date(ano, mes - 1, 1);
    data.setMonth(data.getMonth() + Math.floor(meses));
    const ultimoDia = new Date(data.getFullYear(), data.getMonth() + 1, 0).getDate();
    data.setDate(Math.min(dia, ultimoDia));
    const a = data.getFullYear();
    const m = String(data.getMonth() + 1).padStart(2, '0');
    const d = String(data.getDate()).padStart(2, '0');
    return `${a}-${m}-${d}`;
  }

  abrirGestaoEstagio(estagiario: Estagiario): void {
    this.estagioEmGestao.set(estagiario);
    const estados = ['ativo', 'suspenso', 'concluido', 'cancelado'] as const;
    const estado = estados.includes(estagiario.estado_estagio as typeof estados[number])
      ? estagiario.estado_estagio as typeof estados[number]
      : 'ativo';

    this.estadoEstagioFormulario.set(estado);
    this.inicioEstagioFormulario.set(estagiario.data_inicio ?? '');
    this.fimEstagioFormulario.set(estagiario.data_fim_prevista ?? '');
    this.duracaoMesesFormulario.set(this.calcularDuracaoMeses(estagiario.data_inicio ?? '', estagiario.data_fim_prevista ?? ''));
    this.motivoSuspensaoFormulario.set(estagiario.motivo_suspensao ?? '');
    this.erroGestaoEstagio.set('');
    this.sucessoGestaoEstagio.set('');
  }

  fecharGestaoEstagio(): void {
    if (!this.aGuardarEstadoEstagio()) {
      this.estagioEmGestao.set(null);
      this.erroGestaoEstagio.set('');
    }
  }

  guardarEstadoEstagio(): void {
    const estagiario = this.estagioEmGestao();
    if (!estagiario || this.aGuardarEstadoEstagio()) return;

    const estado = this.estadoEstagioFormulario();
    const inicio = this.inicioEstagioFormulario();
    const fim = this.calcularDataFimEstagio(inicio, this.duracaoMesesFormulario());
    const motivo = this.motivoSuspensaoFormulario().trim();

    if (!estagiario.id_estagio && (!inicio || !fim)) {
      this.erroGestaoEstagio.set('Indica a data de início e a data prevista de fim.');
      return;
    }
    if (inicio && fim && fim < inicio) {
      this.erroGestaoEstagio.set('A data prevista de fim não pode ser anterior à data de início.');
      return;
    }
    if (estado === 'suspenso' && !motivo) {
      this.erroGestaoEstagio.set('Indica o motivo da suspensão.');
      return;
    }

    this.aGuardarEstadoEstagio.set(true);
    this.erroGestaoEstagio.set('');

    this.adminService.atualizarEstadoEstagio(estagiario.id_candidatura, {
      estado,
      data_inicio: inicio || undefined,
      data_fim_prevista: fim || undefined,
      motivo_suspensao: estado === 'suspenso' ? motivo : undefined
    }).subscribe({
      next: (resposta) => {
        this.sucessoGestaoEstagio.set(resposta.message || 'Estado atualizado.');
        this.aGuardarEstadoEstagio.set(false);
        this.estagioEmGestao.set(null);
        this.carregarEstagiarios();
      },
      error: (erro: unknown) => {
        const resposta = erro as { error?: { message?: string } };
        this.erroGestaoEstagio.set(
          resposta.error?.message || 'Não foi possível atualizar o estágio. Verifica a API e as permissões.'
        );
        this.aGuardarEstadoEstagio.set(false);
      }
    });
  }
}
