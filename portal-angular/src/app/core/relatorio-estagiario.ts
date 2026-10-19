import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';
import { AuthService } from './auth.service';

export type TipoRelatorio = 'semanal' | 'mensal' | 'final';
export type EstadoAvaliacao = 'pendente' | 'aprovado' | 'rejeitado';

export interface RelatorioEstagiario {
  id_relatorio: number;
  id_estagio: number;
  tipo: TipoRelatorio;
  periodo_referencia: string;
  atividades: string;
  resultados: string;
  constrangimentos?: string | null;
  objetivos?: string | null;
  submetido_em: string;
  estado_avaliacao: EstadoAvaliacao;
  comentario_admin?: string | null;
  ficheiro_nome?: string | null;
}

export interface NovoRelatorio {
  tipo: TipoRelatorio;
  periodo_referencia: string;
  atividades: string;
  resultados: string;
  constrangimentos?: string;
  objetivos?: string;
}

@Injectable({
  providedIn: 'root'
})
export class RelatorioEstagiarioService {
  private readonly http = inject(HttpClient);
  private readonly authService = inject(AuthService);
  private readonly url = `${environment.apiUrl}/estagiario/relatorios`;

  private obterCabecalhos(): HttpHeaders {
    let headers = new HttpHeaders({
      Accept: 'application/json'
    });

    const token = this.authService.token();

    if (token) {
      headers = headers.set('Authorization', `Bearer ${token}`);
    }

    return headers;
  }

  listar(): Observable<RelatorioEstagiario[]> {
    return this.http.get<RelatorioEstagiario[]>(
      this.url,
      { headers: this.obterCabecalhos() }
    );
  }

  submeter(dados: NovoRelatorio): Observable<unknown> {
    return this.http.post(
      this.url,
      dados,
      { headers: this.obterCabecalhos() }
    );
  }
}