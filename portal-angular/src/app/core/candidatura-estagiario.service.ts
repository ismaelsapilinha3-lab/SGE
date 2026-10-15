import { Injectable, inject } from '@angular/core';

import { HttpClient } from '@angular/common/http';

import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';


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


@Injectable({
  providedIn: 'root'
})
export class CandidaturaEstagiarioService {

  private readonly http = inject(HttpClient);

  private readonly url =
    `${environment.apiUrl}/estagiario/candidatura`;


  // ============================================================
  // OBTER A CANDIDATURA DO ESTAGIÁRIO
  // ============================================================

  minhaCandidatura(): Observable<CandidaturaEstagiario> {

    return this.http.get<CandidaturaEstagiario>(
      this.url
    );

  }


  // ============================================================
  // VISUALIZAR DOCUMENTO
  // ============================================================

  visualizarDocumento(
    idDocumento: number
  ): Observable<Blob> {

    return this.http.get(
      `${environment.apiUrl}/documentos/${idDocumento}`,
      {
        responseType: 'blob'
      }
    );

  }


  // ============================================================
  // SUBSTITUIR DOCUMENTO POR UMA NOVA VERSÃO
  // ============================================================

  substituirDocumento(
    idDocumento: number,
    ficheiro: File
  ): Observable<unknown> {

    const formData = new FormData();

    formData.append(
      'ficheiro',
      ficheiro
    );

    return this.http.post(
      `${environment.apiUrl}/documentos/${idDocumento}/substituir`,
      formData
    );

  }

}
