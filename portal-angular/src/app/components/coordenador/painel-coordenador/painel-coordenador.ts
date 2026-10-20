import { CommonModule } from '@angular/common';

import {
  Component,
  OnInit,
  inject,
  signal
} from '@angular/core';

import { FormsModule } from '@angular/forms';

import { Router } from '@angular/router';

import {
  SupervisorService,
  CriarSupervisor
} from '../../../services/supervisor';

import {
  CoordenadorService,
  CandidaturaCoordenador,
  DashboardCoordenador
} from '../../../services/coordenador.service';

import { AuthService } from '../../../core/auth.service';

import {
  SinoNotificacoesComponent
} from '../../shared/sino-notificacoes/sino-notificacoes';

import {
  EstagioCoordenadorComponent
} from '../../shared/estagio-coordenador/estagio-coordenador';


@Component({
  selector: 'app-painel-coordenador',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    SinoNotificacoesComponent,
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

  private readonly authService =
    inject(AuthService);

  private readonly router =
    inject(Router);


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
  // CICLO DE VIDA
  // ============================================================

  ngOnInit(): void {
    this.carregarDashboard();

    this.carregarCandidaturas();
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


  sair(): void {
    this.authService.logout();

    this.router.navigate(['/telainicial']);
  }

}
