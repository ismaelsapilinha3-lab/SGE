import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { finalize } from 'rxjs';

import { AuthService } from '../../core/auth.service';

type ModoRecuperacao = 'pedido' | 'redefinicao';

type TipoMensagem = '' | 'ok' | 'erro';

@Component({
  selector: 'app-recuperar-password',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule
  ],
  templateUrl: './recuperar-password.html',
  styleUrls: [
    './acesso.css',
    './recuperar-password.css'
  ]
})
export class RecuperarPasswordComponent implements OnInit {

  modo: ModoRecuperacao = 'pedido';

  email = '';
  token = '';

  password = '';
  passwordConfirmation = '';

  aProcessar = false;
  linkInvalido = false;

  mensagem: { texto: string; tipo: TipoMensagem } = {
    texto: '',
    tipo: ''
  };

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private authService: AuthService
  ) {}

  ngOnInit(): void {
    this.modo = this.route.snapshot.data['modo'] === 'redefinicao'
      ? 'redefinicao'
      : 'pedido';

    if (this.modo !== 'redefinicao') {
      return;
    }

    const parametros = this.route.snapshot.queryParamMap;

    this.token = parametros.get('token') ?? '';
    this.email = parametros.get('email') ?? '';

    if (! this.token || ! this.email) {
      this.linkInvalido = true;
      this.mensagem = {
        texto: 'O link de recuperação está incompleto. Pede um novo link.',
        tipo: 'erro'
      };
    }
  }

  submeterPedido(): void {
    if (this.aProcessar) {
      return;
    }

    const email = this.email.trim();

    if (! this.emailValido(email)) {
      this.mensagem = {
        texto: 'Indica um e-mail válido.',
        tipo: 'erro'
      };
      return;
    }

    this.aProcessar = true;
    this.mensagem = { texto: '', tipo: '' };

    this.authService
      .pedirRecuperacao(email)
      .pipe(finalize(() => this.aProcessar = false))
      .subscribe({
        next: (resposta) => {
          this.mensagem = {
            texto: resposta.message,
            tipo: 'ok'
          };
        },
        error: (erro: HttpErrorResponse) => {
          this.mensagem = {
            texto: this.textoDoErro(erro),
            tipo: 'erro'
          };
        }
      });
  }

  submeterRedefinicao(): void {
    if (this.aProcessar || this.linkInvalido) {
      return;
    }

    if (! this.passwordValida()) {
      this.mensagem = {
        texto: 'A palavra-passe deve ter pelo menos 8 caracteres, com letras e números.',
        tipo: 'erro'
      };
      return;
    }

    if (this.password !== this.passwordConfirmation) {
      this.mensagem = {
        texto: 'As palavras-passe não coincidem.',
        tipo: 'erro'
      };
      return;
    }

    this.aProcessar = true;
    this.mensagem = { texto: '', tipo: '' };

    this.authService
      .redefinirPassword({
        token: this.token,
        email: this.email,
        password: this.password,
        password_confirmation: this.passwordConfirmation
      })
      .pipe(finalize(() => this.aProcessar = false))
      .subscribe({
        next: (resposta) => {
          this.mensagem = {
            texto: resposta.message,
            tipo: 'ok'
          };

          setTimeout(() => this.router.navigate(['/acesso']), 1400);
        },
        error: (erro: HttpErrorResponse) => {
          this.mensagem = {
            texto: this.textoDoErro(erro),
            tipo: 'erro'
          };
        }
      });
  }

  pedirNovoLink(): void {
    this.router.navigate(['/recuperar-password']);
  }

  voltarParaLogin(): void {
    this.router.navigate(['/acesso']);
  }

  voltarParaPaginaInicial(): void {
    this.router.navigate(['/telainicial']);
  }

  private emailValido(email: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  private passwordValida(): boolean {
    return this.password.length >= 8
      && /[A-Za-z]/.test(this.password)
      && /\d/.test(this.password);
  }

  private textoDoErro(erro: HttpErrorResponse): string {
    if (erro.status === 429) {
      return 'Demasiados pedidos. Aguarda alguns minutos e tenta novamente.';
    }

    const erros = erro.error?.errors;

    if (erros && typeof erros === 'object') {
      const primeiro = Object.values(erros)[0];

      if (Array.isArray(primeiro) && primeiro.length > 0) {
        return String(primeiro[0]);
      }
    }

    return erro.error?.message
      ?? 'Não foi possível concluir a operação. Tenta novamente.';
  }
}
