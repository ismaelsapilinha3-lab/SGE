import {
  CommonModule,
  isPlatformBrowser
} from '@angular/common';

import {
  Component,
  OnDestroy,
  PLATFORM_ID,
  computed,
  inject,
  signal
} from '@angular/core';

import {
  Router
} from '@angular/router';

import {
  AuthService
} from '../../core/auth.service';

import {
  CandidaturaEstagiario,
  CandidaturaEstagiarioService,
  DocumentoEstagiario
} from '../../core/candidatura-estagiario.service';

import {
  DomSanitizer,
  SafeResourceUrl
} from '@angular/platform-browser';

import {
  SinoNotificacoesComponent
} from '../shared/sino-notificacoes/sino-notificacoes';


// ============================================================
// TIPOS
// ============================================================

type Estado = 'analise' | 'aceite' | 'recusado';


interface ConfigEstado {

  css: string;

  badge: string;

  titulo: string;

  texto: string;

  p2: 'active' | 'done' | '';

  p3: 'done' | 'bad' | '';

  c3: string;

  l3: string;

}


interface Historico {

  classe: string;

  icone: string;

  titulo: string;

  texto: string;

  data: string;

}


// ============================================================
// CONFIGURAÇÃO DOS ESTADOS DA CANDIDATURA
// ============================================================

const ESTADOS: Record<Estado, ConfigEstado> = {

  analise: {

    css: 'analise',

    badge: 'Em análise',

    titulo: 'Pedido em análise',

    texto:
      'A equipa responsável está a analisar a tua candidatura. O resultado aparecerá nesta área.',

    p2: 'active',

    p3: '',

    c3: '3',

    l3: 'Resultado'

  },


  aceite: {

    css: 'aceite',

    badge: 'Aceite',

    titulo: 'Candidatura aceite',

    texto:
      'Parabéns! A tua candidatura foi aceite. A Multitel vai contactar-te com os próximos passos do estágio.',

    p2: 'done',

    p3: 'done',

    c3: '✓',

    l3: 'Aceite'

  },


  recusado: {

    css: 'recusado',

    badge: 'Não aceite',

    titulo: 'Candidatura não aceite',

    texto:
      'A tua candidatura não foi aceite. Para mais informações, contacta a linha de apoio da Multitel.',

    p2: 'done',

    p3: 'bad',

    c3: '✕',

    l3: 'Não aceite'

  }

};


// ============================================================
// COMPONENTE
// ============================================================

@Component({

  selector: 'app-painelestagiario',

  standalone: true,

  imports: [
    CommonModule,
    SinoNotificacoesComponent
  ],

  templateUrl: './painelestagiario.html',

  styleUrl: './painelestagiario.css'

})
export class PainelestagiarioComponent
  implements OnDestroy {


  // ==========================================================
  // INJEÇÕES
  // ==========================================================

  private readonly platformId = inject(PLATFORM_ID);

  private readonly router = inject(Router);

  private readonly sanitizer = inject(DomSanitizer);

  readonly authService = inject(AuthService);

  private readonly candidaturaService =
    inject(CandidaturaEstagiarioService);


  // ==========================================================
  // ESTADO DO PAINEL
  // ==========================================================

  readonly carregando = signal(true);

  readonly erro = signal<string | null>(null);


  // ==========================================================
  // SUBSTITUIÇÃO DE DOCUMENTOS
  // ==========================================================

  readonly documentoEmSubstituicao =
    signal<number | null>(null);

  readonly sucessoDocumento =
    signal<string | null>(null);

  readonly erroDocumento =
    signal<string | null>(null);


  // ==========================================================
  // PRÉ-VISUALIZAÇÃO DE DOCUMENTO
  // ==========================================================

  readonly documentoPreviewAberto = signal(false);

  readonly documentoPreviewUrl =
    signal<string | null>(null);

  readonly documentoPreviewPdfUrl =
    signal<SafeResourceUrl | null>(null);

  readonly documentoPreviewTipo = signal('');

  readonly documentoPreviewNome = signal('');

  readonly abrindoDocumento = signal(false);

  private documentoPreviewBlobUrl: string | null = null;


  // ==========================================================
  // DADOS DA CANDIDATURA
  // ==========================================================

  readonly candidatura =
    signal<CandidaturaEstagiario | null>(null);


  // ==========================================================
  // PRIMEIRO NOME DO UTILIZADOR
  // ==========================================================

  readonly primeiroNome = computed(() => {

    const utilizador = this.authService.utilizador();

    if (!utilizador?.nome) {

      return 'Estagiário';

    }

    return utilizador.nome
      .trim()
      .split(/\s+/)[0];

  });


  // ==========================================================
  // INICIAL DO UTILIZADOR
  // ==========================================================

  readonly inicial = computed(() => {

    const utilizador = this.authService.utilizador();

    if (!utilizador?.nome) {

      return 'E';

    }

    return utilizador.nome
      .trim()
      .charAt(0)
      .toUpperCase();

  });


  // ==========================================================
  // CONFIGURAÇÃO VISUAL DO ESTADO
  // ==========================================================

  readonly estado = computed<ConfigEstado>(() => {

    const candidatura = this.candidatura();

    if (!candidatura) {

      return ESTADOS.analise;

    }

    return ESTADOS[candidatura.estado]
      ?? ESTADOS.analise;

  });


  // ==========================================================
  // TEXTO DO ESTADO
  // ==========================================================

  readonly texto = computed(() => {

    return this.estado().texto;

  });


  // ==========================================================
  // HISTÓRICO DA CANDIDATURA
  // ==========================================================

  readonly historico = computed<Historico[]>(() => {

    const candidatura = this.candidatura();

    if (!candidatura) {

      return [];

    }

    const historico: Historico[] = [];


    historico.push({

      classe: 'done',

      icone: '✓',

      titulo: 'Candidatura submetida',

      texto:
        'A tua candidatura foi recebida pela Multitel.',

      data: candidatura.data_submissao

    });


    if (
      candidatura.estado === 'aceite' ||
      candidatura.estado === 'recusado'
    ) {

      historico.push({

        classe: 'done',

        icone: '✓',

        titulo: 'Candidatura analisada',

        texto:
          'A candidatura foi analisada pela equipa responsável.',

        data: candidatura.data_submissao

      });

    }


    if (candidatura.estado === 'aceite') {

      historico.push({

        classe: 'done',

        icone: '✓',

        titulo: 'Candidatura aceite',

        texto:
          'A candidatura foi aceite pela Multitel.',

        data: candidatura.data_submissao

      });

    }


    if (candidatura.estado === 'recusado') {

      historico.push({

        classe: 'bad',

        icone: '✕',

        titulo: 'Candidatura não aceite',

        texto:
          'A candidatura não foi aceite.',

        data: candidatura.data_submissao

      });

    }

    return historico;

  });


  // ==========================================================
  // CONSTRUTOR
  // ==========================================================

  constructor() {

    if (isPlatformBrowser(this.platformId)) {

      this.carregarPainel();

    } else {

      this.carregando.set(false);

    }

  }


  // ==========================================================
  // CARREGAR PAINEL
  // ==========================================================

  private carregarPainel(): void {

    this.carregando.set(true);

    this.erro.set(null);

    this.candidaturaService
      .minhaCandidatura()
      .subscribe({

        next: (dados) => {

          this.candidatura.set(dados);

          this.carregando.set(false);

        },


        error: (erro: unknown) => {

          console.error(
            'Erro ao carregar candidatura:',
            erro
          );

          this.candidatura.set(null);

          this.carregando.set(false);


          const status =
            this.obterStatusErro(erro);


          if (status === 401) {

            this.erro.set(
              'A sessão expirou. Faça login novamente.'
            );

            return;

          }


          if (status === 404) {

            this.erro.set(
              'Não foi encontrada uma candidatura associada a esta conta.'
            );

            return;

          }


          this.erro.set(
            'Não foi possível carregar os dados da candidatura.'
          );

        }

      });

  }


  // ==========================================================
  // OBTER STATUS DE UM ERRO HTTP
  // ==========================================================

  private obterStatusErro(
    erro: unknown
  ): number | null {

    if (
      typeof erro === 'object' &&
      erro !== null &&
      'status' in erro &&
      typeof erro.status === 'number'
    ) {

      return erro.status;

    }

    return null;

  }


  // ==========================================================
  // RECARREGAR PAINEL
  // ==========================================================

  recarregar(): void {

    this.carregarPainel();

  }


  // ==========================================================
  // NAVEGAÇÃO
  // ==========================================================

  irPara(rota: string): void {

    if (!rota) {

      return;

    }

    this.router.navigate([rota]);

  }


  // ==========================================================
  // ENCERRAR SESSÃO
  // ==========================================================

  sair(): void {

    this.authService
      .logout()
      .subscribe({

        next: () => {

          this.router.navigate([
            '/acesso'
          ]);

        },


        error: () => {

          this.router.navigate([
            '/acesso'
          ]);

        }

      });

  }


  // ==========================================================
  // EXTENSÃO DO DOCUMENTO
  // ==========================================================

  extensao(
    documento: DocumentoEstagiario
  ): string {

    const nome =
      documento.nome_arquivo || '';

    const partes =
      nome.split('.');

    if (partes.length < 2) {

      return 'FILE';

    }

    return partes
      .pop()
      ?.toUpperCase() || 'FILE';

  }


  // ==========================================================
  // TÍTULO DO DOCUMENTO
  // ==========================================================

  tituloDocumento(
    documento: DocumentoEstagiario
  ): string {

    if (documento.tipo_documento) {

      return documento.tipo_documento
        .replace(/_/g, ' ')
        .replace(
          /\b\w/g,
          letra => letra.toUpperCase()
        );

    }

    if (documento.nome_arquivo) {

      return documento.nome_arquivo;

    }

    return 'Documento';

  }


  // ==========================================================
  // RÓTULO DO ESTADO DO DOCUMENTO
  // ==========================================================

  rotuloEstadoDocumento(
    documento: DocumentoEstagiario
  ): string {

    const estado =
      documento.estado?.toLowerCase();

    switch (estado) {

      case 'aprovado':

      case 'aceite':

      case 'aceito':

      case 'validado':

        return 'Aprovado';


      case 'recusado':

      case 'rejeitado':

        return 'Recusado';


      case 'pendente':

      case 'em análise':

      case 'em_analise':

        return 'Em análise';


      default:

        return 'Recebido';

    }

  }


  // ==========================================================
  // VISUALIZAR DOCUMENTO
  // ==========================================================

  ver(
    documento: DocumentoEstagiario
  ): void {

    if (
      !isPlatformBrowser(this.platformId)
    ) {

      return;

    }


    if (this.abrindoDocumento()) {

      return;

    }


    this.abrindoDocumento.set(true);

    this.erroDocumento.set(null);

    this.limparPreviewDocumento();


    this.candidaturaService
      .visualizarDocumento(
        documento.id_documento
      )
      .subscribe({

        next: (blob) => {

          try {

            const tipo =
              this.inferirMimeType(
                documento,
                blob
              );

            const url =
              URL.createObjectURL(blob);

            this.documentoPreviewBlobUrl = url;

            this.documentoPreviewTipo.set(tipo);

            this.documentoPreviewNome.set(
              documento.nome_arquivo ||
              this.tituloDocumento(documento)
            );


            if (tipo === 'application/pdf') {

              this.documentoPreviewPdfUrl.set(
                this.sanitizer
                  .bypassSecurityTrustResourceUrl(
                    url
                  )
              );

              this.documentoPreviewUrl.set(null);

            } else {

              this.documentoPreviewUrl.set(url);

              this.documentoPreviewPdfUrl.set(
                null
              );

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

          console.error(
            'Erro ao abrir documento:',
            erro
          );

          this.abrindoDocumento.set(false);

          this.erroDocumento.set(
            'Não foi possível abrir este documento.'
          );

        }

      });

  }


  // ==========================================================
  // IDENTIFICAR MIME TYPE
  // ==========================================================

  private inferirMimeType(
    documento: DocumentoEstagiario,
    blob: Blob
  ): string {

    if (
      blob.type &&
      blob.type !== 'application/octet-stream'
    ) {

      return blob.type;

    }


    const nome =
      (documento.nome_arquivo || '')
        .toLowerCase();


    if (nome.endsWith('.pdf')) {

      return 'application/pdf';

    }

    if (nome.endsWith('.png')) {

      return 'image/png';

    }

    if (
      nome.endsWith('.jpg') ||
      nome.endsWith('.jpeg')
    ) {

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


  // ==========================================================
  // FECHAR PRÉ-VISUALIZAÇÃO
  // ==========================================================

  fecharPreviewDocumento(): void {

    this.documentoPreviewAberto.set(false);

    this.limparPreviewDocumento();

    this.documentoPreviewTipo.set('');

    this.documentoPreviewNome.set('');

    this.abrindoDocumento.set(false);

  }


  // ==========================================================
  // LIMPAR PRÉ-VISUALIZAÇÃO
  // ==========================================================

  private limparPreviewDocumento(): void {

    if (this.documentoPreviewBlobUrl) {

      URL.revokeObjectURL(
        this.documentoPreviewBlobUrl
      );

      this.documentoPreviewBlobUrl = null;

    }

    this.documentoPreviewUrl.set(null);

    this.documentoPreviewPdfUrl.set(null);

  }


  // ==========================================================
  // ENCERRAMENTO DO COMPONENTE
  // ==========================================================

  ngOnDestroy(): void {

    this.limparPreviewDocumento();

  }


  // ==========================================================
  // SUBSTITUIR DOCUMENTO
  // ==========================================================

  substituirDocumento(
    documento: DocumentoEstagiario,
    event: Event
  ): void {

    const input =
      event.target as HTMLInputElement;

    const ficheiro =
      input.files?.[0];


    /*
     * Limpar o campo permite seleccionar
     * novamente o mesmo ficheiro.
     */

    input.value = '';


    if (!ficheiro) {

      return;

    }


    if (
      this.documentoEmSubstituicao() !== null
    ) {

      return;

    }


    // --------------------------------------------------------
    // Verificar o estado da candidatura
    // --------------------------------------------------------

    if (
      this.candidatura()?.estado !== 'analise'
    ) {

      this.erroDocumento.set(
        'Só podes substituir documentos enquanto a candidatura estiver em análise.'
      );

      this.sucessoDocumento.set(null);

      return;

    }


    // --------------------------------------------------------
    // Validar formato do ficheiro
    // --------------------------------------------------------

    const extensao = ficheiro.name
      .split('.')
      .pop()
      ?.toLowerCase();


    if (
      !extensao ||
      ![
        'pdf',
        'jpg',
        'jpeg',
        'png'
      ].includes(extensao)
    ) {

      this.erroDocumento.set(
        'Selecciona um ficheiro PDF, JPG ou PNG.'
      );

      this.sucessoDocumento.set(null);

      return;

    }


    // --------------------------------------------------------
    // Validar tamanho máximo de 5 MB
    // --------------------------------------------------------

    if (
      ficheiro.size > 5 * 1024 * 1024
    ) {

      this.erroDocumento.set(
        'O ficheiro não pode ultrapassar 5 MB.'
      );

      this.sucessoDocumento.set(null);

      return;

    }


    // --------------------------------------------------------
    // Confirmar substituição
    // --------------------------------------------------------

    const confirmar = window.confirm(
      `Pretendes substituir "${documento.nome_arquivo}" por "${ficheiro.name}"?`
    );


    if (!confirmar) {

      return;

    }


    // --------------------------------------------------------
    // Iniciar operação
    // --------------------------------------------------------

    this.documentoEmSubstituicao.set(
      documento.id_documento
    );

    this.erroDocumento.set(null);

    this.sucessoDocumento.set(null);


    // --------------------------------------------------------
    // Enviar nova versão para Laravel
    // --------------------------------------------------------

    this.candidaturaService
      .substituirDocumento(
        documento.id_documento,
        ficheiro
      )
      .subscribe({

        next: () => {

          this.documentoEmSubstituicao.set(null);

          this.sucessoDocumento.set(
            'Documento substituído com sucesso. A nova versão foi enviada para validação.'
          );


          /*
           * Actualizar os documentos e os dados
           * da candidatura a partir do servidor.
           */

          this.carregarPainel();

        },


        error: (erro: unknown) => {

          console.error(
            'Erro ao substituir documento:',
            erro
          );

          this.documentoEmSubstituicao.set(null);


          const status =
            this.obterStatusErro(erro);


          if (status === 401) {

            this.erroDocumento.set(
              'A sessão expirou. Entra novamente no portal.'
            );

            return;

          }


          if (status === 403) {

            this.erroDocumento.set(
              'Não tens autorização para substituir este documento.'
            );

            return;

          }


          if (status === 404) {

            this.erroDocumento.set(
              'O documento não foi encontrado. Actualiza o painel e tenta novamente.'
            );

            return;

          }


          if (status === 409) {

            this.erroDocumento.set(
              'Este documento já tem uma versão mais recente. Actualiza o painel e tenta novamente.'
            );

            return;

          }


          if (status === 422) {

            this.erroDocumento.set(
              'A substituição não foi aceite. Verifica o estado da candidatura e o ficheiro seleccionado.'
            );

            return;

          }


          this.erroDocumento.set(
            'Não foi possível substituir o documento. Tenta novamente.'
          );

        }

      });

  }


  // ==========================================================
  // FORMATAR DATA
  // ==========================================================

  formatarData(
    data: string | null | undefined
  ): string {

    if (!data) {

      return '-';

    }


    const objeto = new Date(data);


    if (
      Number.isNaN(objeto.getTime())
    ) {

      return data;

    }


    return new Intl.DateTimeFormat(
      'pt-PT',
      {

        day: '2-digit',

        month: '2-digit',

        year: 'numeric'

      }
    ).format(objeto);

  }

  // ==========================================================
  // NAVEGAR PARA SECÇÃO
  // ==========================================================

  navegarSecao(id: string): void {
    if (typeof document === 'undefined') {
      return;
    }

    const secao = document.getElementById(id);

    if (!secao) {
      console.warn('Secção não encontrada:', id);
      return;
    }

    secao.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });
  }
}
