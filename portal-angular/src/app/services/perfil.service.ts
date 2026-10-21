import { Injectable, inject } from '@angular/core';

import { HttpClient } from '@angular/common/http';

import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';


export interface PerfilUtilizador {

  id_utilizador: number;

  nome: string;

  email: string;

  telefone: string | null;

  tipo_utilizador: string;

  foto: string | null;

  foto_url: string | null;

}


export interface RespostaFoto {

  message: string;

  foto: string | null;

  foto_url: string | null;

}


/**
 * Perfil do próprio utilizador autenticado, incluindo a foto.
 */
@Injectable({ providedIn: 'root' })
export class PerfilService {

  private readonly http = inject(HttpClient);

  private readonly apiUrl = `${environment.apiUrl}/perfil`;

  /**
   * Dados do perfil autenticado (nome, e-mail, foto...).
   */
  mostrar(): Observable<{ utilizador: PerfilUtilizador }> {

    return this.http.get<{ utilizador: PerfilUtilizador }>(
      this.apiUrl
    );

  }

  /**
   * Envia uma nova foto de perfil. O ficheiro vai em multipart/form-data.
   */
  atualizarFoto(foto: File): Observable<RespostaFoto> {

    const dados = new FormData();

    dados.append('foto', foto);

    return this.http.post<RespostaFoto>(
      `${this.apiUrl}/foto`,
      dados
    );

  }

  /**
   * Remove a foto de perfil atual.
   */
  removerFoto(): Observable<RespostaFoto> {

    return this.http.delete<RespostaFoto>(
      `${this.apiUrl}/foto`
    );

  }

}
