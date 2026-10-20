import { CommonModule } from '@angular/common';

import {
  Component,
  inject,
  signal
} from '@angular/core';

import { FormsModule } from '@angular/forms';

import { Router } from '@angular/router';

import {
  SupervisorService,
  CriarSupervisor
} from '../../../services/supervisor';

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
export class PainelCoordenadorComponent {

  private readonly supervisorService =
    inject(SupervisorService);

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

      password_confirmation:
        this.passwordConfirmation,

      telefone:
        this.telefone.trim() || undefined

    };


    this.supervisorService
      .criar(dados)
      .subscribe({

        next: resposta => {

          this.guardando.set(false);

          this.sucesso.set(
            resposta.message ||
            'Conta de supervisor criada com sucesso.'
          );

          this.limparFormulario();

        },

        error: erro => {

          this.guardando.set(false);

          console.error(
            'Erro ao criar supervisor:',
            erro
          );

          this.erro.set(
            this.mensagemErro(erro)
          );

        }

      });

  }


  // ============================================================
  // LIMPAR FORMULÁRIO
  // ============================================================

  limparFormulario(): void {

  this.nome = '';

  this.email = '';

  this.telefone = '';

  this.password = '';

  this.passwordConfirmation = '';

}


  // ============================================================
  // INTERPRETAR ERROS DA API
  // ============================================================

  private mensagemErro(erro: any): string {

    if (
      erro.status === 422 &&
      erro.error?.errors
    ) {

      const mensagens =
        Object.values(
          erro.error.errors
        ) as string[][];

      return mensagens
        .flat()
        .join(' ');

    }


    if (erro.status === 401) {

      return (
        'A sessão expirou. Inicia sessão novamente.'
      );

    }


    if (erro.status === 403) {

      return (
        erro.error?.message ||
        'Não tens permissão para criar supervisores.'
      );

    }


    return (
      erro.error?.message ||
      'Não foi possível criar a conta. Verifica os dados e tenta novamente.'
    );

  }


  // ============================================================
  // ENCERRAR SESSÃO
  // ============================================================

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

          // Mesmo que a API falhe, regressar ao acesso.
          this.router.navigate([
            '/acesso'
          ]);

        }

      });

  }

}
