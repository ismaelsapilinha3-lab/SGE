import { Injectable, inject } from '@angular/core';

import { HttpClient, HttpParams } from '@angular/common/http';

import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

/*
 * Os recursos do estágio (assiduidade, atividades, avaliações,
 * relatórios, feedback e documentos) têm a mesma forma para todos os
 * papéis. Reaproveitam-se, por isso, os tipos já definidos no serviço
 * do estagiário.
 */
import {
  Assiduidade,
  Atividade,
  Avaliacao,
  DocumentoContrato,
  Feedback,
  Relatorio,
  RespostaMensagem
} from './estagio-estagiario.service';


/* =========================================================
   ESTÁGIO SUPERVISIONADO
   ========================================================= */

export interface PlanoEstagio {

  id_plano_estagio: number;

  id_estagio: number;

  id_supervisor: number | null;

  objetivos: string;

  atividades_previstas: string;

  cronograma: string;

  data_inicio: string;

  data_fim: string;

  estado: 'submetido' | 'aprovado' | 'recusado' | 'substituido';

  motivo_recusa: string | null;

  data_decisao: string | null;

}


export interface PlanoHistorico {

  id_plano_historico: number;

  objetivos: string;

  atividades_previstas: string;

  cronograma: string;

  data_inicio: string;

  data_fim: string;

  estado: string;

  motivo_recusa: string | null;

  alterado_em: string | null;

}


export interface EstagiarioResumo {

  id_utilizador: number;

  nome: string;

  email: string;

}


export interface DepartamentoResumo {

  id_departamento: number;

  nome: string;

}


export interface CandidaturaResumo {

  id_candidatura: number;

  nome: string;

  email: string;

  departamento: DepartamentoResumo | null;

}


export interface EstagioSupervisionado {

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

  planos?: PlanoEstagio[];

}


/* =========================================================
   PEDIDOS
   ========================================================= */

export interface DadosPlano {

  objetivos: string;

  atividades_previstas: string;

  cronograma: string;

  data_inicio: string;

  data_fim: string;

}


export interface DadosAssiduidade {

  data: string;

  estado: string;

  hora_entrada?: string | null;

  hora_saida?: string | null;

  justificacao?: string | null;

}


export interface DadosAvaliacao {

  tipo: string;

  periodo_referencia: string;

  nota: number;

  nota_atividades?: number | null;

  nota_assiduidade?: number | null;

  nota_pontualidade?: number | null;

  comentarios?: string | null;

  pontos_fortes?: string | null;

  areas_melhoria?: string | null;

}


export interface FiltrosAssiduidade {

  mes?: string | null;

  estado?: string | null;

  justificacao_estado?: string | null;

}


/* =========================================================
   RESUMOS
   ========================================================= */

export interface ResumoAssiduidade {

  presente: number;

  atraso: number;

  falta: number;

  falta_justificada: number;

  justificacoes_pendentes: number;

  total: number;

}


export interface ResumoAvaliacoes {

  semanal: number;

  mensal: number;

  final: number;

  media_nota: number | null;

  total: number;

}


@Injectable({
  providedIn: 'root'
})
export class EstagioSupervisorService {

  private readonly http = inject(HttpClient);

  private readonly apiUrl =
    `${environment.apiUrl}/supervisor`;


  /* =======================================================
     ESTÁGIOS ATRIBUÍDOS
     ======================================================= */

  estagios(): Observable<{
    estagios: EstagioSupervisionado[];
  }> {

    return this.http.get<{
      estagios: EstagioSupervisionado[];
    }>(
      `${this.apiUrl}/estagios`
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


  submeterPlano(
    idEstagio: number,
    dados: DadosPlano
  ): Observable<{
    message: string;
    plano: PlanoEstagio;
  }> {

    return this.http.put<{
      message: string;
      plano: PlanoEstagio;
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/plano`,
      dados
    );

  }


  /* =======================================================
     DOCUMENTOS (CONTRATO)
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


  validarDocumento(
    idEstagio: number,
    idDocumento: number
  ): Observable<{
    message: string;
    documento: DocumentoContrato;
  }> {

    return this.http.patch<{
      message: string;
      documento: DocumentoContrato;
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/documentos/${idDocumento}/validar`,
      {}
    );

  }


  rejeitarDocumento(
    idEstagio: number,
    idDocumento: number,
    descricao: string
  ): Observable<{
    message: string;
    documento: DocumentoContrato;
  }> {

    return this.http.patch<{
      message: string;
      documento: DocumentoContrato;
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/documentos/${idDocumento}/rejeitar`,
      {
        descricao
      }
    );

  }


  descarregarDocumento(
    idDocumento: number
  ): Observable<Blob> {

    return this.http.get(
      `${environment.apiUrl}/documentos-estagio/${idDocumento}`,
      {
        responseType: 'blob'
      }
    );

  }


  /* =======================================================
     RELATÓRIOS
     ======================================================= */

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


  /* =======================================================
     ASSIDUIDADE
     ======================================================= */

  assiduidades(
    idEstagio: number,
    filtros: FiltrosAssiduidade = {}
  ): Observable<{
    id_estagio: number;
    assiduidades: Assiduidade[];
    resumo: ResumoAssiduidade;
  }> {

    let parametros = new HttpParams();

    if (filtros.mes) {

      parametros = parametros.set('mes', filtros.mes);

    }

    if (filtros.estado) {

      parametros = parametros.set('estado', filtros.estado);

    }

    if (filtros.justificacao_estado) {

      parametros = parametros.set(
        'justificacao_estado',
        filtros.justificacao_estado
      );

    }

    return this.http.get<{
      id_estagio: number;
      assiduidades: Assiduidade[];
      resumo: ResumoAssiduidade;
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/assiduidades`,
      {
        params: parametros
      }
    );

  }


  registarAssiduidade(
    idEstagio: number,
    dados: DadosAssiduidade
  ): Observable<{
    message: string;
    assiduidade: Assiduidade;
  }> {

    return this.http.post<{
      message: string;
      assiduidade: Assiduidade;
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/assiduidades`,
      dados
    );

  }


  removerAssiduidade(
    idEstagio: number,
    idAssiduidade: number
  ): Observable<RespostaMensagem> {

    return this.http.delete<RespostaMensagem>(
      `${this.apiUrl}/estagios/${idEstagio}/assiduidades/${idAssiduidade}`
    );

  }


  aprovarJustificacao(
    idEstagio: number,
    idAssiduidade: number
  ): Observable<{
    message: string;
    assiduidade: Assiduidade;
  }> {

    return this.http.patch<{
      message: string;
      assiduidade: Assiduidade;
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/assiduidades/${idAssiduidade}/justificacao/aprovar`,
      {}
    );

  }


  recusarJustificacao(
    idEstagio: number,
    idAssiduidade: number,
    motivo: string
  ): Observable<{
    message: string;
    assiduidade: Assiduidade;
  }> {

    return this.http.patch<{
      message: string;
      assiduidade: Assiduidade;
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/assiduidades/${idAssiduidade}/justificacao/recusar`,
      {
        motivo
      }
    );

  }


  /* =======================================================
     AVALIAÇÕES
     ======================================================= */

  avaliacoes(
    idEstagio: number,
    tipo: string | null = null
  ): Observable<{
    id_estagio: number;
    avaliacoes: Avaliacao[];
    resumo: ResumoAvaliacoes;
  }> {

    let parametros = new HttpParams();

    if (tipo) {

      parametros = parametros.set('tipo', tipo);

    }

    return this.http.get<{
      id_estagio: number;
      avaliacoes: Avaliacao[];
      resumo: ResumoAvaliacoes;
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/avaliacoes`,
      {
        params: parametros
      }
    );

  }


  registarAvaliacao(
    idEstagio: number,
    dados: DadosAvaliacao
  ): Observable<{
    message: string;
    avaliacao: Avaliacao;
  }> {

    return this.http.post<{
      message: string;
      avaliacao: Avaliacao;
    }>(
      `${this.apiUrl}/estagios/${idEstagio}/avaliacoes`,
      dados
    );

  }


  removerAvaliacao(
    idEstagio: number,
    idAvaliacao: number
  ): Observable<RespostaMensagem> {

    return this.http.delete<RespostaMensagem>(
      `${this.apiUrl}/estagios/${idEstagio}/avaliacoes/${idAvaliacao}`
    );

  }


  /* =======================================================
     FEEDBACK E ATIVIDADES
     ======================================================= */

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

}
