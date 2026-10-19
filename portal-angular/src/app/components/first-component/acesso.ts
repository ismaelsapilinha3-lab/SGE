import { CommonModule } from '@angular/common';

import {
  HttpErrorResponse
} from '@angular/common/http';

import {
  Component,
  OnInit
} from '@angular/core';

import {
  FormsModule
} from '@angular/forms';

import {
  Router
} from '@angular/router';

import {
  CandidaturaService,
  Departamento
} from '../../services/candidatura.service';

import {
  AuthService,
  RespostaAuth
} from '../../core/auth.service';


// ============================================================
// INTERFACES
// ============================================================

interface DadosEstagiario {
  nome: string;
  curso: string;
  universidade: string;
  nascimento: string;
  sexo: string;
  bi: string;
  email: string;
  contacto: string;
  id_departamento_selecionado: number | null;
  password: string;
  password_confirmation: string;
}

interface DocumentoUpload {
  key: string;
  rotulo: string;
  dica: string;
  accept: string;
  arquivo: File | null;
}

interface Mensagem {
  texto: string;
  tipo: '' | 'ok' | 'erro';
}


// ============================================================
// COMPONENTE DE ACESSO
// ============================================================

@Component({
  selector: 'app-acesso',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule
  ],
  templateUrl: './acesso.html',
  styleUrls: ['./acesso.css']
})
export class AcessoComponent implements OnInit {


  // ==========================================================
  // CONTROLO DAS ABAS
  // ==========================================================

  abaAtiva: 'login' | 'cadastro' = 'login';


  // ==========================================================
  // LOGIN
  // ==========================================================

  login = {
    email: '',
    senha: ''
  };

  loginMensagem: Mensagem = {
    texto: '',
    tipo: ''
  };

  entrando = false;


  // ==========================================================
  // DADOS DO CADASTRO
  // ==========================================================

  cadastro: DadosEstagiario = {
    nome: '',
    curso: '',
    universidade: '',
    nascimento: '',
    sexo: '',
    bi: '',
    email: '',
    contacto: '',
    id_departamento_selecionado: null,
    password: '',
    password_confirmation: ''
  };

  cadastroMensagem: Mensagem = {
    texto: '',
    tipo: ''
  };

  enviando = false;


  // ==========================================================
  // DEPARTAMENTOS
  // ==========================================================

  departamentos: Departamento[] = [];


  // ==========================================================
  // DOCUMENTOS EXIGIDOS
  // ==========================================================

  documentos: DocumentoUpload[] = [

    {
      key: 'declaracao_escolar',
      rotulo: 'Declaração <br>ou<br> Certificado Académico',
      dica: 'Arraste ou clique para escolher',
      accept: '.pdf,.jpg,.jpeg,.png',
      arquivo: null
    },

    {
      key: 'foto',
      rotulo: 'Foto tipo passe',
      dica: 'Arraste ou clique para escolher',
      accept: '.jpg,.jpeg,.png',
      arquivo: null
    },

    {
      key: 'bi',
      rotulo: 'Cópia do BI <br>ou<br> Passaporte',
      dica: 'Arraste ou clique para escolher',
      accept: '.pdf,.jpg,.jpeg,.png',
      arquivo: null
    },

    {
      key: 'carta_solicitacao',
      rotulo: 'Carta de solicitação',
      dica: 'Indica a área em que queres trabalhar (PDF)',
      accept: '.pdf',
      arquivo: null
    },

    {
      key: 'cv',
      rotulo: 'Curriculum Vitae',
      dica: 'Arraste ou clique para escolher',
      accept: '.pdf,.jpg,.jpeg,.png',
      arquivo: null
    }

  ];


  // ==========================================================
  // CONSTRUTOR
  // ==========================================================

  constructor(
    private router: Router,
    private candidaturaService: CandidaturaService,
    private authService: AuthService
  ) {}


  // ==========================================================
  // INICIALIZAÇÃO
  // ==========================================================

  ngOnInit(): void {

    this.carregarDepartamentos();
  }


  // ==========================================================
  // CARREGAR DEPARTAMENTOS
  // ==========================================================

  private carregarDepartamentos(): void {

    this.candidaturaService
      .listarDepartamentos()
      .subscribe({

        next: (departamentos: Departamento[]) => {

          this.departamentos = departamentos;
        },

        error: () => {

          this.cadastroMensagem = {
            texto: 'Não foi possível carregar os departamentos.',
            tipo: 'erro'
          };
        }

      });
  }


  // ==========================================================
  // ALTERNAR ENTRE LOGIN E CADASTRO
  // ==========================================================

  mostrarFormulario(
    tipo: 'login' | 'cadastro'
  ): void {

    this.abaAtiva = tipo;

    this.loginMensagem = {
      texto: '',
      tipo: ''
    };

    this.cadastroMensagem = {
      texto: '',
      tipo: ''
    };
  }


  // ==========================================================
  // SELECIONAR DOCUMENTO
  // ==========================================================

  onFileChange(
    event: Event,
    documento: DocumentoUpload
  ): void {

    const input =
      event.target as HTMLInputElement;

    if (
      input.files &&
      input.files.length > 0
    ) {

      documento.arquivo = input.files[0];

    } else {

      documento.arquivo = null;
    }
  }


  // ==========================================================
  // RECEBER DOCUMENTO POR ARRASTO
  // ==========================================================

  onFileDrop(
    event: DragEvent,
    documento: DocumentoUpload
  ): void {

    event.preventDefault();

    const arquivos =
      event.dataTransfer?.files;

    if (
      arquivos &&
      arquivos.length > 0
    ) {

      documento.arquivo = arquivos[0];
    }
  }


  // ==========================================================
  // SUBMETER CADASTRO
  // ==========================================================

  onSubmitCadastro(): void {

    if (this.enviando) {
      return;
    }

    this.cadastroMensagem = {
      texto: '',
      tipo: ''
    };


    // ----------------------------------------------------------
    // VALIDAR DOCUMENTOS
    // ----------------------------------------------------------

    const documentosEmFalta =
      this.documentos.filter(
        documento => !documento.arquivo
      );

    if (documentosEmFalta.length > 0) {

      this.cadastroMensagem = {
        texto: 'Envia todos os documentos antes de continuar.',
        tipo: 'erro'
      };

      return;
    }


    // ----------------------------------------------------------
    // VALIDAR DEPARTAMENTO
    // ----------------------------------------------------------

    if (
      this.cadastro.id_departamento_selecionado === null
    ) {

      this.cadastroMensagem = {
        texto: 'Seleciona o departamento pretendido.',
        tipo: 'erro'
      };

      return;
    }


    // ----------------------------------------------------------
    // VALIDAR PALAVRA-PASSE
    // ----------------------------------------------------------

    if (
      this.cadastro.password.length < 8
    ) {

      this.cadastroMensagem = {
        texto: 'A palavra-passe deve ter pelo menos 8 caracteres.',
        tipo: 'erro'
      };

      return;
    }


    // ----------------------------------------------------------
    // CONFIRMAR PALAVRA-PASSE
    // ----------------------------------------------------------

    if (
      this.cadastro.password !==
      this.cadastro.password_confirmation
    ) {

      this.cadastroMensagem = {
        texto: 'As palavras-passe não coincidem.',
        tipo: 'erro'
      };

      return;
    }


    // ----------------------------------------------------------
    // ENVIAR CANDIDATURA
    // ----------------------------------------------------------

    this.enviando = true;

    const dadosParaEnviar = {
      ...this.cadastro,
      id_departamento_selecionado:
        this.cadastro.id_departamento_selecionado
    };

    this.candidaturaService
      .submeter(
        dadosParaEnviar,
        this.documentos
      )
      .subscribe({

        next: (resposta: RespostaAuth) => {

          // Guardar a sessão devolvida pela API.
          this.authService.iniciarSessao(
            resposta
          );

          this.cadastroMensagem = {
            texto: 'Candidatura enviada com sucesso!',
            tipo: 'ok'
          };

          this.enviando = false;

          setTimeout(() => {

            this.router.navigate([
              '/estagiario'
            ]);

          }, 600);
        },

        error: (
          erro: HttpErrorResponse
        ) => {

          this.enviando = false;

          this.tratarErroCadastro(erro);
        }

      });
  }


  // ============================================================
  // SUBMETER LOGIN
  // ============================================================

  onSubmitLogin(): void {

    if (this.entrando) {
      return;
    }

    this.loginMensagem = {
      texto: '',
      tipo: ''
    };


    // ----------------------------------------------------------
    // VALIDAR E-MAIL
    // ----------------------------------------------------------

    if (!this.login.email.trim()) {

      this.loginMensagem = {
        texto: 'Digite o seu e-mail.',
        tipo: 'erro'
      };

      return;
    }


    // ----------------------------------------------------------
    // VALIDAR PALAVRA-PASSE
    // ----------------------------------------------------------

    if (!this.login.senha) {

      this.loginMensagem = {
        texto: 'Digite a sua palavra-passe.',
        tipo: 'erro'
      };

      return;
    }


    // ----------------------------------------------------------
    // AUTENTICAR UTILIZADOR
    // ----------------------------------------------------------

    this.entrando = true;

    this.authService
      .login(
        this.login.email.trim(),
        this.login.senha
      )
      .subscribe({

        next: (utilizador) => {

          const tipoUtilizador =
            utilizador.tipo_utilizador
              ?.trim()
              .toLowerCase();


          // ------------------------------------------------------
          // DETERMINAR DESTINO
          // ------------------------------------------------------

          let destino: string;

          switch (tipoUtilizador) {

            case 'administrador':

              destino = '/admin';

              break;

            case 'coordenador':

              destino = '/coordenador';

              break;

            case 'estagiario':

              destino = '/estagiario';

              break;

            case 'supervisor':

              destino = '/supervisor';

              break;

            default:

              this.entrando = false;

              this.loginMensagem = {
                texto:
                  `Perfil não reconhecido: ${utilizador.tipo_utilizador || 'não informado'}.`,
                tipo: 'erro'
              };

              return;
          }


          // ------------------------------------------------------
          // CONFIRMAR LOGIN
          // ------------------------------------------------------

          this.loginMensagem = {
            texto: 'Acesso autorizado. A abrir a tua área...',
            tipo: 'ok'
          };

          this.entrando = false;


          // ------------------------------------------------------
          // REDIRECIONAR PARA O PAINEL
          // ------------------------------------------------------

          setTimeout(() => {

            this.router
              .navigate([destino])
              .then(navegou => {

                if (!navegou) {

                  this.loginMensagem = {
                    texto:
                      `Não foi possível abrir ${destino}. Verifica as rotas e as permissões.`,
                    tipo: 'erro'
                  };
                }

              })
              .catch((erro: unknown) => {

                console.error(
                  'Erro ao redirecionar após login:',
                  erro
                );

                this.loginMensagem = {
                  texto: 'O login foi efetuado, mas ocorreu um erro ao abrir o painel.',
                  tipo: 'erro'
                };
              });

          }, 400);
        },


        // --------------------------------------------------------
        // TRATAR ERRO DE LOGIN
        // --------------------------------------------------------

        error: (
          erro: HttpErrorResponse
        ) => {

          this.entrando = false;

          if (
            erro.status === 422 ||
            erro.status === 401
          ) {

            this.loginMensagem = {
              texto: 'E-mail ou palavra-passe incorretos.',
              tipo: 'erro'
            };

            return;
          }

          this.loginMensagem = {
            texto:
              erro?.error?.message ??
              'Não foi possível iniciar sessão.',
            tipo: 'erro'
          };
        }

      });
  }


  // ============================================================
  // TRATAR ERROS DO CADASTRO
  // ============================================================

  private tratarErroCadastro(
    erro: HttpErrorResponse
  ): void {

    // ----------------------------------------------------------
    // LIMITE DE TENTATIVAS
    // ----------------------------------------------------------

    if (erro.status === 429) {

      this.cadastroMensagem = {
        texto: 'Demasiadas tentativas. Aguarda um minuto.',
        tipo: 'erro'
      };

      return;
    }


    // ----------------------------------------------------------
    // ERROS DE VALIDAÇÃO DO LARAVEL
    // ----------------------------------------------------------

    const erros =
      erro?.error?.errors;

    if (erros) {

      const primeiroCampo =
        Object.keys(erros)[0];

      const mensagens =
        erros[primeiroCampo];

      if (
        mensagens &&
        mensagens.length > 0
      ) {

        this.cadastroMensagem = {
          texto: mensagens[0],
          tipo: 'erro'
        };

        return;
      }
    }


    // ----------------------------------------------------------
    // ERRO GERAL
    // ----------------------------------------------------------

    this.cadastroMensagem = {
      texto:
        erro?.error?.message ??
        'Erro ao enviar a candidatura.',
      tipo: 'erro'
    };
  }


  // ============================================================
  // VOLTAR À PÁGINA INICIAL
  // ============================================================

  voltarParaPaginaInicial(): void {

    this.router.navigate([
      '/telainicial'
    ]);
  }

}
