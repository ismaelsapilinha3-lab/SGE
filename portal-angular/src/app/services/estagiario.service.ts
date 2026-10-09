import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

export interface DocumentoEstagiario {
  id_documento: number;
  id_candidatura: number;

  tipo_documento: string;

  nome_arquivo: string;
  caminho_arquivo: string;

  data_upload?: string | null;
  validade?: string | null;

  estado?: string | null;
  descricao?: string | null;
}

export type EstadoCandidatura =
  | 'analise'
  | 'aceite'
  | 'recusado';

export interface MinhaCandidatura {
  id_candidatura: number;

  nome: string;
  email: string;

  curso: string;
  universidade: string;

  nascimento: string;
  sexo: string;

  bi: string;
  contacto: string;

  estado: EstadoCandidatura;

  data_submissao: string;

  departamento: string | null;

  documentos: DocumentoEstagiario[];
}

@Injectable({
  providedIn: 'root'
})
export class EstagiarioService {

  private readonly http = inject(HttpClient);

  private readonly apiUrl =
    `${environment.apiUrl}/estagiario`;

  minhaCandidatura(): Observable<MinhaCandidatura> {

    return this.http.get<MinhaCandidatura>(
      `${this.apiUrl}/minha-candidatura`
    );
  }

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
}