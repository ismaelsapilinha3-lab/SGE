import { CommonModule } from '@angular/common';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import {
  UtilizadorAdminService,
  UtilizadorAdmin,
  DepartamentoAdmin,
  TipoUtilizador,
  CriarUtilizadorAdmin,
  ActualizarUtilizadorAdmin
} from '../../../services/utilizador-admin.service';

@Component({
  selector: 'app-utilizadores-admin',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule
  ],
  templateUrl: './utilizadores-admin.html',
  styleUrls: ['./utilizadores-admin.css']
})
export class UtilizadoresAdminComponent implements OnInit {

  private readonly service = inject(UtilizadorAdminService);

  // Lista e paginação
  utilizadores = signal<UtilizadorAdmin[]>([]);
  departamentos = signal<DepartamentoAdmin[]>([]);

  carregando = signal(false);
  guardando = signal(false);
  erro = signal('');
  sucesso = signal('');

  paginaAtual = signal(1);
  totalPaginas = signal(1);
  totalRegistos = signal(0);
  porPagina = 15;

  // Pesquisa e filtros
  pesquisa = '';
  filtroTipo = '';
  filtroEstado = '';

  // Formulário
  mostrarFormulario = signal(false);
  utilizadorEditado = signal<UtilizadorAdmin | null>(null);

  nome = '';
  email = '';
  telefone = '';
  tipoUtilizador: 'coordenador' | 'supervisor' | 'estagiario' = 'estagiario';
  idDepartamento: number | null = null;
  password = '';
  passwordConfirmation = '';

  ngOnInit(): void {
    this.carregarDepartamentos();
    this.carregarUtilizadores();
  }

  carregarDepartamentos(): void {
    this.service.listarDepartamentos().subscribe({
      next: dados => this.departamentos.set(dados),
      error: erro => {
        console.error('Erro ao carregar direções:', erro);
      }
    });
  }

  carregarUtilizadores(): void {
    this.carregando.set(true);
    this.erro.set('');
    this.sucesso.set('');

    const ativo: boolean | null =
      this.filtroEstado === ''
        ? null
        : this.filtroEstado === 'ativo';

    this.service.listar(
      this.pesquisa,
      this.filtroTipo,
      ativo,
      this.paginaAtual(),
      this.porPagina
    ).subscribe({
      next: resposta => {
        this.utilizadores.set(resposta.data ?? []);
        this.totalRegistos.set(resposta.total ?? 0);
        this.totalPaginas.set(Math.max(resposta.last_page ?? 1, 1));
        this.paginaAtual.set(resposta.current_page ?? 1);
        this.carregando.set(false);
      },
      error: erro => {
        console.error('Erro ao carregar utilizadores:', erro);
        this.erro.set(
          erro.status === 403
            ? 'Não tens permissão para gerir utilizadores.'
            : 'Não foi possível carregar os utilizadores. Verifica a ligação ao servidor.'
        );
        this.carregando.set(false);
      }
    });
  }

  pesquisar(): void {
    this.paginaAtual.set(1);
    this.carregarUtilizadores();
  }

  limparFiltros(): void {
    this.pesquisa = '';
    this.filtroTipo = '';
    this.filtroEstado = '';
    this.paginaAtual.set(1);
    this.carregarUtilizadores();
  }

  abrirCriacao(): void {
    this.limparFormulario();
    this.utilizadorEditado.set(null);
    this.mostrarFormulario.set(true);
    this.erro.set('');
    this.sucesso.set('');
  }

  abrirEdicao(utilizador: UtilizadorAdmin): void {
    if (utilizador.tipo_utilizador === 'administrador') {
      this.erro.set('A edição de contas de administrador não está disponível nesta página.');
      return;
    }

    this.limparFormulario();
    this.utilizadorEditado.set(utilizador);

    this.nome = utilizador.nome;
    this.email = utilizador.email;
    this.telefone = utilizador.telefone ?? '';
    this.tipoUtilizador = utilizador.tipo_utilizador as
      'coordenador' | 'supervisor' | 'estagiario';

    this.mostrarFormulario.set(true);
    this.erro.set('');
    this.sucesso.set('');
  }

  fecharFormulario(): void {
    if (this.guardando()) {
      return;
    }

    this.mostrarFormulario.set(false);
    this.utilizadorEditado.set(null);
    this.limparFormulario();
  }

  private limparFormulario(): void {
    this.nome = '';
    this.email = '';
    this.telefone = '';
    this.tipoUtilizador = 'estagiario';
    this.idDepartamento = null;
    this.password = '';
    this.passwordConfirmation = '';
  }

  guardarUtilizador(): void {
    this.erro.set('');
    this.sucesso.set('');

    if (!this.nome.trim() || !this.email.trim()) {
      this.erro.set('Preenche o nome e o e-mail.');
      return;
    }

    if (this.password || this.passwordConfirmation ||
        !this.utilizadorEditado()) {
      if (this.password.length < 8) {
        this.erro.set('A palavra-passe deve ter pelo menos 8 caracteres.');
        return;
      }

      if (this.password !== this.passwordConfirmation) {
        this.erro.set('As palavras-passe não coincidem.');
        return;
      }
    }

    this.guardando.set(true);

    const editado = this.utilizadorEditado();

    if (editado) {
      const dados: ActualizarUtilizadorAdmin = {
        nome: this.nome.trim(),
        email: this.email.trim(),
        telefone: this.telefone.trim() || null,
        tipo_utilizador: this.tipoUtilizador
      };

      if (this.password) {
        dados.password = this.password;
        dados.password_confirmation = this.passwordConfirmation;
      }

      this.service.actualizar(editado.id_utilizador, dados).subscribe({
        next: resposta => {
          this.guardando.set(false);
          this.mostrarFormulario.set(false);
          this.sucesso.set(resposta.message || 'Utilizador actualizado com sucesso.');
          this.carregarUtilizadores();
        },
        error: erro => {
          this.guardando.set(false);
          console.error('Erro ao actualizar utilizador:', erro);
          this.erro.set(this.mensagemErro(erro));
        }
      });

      return;
    }

    const dados: CriarUtilizadorAdmin = {
      nome: this.nome.trim(),
      email: this.email.trim(),
      telefone: this.telefone.trim(),
      password: this.password,
      password_confirmation: this.passwordConfirmation,
      tipo_utilizador: this.tipoUtilizador
    };

    if (this.tipoUtilizador === 'coordenador' && this.idDepartamento !== null) {
      dados.id_departamento = this.idDepartamento;
    }

    this.service.criar(dados).subscribe({
      next: resposta => {
        this.guardando.set(false);
        this.mostrarFormulario.set(false);
        this.sucesso.set(resposta.message || 'Utilizador criado com sucesso.');
        this.paginaAtual.set(1);
        this.carregarUtilizadores();
      },
      error: erro => {
        this.guardando.set(false);
        console.error('Erro ao criar utilizador:', erro);
        this.erro.set(this.mensagemErro(erro));
      }
    });
  }

  alterarEstado(utilizador: UtilizadorAdmin): void {
    if (utilizador.tipo_utilizador === 'administrador') {
      this.erro.set('Não é permitido alterar o estado de uma conta de administrador nesta página.');
      return;
    }

    const acao = utilizador.ativo ? 'desactivar' : 'activar';

    if (!window.confirm(`Tens a certeza de que pretendes ${acao} a conta de ${utilizador.nome}?`)) {
      return;
    }

    this.erro.set('');
    this.sucesso.set('');

    this.service.alterarEstado(utilizador.id_utilizador).subscribe({
      next: resposta => {
        this.sucesso.set(resposta.message);
        this.carregarUtilizadores();
      },
      error: erro => {
        console.error('Erro ao alterar estado:', erro);
        this.erro.set(this.mensagemErro(erro));
      }
    });
  }

  irParaPagina(pagina: number): void {
    if (pagina < 1 || pagina > this.totalPaginas()) {
      return;
    }

    this.paginaAtual.set(pagina);
    this.carregarUtilizadores();
  }

  nomeTipo(tipo: TipoUtilizador): string {
    const nomes: Record<TipoUtilizador, string> = {
      administrador: 'Administrador',
      coordenador: 'Coordenador',
      supervisor: 'Supervisor',
      estagiario: 'Estagiário'
    };

    return nomes[tipo] ?? tipo;
  }

  mensagemErro(erro: any): string {
    if (erro.status === 422 && erro.error?.errors) {
      const mensagens = Object.values(erro.error.errors) as string[][];
      return mensagens.flat().join(' ');
    }

    if (erro.status === 401) {
      return 'A sessão expirou. Inicia sessão novamente.';
    }

    if (erro.status === 403) {
      return 'Não tens permissão para realizar esta operação.';
    }

    return erro.error?.message ||
      'Ocorreu um erro. Verifica os dados e tenta novamente.';
  }
}
