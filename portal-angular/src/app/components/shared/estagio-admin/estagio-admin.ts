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
  AdminService,
  EstagioAdmin,
  PlanoEstagioAdmin,
  SupervisorEstagio
} from '../../../services/admin.service';


// ============================================================
// ESTADOS E TRANSIÇÕES
// ============================================================

type EstadoEstagio =
  | 'ativo'
  | 'suspenso'
  | 'concluido'
  | 'cancelado';


const TRANSICOES: Record<EstadoEstagio, EstadoEstagio[]> = {

  ativo: ['ativo', 'suspenso', 'concluido', 'cancelado'],

  suspenso: ['suspenso', 'ativo', 'cancelado'],

  concluido: ['concluido'],

  cancelado: ['cancelado']

};


// ============================================================
// COMPONENTE
// ============================================================

@Component({
  selector: 'app-estagio-admin',
  standalone: true,

  imports: [
    CommonModule,
    FormsModule
  ],

  templateUrl: './estagio-admin.html',

  styleUrl: './estagio-admin.css'
})
export class EstagioAdminComponent {

  private readonly adminService = inject(AdminService);

  private readonly platformId = inject(PLATFORM_ID);


  // ==========================================================
  // LISTAGEM
  // ==========================================================

  readonly carregando = signal(false);

  readonly erro = signal<string | null>(null);

  readonly estagios = signal<EstagioAdmin[]>([]);

  pesquisaEstagios = '';

  estadoFiltroEstagios = '';

  readonly pagina = signal(1);

  readonly ultimaPagina = signal(1);

  readonly totalEstagios = signal(0);


  // ==========================================================
  // DETALHE
  // ==========================================================

  readonly estagioSelecionadoId = signal<number | null>(null);

  readonly detalhe = signal<EstagioAdmin | null>(null);

  readonly carregandoDetalhe = signal(false);

  readonly erroDetalhe = signal<string | null>(null);


  // ==========================================================
  // FORMULÁRIO DE ESTADO
  // ==========================================================

  estadoFormulario = signal<EstadoEstagio>('ativo');

  inicioFormulario = signal('');

  fimFormulario = signal('');

  motivoSuspensaoFormulario = signal('');

  readonly processando = signal(false);

  readonly sucesso = signal<string | null>(null);

  readonly erroAcao = signal<string | null>(null);


  // ==========================================================
  // PERMISSÕES
  // ==========================================================

  readonly estadoAtual = computed<EstadoEstagio>(() => {

    return this.detalhe()?.estado ?? 'ativo';

  });

  readonly estagioTerminado = computed(() => {

    const estado = this.estadoAtual();

    return estado === 'concluido' || estado === 'cancelado';

  });

  readonly estadosPermitidos = computed<EstadoEstagio[]>(() => {

    return TRANSICOES[this.estadoAtual()] ?? [this.estadoAtual()];

  });

  readonly pedeMotivoSuspensao = computed(() => {

    return this.estadoFormulario() === 'suspenso';

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
  // LISTAGEM
  // ==========================================================

  carregarEstagios(): void {

    this.carregando.set(true);

    this.erro.set(null);

    this.adminService
      .listarEstagios(
        this.pesquisaEstagios,
        this.estadoFiltroEstagios,
        this.pagina()
      )
      .subscribe({

        next: resposta => {

          this.estagios.set(resposta.data ?? []);

          this.ultimaPagina.set(
            Math.max(resposta.last_page ?? 1, 1)
          );

          this.totalEstagios.set(resposta.total ?? 0);

          this.carregando.set(false);

        },

        error: erro => {

          console.error('Erro ao carregar estágios:', erro);

          this.estagios.set([]);

          this.carregando.set(false);

          this.erro.set(
            this.mensagemErro(
              erro,
              'Não foi possível carregar os estágios.'
            )
          );

        }

      });

  }


  aplicarFiltros(): void {

    this.pagina.set(1);

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


  // ==========================================================
  // DETALHE
  // ==========================================================

  selecionar(estagio: EstagioAdmin): void {

    this.estagioSelecionadoId.set(estagio.id_estagio);

    this.detalhe.set(null);

    this.erroDetalhe.set(null);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.carregandoDetalhe.set(true);

    this.adminService.obterEstagio(estagio.id_estagio).subscribe({

      next: resposta => {

        this.detalhe.set(resposta);

        this.preencherFormulario(resposta);

        this.carregandoDetalhe.set(false);

      },

      error: erro => {

        console.error('Erro ao carregar o estágio:', erro);

        this.carregandoDetalhe.set(false);

        this.erroDetalhe.set(
          'Não foi possível carregar os detalhes do estágio.'
        );

      }

    });

  }


  fecharDetalhe(): void {

    if (this.processando()) {

      return;

    }

    this.estagioSelecionadoId.set(null);

    this.detalhe.set(null);

    this.erroDetalhe.set(null);

    this.sucesso.set(null);

    this.erroAcao.set(null);

  }


  private preencherFormulario(estagio: EstagioAdmin): void {

    const estado = estagio.estado;

    const estados = (['ativo', 'suspenso', 'concluido', 'cancelado'] as EstadoEstagio[]);

    this.estadoFormulario.set(
      estados.includes(estado) ? estado : 'ativo'
    );

    this.inicioFormulario.set(
      (estagio.data_inicio ?? '').substring(0, 10)
    );

    this.fimFormulario.set(
      (estagio.data_fim_prevista ?? '').substring(0, 10)
    );

    this.motivoSuspensaoFormulario.set(
      estagio.motivo_suspensao ?? ''
    );

  }


  // ==========================================================
  // GUARDAR ESTADO
  // ==========================================================

  guardarEstado(): void {

    const detalhe = this.detalhe();

    if (!detalhe || this.processando()) {

      return;

    }

    const estado = this.estadoFormulario();

    const inicio = this.inicioFormulario();

    const fim = this.fimFormulario();

    const motivo = this.motivoSuspensaoFormulario().trim();

    if (!inicio || !fim) {

      this.erroAcao.set(
        'Indica a data de início e a data prevista de fim.'
      );

      return;

    }

    if (fim < inicio) {

      this.erroAcao.set(
        'A data prevista de fim não pode ser anterior à data de início.'
      );

      return;

    }

    if (estado === 'suspenso' && !motivo) {

      this.erroAcao.set('Indica o motivo da suspensão.');

      return;

    }

    this.processando.set(true);

    this.sucesso.set(null);

    this.erroAcao.set(null);

    this.adminService
      .atualizarEstadoEstagio(detalhe.id_candidatura, {

        estado,

        data_inicio: inicio,

        data_fim_prevista: fim,

        motivo_suspensao: estado === 'suspenso'
          ? motivo
          : undefined

      })
      .subscribe({

        next: resposta => {

          this.processando.set(false);

          this.sucesso.set(
            resposta.message || 'Estado do estágio atualizado.'
          );

          this.recarregarDetalhe(detalhe.id_estagio);

        },

        error: erro => {

          console.error('Erro ao atualizar o estado:', erro);

          this.processando.set(false);

          this.erroAcao.set(
            this.mensagemErro(
              erro,
              'Não foi possível atualizar o estado do estágio.'
            )
          );

        }

      });

  }


  private recarregarDetalhe(idEstagio: number): void {

    this.adminService.obterEstagio(idEstagio).subscribe({

      next: resposta => {

        this.detalhe.set(resposta);

        this.preencherFormulario(resposta);

      },

      error: erro => {

        console.error('Erro ao recarregar o estágio:', erro);

      }

    });

  }


  // ==========================================================
  // APRESENTAÇÃO
  // ==========================================================

  nomeEstagiario(estagio: EstagioAdmin): string {

    return estagio.estagiario?.nome
      || estagio.candidatura?.nome
      || '—';

  }


  nomesSupervisores(estagio: EstagioAdmin): string {

    const vinculos = estagio.supervisores_ativos ?? [];

    const nomes = vinculos
      .map((vinculo: SupervisorEstagio) => vinculo.supervisor?.nome)
      .filter((nome): nome is string => Boolean(nome));

    return nomes.length > 0
      ? nomes.join(', ')
      : 'Sem supervisor atribuído';

  }


  rotuloEstado(estado: string | null): string {

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


  rotuloEstadoPlano(plano: PlanoEstagioAdmin | null | undefined): string {

    switch (plano?.estado) {

      case 'submetido':
        return 'Aguarda aprovação';

      case 'aprovado':
        return 'Aprovado';

      case 'recusado':
        return 'Recusado';

      default:
        return 'Sem plano aprovado';

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
