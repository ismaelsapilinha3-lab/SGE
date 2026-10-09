import { HttpClient } from '@angular/common/http';
import { Injectable, PLATFORM_ID, computed, inject, signal } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { Observable, catchError, map, of, tap } from 'rxjs';
import { environment } from '../../environments/environment';

/** Rota da página de login/cadastro. Confirma que coincide com o app.routes.ts. */
export const ROTA_ACESSO = '/acesso';

export interface Utilizador {
  nome: string;
  email: string;
  tipo_utilizador: string;
}

export interface RespostaAuth {
  token: string;
  user: Utilizador;
}

const CHAVE = 'multitel_auth';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);
  private platformId = inject(PLATFORM_ID);

  private sessao = signal<RespostaAuth | null>(this.lerSessao());

  readonly utilizador = computed(() => this.sessao()?.user ?? null);

  token(): string | null {
    return this.sessao()?.token ?? null;
  }

  autenticado(): boolean {
    return this.token() !== null;
  }

  login(email: string, password: string): Observable<Utilizador> {
    return this.http
      .post<RespostaAuth>(`${environment.apiUrl}/login`, { email, password })
      .pipe(
        tap(resposta => this.iniciarSessao(resposta)),
        map(resposta => resposta.user)
      );
  }

  /** Usado também depois do cadastro, que já devolve o token. */
  iniciarSessao(resposta: RespostaAuth): void {
    if (this.noBrowser()) {
      // sessionStorage: a sessão termina quando o separador é fechado
      sessionStorage.setItem(CHAVE, JSON.stringify({ token: resposta.token, user: resposta.user }));
    }
    this.sessao.set({ token: resposta.token, user: resposta.user });
  }

  /** Termina a sessão no servidor (apaga o token) e localmente. Nunca dá erro. */
  logout(): Observable<void> {
    if (!this.autenticado()) {
      this.terminarLocal();
      return of(undefined);
    }

    return this.http.post(`${environment.apiUrl}/logout`, {}).pipe(
      catchError(() => of(null)),
      tap(() => this.terminarLocal()),
      map(() => undefined)
    );
  }

  /** Limpa a sessão sem chamar a API (ex.: token expirado, resposta 401). */
  terminarLocal(): void {
    if (this.noBrowser()) {
      sessionStorage.removeItem(CHAVE);
    }
    this.sessao.set(null);
  }

  private lerSessao(): RespostaAuth | null {
    if (!this.noBrowser()) {
      return null;
    }

    this.limparDadosAntigos();

    try {
      const bruto = sessionStorage.getItem(CHAVE);
      return bruto ? (JSON.parse(bruto) as RespostaAuth) : null;
    } catch {
      return null;
    }
  }

  /** Versão antiga guardava nome, BI e contacto em claro no navegador. */
  private limparDadosAntigos(): void {
    localStorage.removeItem('multitel_estagiario');
    sessionStorage.removeItem('multitel_sessao');
  }

  private noBrowser(): boolean {
    return isPlatformBrowser(this.platformId);
  }
}