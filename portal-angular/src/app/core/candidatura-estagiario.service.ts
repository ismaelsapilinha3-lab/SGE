
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';
import { AuthService } from './auth.service';


/* =========================================================
   DOCUMENTO DO ESTAGIÁRIO
   ========================================================= */

export interface DocumentoEstagiario {
  id_documento: number;
  id_candidatura: number;
  tipo_documento: string;
  versao?: number;
  nome_arquivo: string;
  caminho_arquivo: string;
  data_upload: string;
  validade?: string | null;
  estado?: string | null;
  descricao?: string | null;
}


/* =========================================================
   CANDIDATURA DO ESTAGIÁRIO
   ========================================================= */

export interface CandidaturaEstagiario {
  id: number;
  nome: string;
  bi: string;
  contacto: string;
  email: string;
  nascimento: string;
  sexo: string;
  curso: string;
  universidade: string;
  departamento: string;
  estado: 'analise' | 'aceite' | 'recusado';
  data_submissao: string;
  documentos: DocumentoEstagiario[];
}


/* =========================================================
   SERVIÇO DE ACOMPANHAMENTO DO ESTAGIÁRIO
   ========================================================= */

@Injectable({
  providedIn: 'root'
})
export class CandidaturaEstagiarioService {

  private readonly http = inject(HttpClient);
  private readonly authService = inject(AuthService);

  private readonly url =
    `${environment.apiUrl}/estagiario/candidatura`;


  /* =======================================================
     CABEÇALHOS DE AUTENTICAÇÃO
     ======================================================= */

  private obterCabecalhos(): HttpHeaders {
    let headers = new HttpHeaders({
      Accept: 'application/json'
    });

    const token = this.authService.token();

    if (token) {
      headers = headers.set(
        'Authorization',
        `Bearer ${token}`
      );
    }

    return headers;
  }


  /* =======================================================
     CARREGAR A CANDIDATURA DO ESTAGIÁRIO
     ======================================================= */

  minhaCandidatura(): Observable<CandidaturaEstagiario> {
    return this.http.get<CandidaturaEstagiario>(
      this.url,
      {
        headers: this.obterCabecalhos()
      }
    );
  }


  /* =======================================================
     VISUALIZAR OU DESCARREGAR UM DOCUMENTO
     ======================================================= */

  visualizarDocumento(idDocumento: number): Observable<Blob> {
    return this.http.get(
      `${environment.apiUrl}/documentos/${idDocumento}`,
      {
        headers: this.obterCabecalhos(),
        responseType: 'blob'
      }
    );
  }

}
