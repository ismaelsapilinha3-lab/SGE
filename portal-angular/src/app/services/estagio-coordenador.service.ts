import { Injectable, inject } from '@angular/core';

import { HttpClient, HttpParams } from '@angular/common/http';

import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

/*
 * Os recursos do estágio (assiduidade, atividades, avaliações,
 * relatórios, feedback e documentos) têm a mesma forma para todos os
 * papéis. Reaproveitam-se, por isso, os tipos já definidos nos
 * serviços do estagiário e do supervisor.
 */
import {
  Assiduidade,
  Atividade,
  Avaliacao,
  DocumentoContrato,
  Feedback,
  Relatorio
} from './estagio-estagiario.service';

import {
  CandidaturaResumo,
  EstagiarioResumo,
  PlanoEstagio,
  PlanoHistorico
} from './estagio-supervisor.service';


/* =========================================================
   VÍNCULO COM O SUPERVISOR
   ========================================================= */

export interface SupervisorVinculo {

  id_estagio_supervisor?: number;

  id_supervisor: number;

  id_coordenador?: number | null;

  atribuido_em?: string | null;

  removido_em?: string | null;

  supervisor?: EstagiarioResumo | null;

}


/* =========================================================
   ESTÁGIO VISTO PELA COORDENAÇÃO
   ========================================================= */

export interface EstagioCoordenado {

  id_estagio: number;

  id_candidatura: number | null;

  id_estagiario: number | null;

  data_inicio: string | null;

  data_fim_prevista: string | null;

  data_fim_real: string | null;

  estado: string | null;

  motivo_suspensao: string | null;

  estagiario?: EstagiarioResumo | null;

  candidatura?: CandidaturaResumo | null;

  supervisores_ativos?: SupervisorVinculo[];

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


/* =========================================================
   RESUMO LOCAL DA ASSIDUIDADE
   ========================================================= */

export interface ResumoAssiduidade {

  presente: number;

  atraso: number;

  falta: number;

  falta_justificada: number;

  justificacoes_pendentes: number;

  total: number;

}


@Injectable({
  providedIn: 'root'
})
export class EstagioCoordenadorService {

  private readonly http = inject(HttpClient);

  private readonly apiUrl =
    `${environment.apiUrl}/coordenador`;


  /* =======================================================
     ESTÁGIOS DA DIREÇÃO
     ======================================================= */

  estagios(
    pesquisa: string = '',
    estado: string = '',
    pagina: number = 1,
    porPagina: number = 50
  ): Observable<PaginaResposta<EstagioCoordenado>> {

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

    return this.http.get<PaginaResposta<EstagioCoordenado>>(
      `${this.apiUrl}/estagios`,
      {
        params: parametros
      }
    );

  }


  /* =======================================================
     SUPERVISORES DO ESTÁGIO
     ======================================================= */

  supervisores(idEstagio: number): Observable<{
    id_estagio: number;
    supervisores: SupervisorVinculo[];
  }> {

    return this.http.get<{
      id_estagio: number;
      supervisores: SupervisorVinculo[];
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/supervisores`
    );

  }


  atribuirSupervisor(
    idEstagio: number,
    idSupervisor: number
  ): Observable<{
    message: string;
    supervisor: SupervisorVinculo;
  }> {

    return this.http.post<{
      message: string;
      supervisor: SupervisorVinculo;
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/supervisores`,
      {
        id_supervisor: idSupervisor
      }
    );

  }


  removerSupervisor(
    idEstagio: number,
    idSupervisor: number
  ): Observable<{
    message: string;
    supervisor: SupervisorVinculo;
  }> {

    return this.http.delete<{
      message: string;
      supervisor: SupervisorVinculo;
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/supervisores/${idSupervisor}`
    );

  }


  /* =======================================================
     PLANO DE ESTÁGIO
     ======================================================= */

  plano(idEstagio: number): Observable<{
    id_estagio: number;
    plano: PlanoEstagio | null;
    historico: PlanoHistorico[];
  }> {

    return this.http.get<{
      id_estagio: number;
      plano: PlanoEstagio | null;
      historico: PlanoHistorico[];
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/plano`
    );

  }


  aprovarPlano(
    idEstagio: number
  ): Observable<{
    message: string;
    plano: PlanoEstagio;
  }> {

    return this.http.patch<{
      message: string;
      plano: PlanoEstagio;
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/plano/aprovar`,
      {}
    );

  }


  recusarPlano(
    idEstagio: number,
    motivo: string
  ): Observable<{
    message: string;
    plano: PlanoEstagio;
  }> {

    return this.http.patch<{
      message: string;
      plano: PlanoEstagio;
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/plano/recusar`,
      {
        motivo
      }
    );

  }


  /* =======================================================
     DOCUMENTOS, RELATÓRIOS E ATIVIDADES
     ======================================================= */

  documentos(idEstagio: number): Observable<{
    id_estagio: number;
    documentos: DocumentoContrato[];
  }> {

    return this.http.get<{
      id_estagio: number;
      documentos: DocumentoContrato[];
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/documentos`
    );

  }


  descarregarDocumento(idDocumento: number): Observable<Blob> {

    return this.http.get(
      `${environment.apiUrl}/documentos-estagio/${idDocumento}`,
      {
        responseType: 'blob'
      }
    );

  }


  relatorios(idEstagio: number): Observable<{
    id_estagio: number;
    relatorios: Relatorio[];
  }> {

    return this.http.get<{
      id_estagio: number;
      relatorios: Relatorio[];
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/relatorios`
    );

  }


  atividades(idEstagio: number): Observable<{
    id_estagio: number;
    atividades: Atividade[];
  }> {

    return this.http.get<{
      id_estagio: number;
      atividades: Atividade[];
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/atividades`
    );

  }


  /* =======================================================
     ASSIDUIDADE
     ======================================================= */

  assiduidades(idEstagio: number): Observable<{
    id_estagio: number;
    assiduidades: Assiduidade[];
  }> {

    return this.http.get<{
      id_estagio: number;
      assiduidades: Assiduidade[];
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/assiduidades`
    );

  }


  /* =======================================================
     AVALIAÇÕES E FEEDBACK
     ======================================================= */

  avaliacoes(idEstagio: number): Observable<{
    id_estagio: number;
    avaliacoes: Avaliacao[];
  }> {

    return this.http.get<{
      id_estagio: number;
      avaliacoes: Avaliacao[];
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/avaliacoes`
    );

  }


  feedback(idEstagio: number): Observable<{
    id_estagio: number;
    feedback: Feedback | null;
  }> {

    return this.http.get<{
      id_estagio: number;
      feedback: Feedback | null;
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/feedback`
    );

  }

}
