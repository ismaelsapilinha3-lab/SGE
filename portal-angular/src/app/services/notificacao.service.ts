import { Injectable, inject } from '@angular/core';

import { HttpClient, HttpParams } from '@angular/common/http';

import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';


export interface Notificacao {

  id_notificacao: number;

  id_utilizador: number | null;

  id_candidatura: number | null;

  canal: string;

  titulo: string;

  mensagem: string;

  enviada_em: string | null;

  lida_em: string | null;

}


export interface ResumoNotificacoes {

  nao_lidas: number;

  total: number;

}


export interface ListaNotificacoes {

  notificacoes: Notificacao[];

  resumo: ResumoNotificacoes;

}


export interface RespostaMarcacaoLida {

  message: string;

  notificacao: Notificacao;

}


export interface RespostaMarcacaoTodasLidas {

  message: string;

  atualizadas: number;

}


export interface RespostaSimples {

  message: string;

}


@Injectable({
  providedIn: 'root'
})
export class NotificacaoService {

  private readonly http = inject(HttpClient);

  private readonly apiUrl =
    `${environment.apiUrl}/notificacoes`;


  /**
   * Notificações do utilizador autenticado, da mais recente
   * para a mais antiga. A API devolve no máximo 50 registos.
   *
   * Com `apenasNaoLidas` a lista é filtrada no servidor.
   */
  listar(
    apenasNaoLidas = false
  ): Observable<ListaNotificacoes> {

    let params = new HttpParams();

    if (apenasNaoLidas) {

      params = params.set('nao_lidas', '1');

    }

    return this.http.get<ListaNotificacoes>(
      this.apiUrl,
      {
        params
      }
    );

  }


  /**
   * Marca uma única notificação como lida.
   */
  marcarComoLida(
    id: number
  ): Observable<RespostaMarcacaoLida> {

    return this.http.patch<RespostaMarcacaoLida>(
      `${this.apiUrl}/${id}/ler`,
      {}
    );

  }


  /**
   * Marca todas as notificações não lidas como lidas.
   * Devolve quantas foram atualizadas.
   */
  marcarTodasComoLidas(): Observable<
    RespostaMarcacaoTodasLidas
  > {

    return this.http.patch<
      RespostaMarcacaoTodasLidas
    >(
      `${this.apiUrl}/ler-todas`,
      {}
    );

  }


  /**
   * Remove definitivamente uma notificação do utilizador.
   */
  remover(
    id: number
  ): Observable<RespostaSimples> {

    return this.http.delete<RespostaSimples>(
      `${this.apiUrl}/${id}`
    );

  }

}
