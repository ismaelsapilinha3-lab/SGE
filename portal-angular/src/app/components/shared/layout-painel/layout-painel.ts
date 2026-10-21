import {
  ChangeDetectionStrategy,
  Component,
  EventEmitter,
  Input,
  Output,
  signal
} from '@angular/core';

import { CommonModule } from '@angular/common';

import {
  SinoNotificacoesComponent
} from '../sino-notificacoes/sino-notificacoes';

/**
 * Item de navegação da barra lateral.
 */
export interface ItemNavegacaoPainel {
  /** Identificador da secção (ex.: `candidaturas`). */
  id: string;

  /** Texto visível do item. */
  rotulo: string;

  /** Glifo mostrado à esquerda do rótulo. */
  icone?: string;

  /** Quando `true` o item é mostrado mas não navegável. */
  desativado?: boolean;

  /** Nota mostrada em `title` quando o item está desativado. */
  nota?: string;
}

/**
 * Grupo de itens da barra lateral (com título opcional).
 */
export interface GrupoNavegacaoPainel {
  /** Título do grupo; quando omitido os itens aparecem soltos. */
  titulo?: string;

  /** Itens que compõem o grupo. */
  itens: ItemNavegacaoPainel[];
}

/**
 * Estrutura de layout partilhada pelos painéis autenticados.
 *
 * Reproduz a linguagem visual da página inicial pública (barra superior
 * branca, tokens de cor institucionais) e acrescenta uma barra lateral
 * de navegação por secções, mantendo o conteúdo no `<ng-content>`.
 *
 * O componente é apenas apresentacional: não conhece o serviço de API
 * nem os perfis. O painel que o utiliza decide as secções e reage a
 * `(secaoSelecionada)`.
 */
@Component({
  selector: 'app-layout-painel',
  standalone: true,
  imports: [CommonModule, SinoNotificacoesComponent],
  templateUrl: './layout-painel.html',
  styleUrl: './layout-painel.css',
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class LayoutPainelComponent {

  /** Rótulo curto mostrado junto ao logótipo (ex.: `SGE`). */
  @Input() marcaSigla = 'SGE';

  /** Descrição sob a sigla (ex.: `Gestão de Estágios`). */
  @Input() marcaDescricao = 'Gestão de Estágios';

  /** Nome do perfil apresentado no topo (ex.: `Administrador`). */
  @Input() nomePerfil = 'Utilizador';

  /** Descrição do perfil apresentada sob o nome. */
  @Input() descricaoPerfil = '';

  /** URL da foto de perfil; quando ausente mostra-se a inicial do nome. */
  @Input() fotoUrl: string | null = null;

  /** Quando `false` o avatar não é clicável (ex.: perfil só de leitura). */
  @Input() fotoEditavel = true;

  /** Secção atualmente ativa; controla o estado `[class.ativo]`. */
  @Input() secaoAtiva = '';

  /** Grupos de navegação mostrados na barra lateral. */
  @Input() grupos: GrupoNavegacaoPainel[] = [];

  /** Caminho do logótipo institucional. */
  @Input() logoUrl = 'assets/img/multitel.png';

  /** Texto mostrado na barra informativa superior. */
  @Input() notaInstitucional = 'Sistema de Gestão de Estágios';

  /** Emitido quando o utilizador escolhe uma secção navegável. */
  @Output() secaoSelecionada = new EventEmitter<string>();

  /** Emitido quando o utilizador termina a sessão. */
  @Output() terminarSessao = new EventEmitter<void>();

  /** Emitido quando o utilizador escolhe um ficheiro para a foto de perfil. */
  @Output() fotoSelecionada = new EventEmitter<File>();

  /** Estado do menu em ecrãs estreitos. */
  readonly menuAberto = signal(false);

  /** Abre ou fecha a barra lateral em ecrãs estreitos. */
  alternarMenu(): void {
    this.menuAberto.update((aberto) => !aberto);
  }

  /**
   * Valida e emite o ficheiro escolhido para a foto de perfil,
   * repondo o valor do campo para permitir escolher o mesmo ficheiro.
   */
  escolherFoto(evento: Event): void {
    const campo = evento.target as HTMLInputElement;
    const ficheiro = campo.files?.[0];

    campo.value = '';

    if (!ficheiro) {
      return;
    }

    this.fotoSelecionada.emit(ficheiro);
  }

  /** Fecha a barra lateral (usado após escolher uma secção). */
  fecharMenu(): void {
    this.menuAberto.set(false);
  }

  /**
   * Trata a escolha de um item, ignorando os desativados e
   * fechando o menu móvel depois de navegar.
   */
  selecionar(item: ItemNavegacaoPainel): void {
    if (item.desativado) {
      return;
    }

    this.secaoSelecionada.emit(item.id);
    this.fecharMenu();
  }
}
