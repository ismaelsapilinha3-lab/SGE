import { Injectable, inject } from '@angular/core';

import { HttpClient, HttpParams } from '@angular/common/http';

import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';


/* =========================================================
   CANDIDATURA (VISTA PELO COORDENADOR)
   ========================================================= */

export type ResultadoEntrevistaCoordenador =
  | 'pendente'
  | 'aprovado'
  | 'reprovado';


export interface EntrevistaCoordenador {

  id_entrevista: number;

  id_candidatura: number;

  data_hora: string;

  resultado: ResultadoEntrevistaCoordenador;

  motivo_reprovacao?: string | null;

  supervisor_representante?: {

    id_utilizador: number;

    nome: string;

    email: string;

  } | null;

}


export interface CandidaturaCoordenador {

  id_candidatura: number;

  id_utilizador?: number | null;

  nome: string;

  email: string;

  telefone?: string | null;

  curso?: string | null;

  universidade?: string | null;

  mensagem?: string | null;

  estado: string;

  motivo_recusa?: string | null;

  data_decisao?: string | null;

  id_coordenador?: number | null;

  id_departamento_selecionado?: number | null;

  created_at?: string;

  updated_at?: string;

  departamento?: {

    id_departamento: number;

    nome: string;

  } | null;

  entrevista?: EntrevistaCoordenador | null;

  documentos?: {

    id_documento: number;

    tipo_documento: string;

    nome_arquivo: string;

    caminho_arquivo?: string;

    versao?: number;

    estado?: string;

    descricao?: string | null;

  }[];

}


/* =========================================================
   DASHBOARD DO COORDENADOR
   ========================================================= */

export interface DashboardCoordenador {

  total_candidaturas: number;

  em_analise: number;

  aprovadas: number;

  recusadas: number;

}


/* =========================================================
   RESPOSTA PAGINADA DO LARAVEL
   ========================================================= */

export interface PaginaResposta<T> {

  data: T[];

  current_page: number;

  last_page: number;

  per_page: number;

  total: number;

}


@Injectable({
  providedIn: 'root'
})
export class CoordenadorService {

  private readonly http = inject(HttpClient);

  private readonly apiUrl =
    `${environment.apiUrl}/coordenador`;


  /* =======================================================
     DASHBOARD
     ======================================================= */

  dashboard(): Observable<DashboardCoordenador> {

    return this.http.get<DashboardCoordenador>(
      `${this.apiUrl}/dashboard`
    );

  }


  /* =======================================================
     CANDIDATURAS
     ======================================================= */

  candidaturas(
    pesquisa: string = '',
    estado: string = '',
    pagina: number = 1,
    porPagina: number = 50
  ): Observable<PaginaResposta<CandidaturaCoordenador>> {

    let parametros = new HttpParams()
      .set('page', pagina)
      .set('por_pagina', porPagina);

    if (pesquisa.trim()) {

      parametros = parametros.set(
        'pesquisa',
        pesquisa.trim()
      );

    }

    if (estado) {

      parametros = parametros.set('estado', estado);

    }

    return this.http.get<PaginaResposta<CandidaturaCoordenador>>(
      `${this.apiUrl}/candidaturas`,
      {
        params: parametros
      }
    );

  }


  obterCandidatura(
    id: number
  ): Observable<CandidaturaCoordenador> {

    return this.http.get<CandidaturaCoordenador>(
      `${this.apiUrl}/candidaturas/${id}`
    );

  }


  /* =======================================================
     DOCUMENTOS DA CANDIDATURA
     ======================================================= */

  /**
   * Devolve o conteúdo do documento como blob, para pré-visualização
   * no próprio painel (imagens e PDF).
   */
  visualizarDocumento(idDocumento: number): Observable<Blob> {

    return this.http.get(
      `${environment.apiUrl}/documentos/${idDocumento}`,
      {
        responseType: 'blob'
      }
    );

  }


  /* =======================================================
     DECISÃO FINAL
     ======================================================= */

  aprovarCandidatura(
    id: number
  ): Observable<{
    message: string;
    candidatura: CandidaturaCoordenador;
  }> {

    return this.http.patch<{
      message: string;
      candidatura: CandidaturaCoordenador;
    }>(
      `${this.apiUrl}/candidaturas/${id}/aprovar`,
      {}
    );

  }


  recusarCandidatura(
    id: number,
    motivo: string
  ): Observable<{
    message: string;
    candidatura: CandidaturaCoordenador;
  }> {

    return this.http.patch<{
      message: string;
      candidatura: CandidaturaCoordenador;
    }>(
      `${this.apiUrl}/candidaturas/${id}/recusar`,
      { motivo }
    );

  }

}
