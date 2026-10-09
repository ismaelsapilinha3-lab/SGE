import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import {
  CandidaturaService,
  Departamento
} from '../../services/candidatura.service';

import {
  AuthService,
  RespostaAuth
} from '../../core/auth.service';

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

@Component({
  selector: 'app-acesso',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './acesso.html',
  styleUrls: ['./acesso.css']
})
export class AcessoComponent implements OnInit {

  abaAtiva: 'login' | 'cadastro' = 'login';

  login = {
    email: '',
    senha: ''
  };

  loginMensagem: Mensagem = {
    texto: '',
    tipo: ''
  };

  entrando = false;

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

  departamentos: Departamento[] = [];

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

  constructor(
    private router: Router,
    private candidaturaService: CandidaturaService,
    private authService: AuthService
  ) {}

  ngOnInit(): void {
    this.carregarDepartamentos();
  }

  private carregarDepartamentos(): void {
    this.candidaturaService.listarDepartamentos().subscribe({
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

  mostrarFormulario(tipo: 'login' | 'cadastro'): void {
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

  onFileChange(
    event: Event,
    documento: DocumentoUpload
  ): void {
    const input = event.target as HTMLInputElement;

    if (
      input.files &&
      input.files.length > 0
    ) {
      documento.arquivo = input.files[0];
    } else {
      documento.arquivo = null;
    }
  }

  onFileDrop(
    event: DragEvent,
    documento: DocumentoUpload
  ): void {
    event.preventDefault();

    const arquivos = event.dataTransfer?.files;

    if (
      arquivos &&
      arquivos.length > 0
    ) {
      documento.arquivo = arquivos[0];
    }
  }

  onSubmitCadastro(): void {

    if (this.enviando) {
      return;
    }

    this.cadastroMensagem = {
      texto: '',
      tipo: ''
    };

    /*
     * Verificar documentos
     */
    const documentosEmFalta =
      this.documentos.filter(
        documento => !documento.arquivo
      );

    if (documentosEmFalta.length > 0) {
      this.cadastroMensagem = {
        texto:
          'Envia todos os documentos antes de continuar.',
        tipo: 'erro'
      };

      return;
    }

    /*
     * Verificar departamento
     */
    if (
      this.cadastro.id_departamento_selecionado === null
    ) {
      this.cadastroMensagem = {
        texto:
          'Seleciona o departamento pretendido.',
        tipo: 'erro'
      };

      return;
    }

    /*
     * Verificar palavra-passe
     */
    if (
      this.cadastro.password.length < 8
    ) {
      this.cadastroMensagem = {
        texto:
          'A palavra-passe deve ter pelo menos 8 caracteres.',
        tipo: 'erro'
      };

      return;
    }

    /*
     * Confirmar palavra-passe
     */
    if (
      this.cadastro.password !==
      this.cadastro.password_confirmation
    ) {
      this.cadastroMensagem = {
        texto:
          'As palavras-passe não coincidem.',
        tipo: 'erro'
      };

      return;
    }

    this.enviando = true;

    /*
     * Neste ponto o departamento já foi
     * validado como number.
     */
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

          /*
           * Guardar a sessão recebida
           * pelo Laravel/Sanctum.
           */
          this.authService.iniciarSessao(
            resposta
          );

          this.cadastroMensagem = {
            texto:
              'Candidatura enviada com sucesso!',
            tipo: 'ok'
          };

          this.enviando = false;

          /*
           * Pequeno atraso apenas para
           * permitir que a mensagem seja vista.
           */
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

          this.tratarErroCadastro(
            erro
          );
        }
      });
  }

  onSubmitLogin(): void {

    if (this.entrando) {
      return;
    }

    this.loginMensagem = {
      texto: '',
      tipo: ''
    };

    /*
     * Verificar e-mail
     */
    if (!this.login.email.trim()) {
      this.loginMensagem = {
        texto:
          'Digite o seu e-mail.',
        tipo: 'erro'
      };

      return;
    }

    /*
     * Verificar palavra-passe
     */
    if (!this.login.senha) {
      this.loginMensagem = {
        texto:
          'Digite a sua palavra-passe.',
        tipo: 'erro'
      };

      return;
    }

    this.entrando = true;

    this.authService
      .login(
        this.login.email.trim(),
        this.login.senha
      )
      .subscribe({

        /*
         * O AuthService devolve o utilizador
         * autenticado pelo Laravel.
         */
        next: (utilizador) => {

          this.loginMensagem = {
            texto:
              'Acesso autorizado. A abrir a tua área...',
            tipo: 'ok'
          };

          this.entrando = false;

          /*
           * O destino depende do tipo de utilizador.
           */
          setTimeout(() => {

            switch (utilizador.tipo_utilizador) {

              case 'administrador':
                this.router.navigate(['/admin']);
                break;

              case 'estagiario':
                this.router.navigate(['/estagiario']);
                break;

              default:
                this.router.navigate(['/telainicial']);
                break;
            }

          }, 400);
        },

        error: (
          erro: HttpErrorResponse
        ) => {

          this.entrando = false;

          if (erro.status === 422) {
            this.loginMensagem = {
              texto:
                'E-mail ou palavra-passe incorretos.',
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

  private tratarErroCadastro(
    erro: HttpErrorResponse
  ): void {

    /*
     * Limite de tentativas
     */
    if (erro.status === 429) {
      this.cadastroMensagem = {
        texto:
          'Demasiadas tentativas. Aguarda um minuto.',
        tipo: 'erro'
      };

      return;
    }

    /*
     * Erros de validação enviados pelo Laravel
     *
     * Exemplo:
     * {
     *   errors: {
     *     email: ['Este e-mail já existe.']
     *   }
     * }
     */
    const erros = erro?.error?.errors;

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

    /*
     * Erro geral enviado pelo Laravel
     */
    this.cadastroMensagem = {
      texto:
        erro?.error?.message ??
        'Erro ao enviar a candidatura.',
      tipo: 'erro'
    };
  }

  voltarParaPaginaInicial(): void {
    this.router.navigate([
      '/telainicial'
    ]);
  }
}

