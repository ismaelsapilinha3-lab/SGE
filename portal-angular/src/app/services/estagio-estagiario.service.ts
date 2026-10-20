import { Injectable, inject } from '@angular/core';

import { HttpClient } from '@angular/common/http';

import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';


/* =========================================================
   ESTÁGIO
   ========================================================= */

export interface Estagio {

  id_estagio: number;

  id_candidatura: number | null;

  id_estagiario: number | null;

  data_inicio: string | null;

  data_fim_prevista: string | null;

  data_fim_real: string | null;

  estado: string | null;

  motivo_suspensao: string | null;

  /** Contrato e restantes documentos do estágio (via GET /estagiario/estagio). */
  documentos?: DocumentoContrato[];

}


/* =========================================================
   CONTRATO E DOCUMENTOS DE ESTÁGIO
   ========================================================= */

export interface DocumentoContrato {

  id_documento_estagio: number;

  id_estagio: number;

  id_estagiario: number | null;

  tipo_documento: string;

  versao: number;

  nome_arquivo: string;

  caminho_arquivo: string;

  data_upload: string | null;

  validade: string | null;

  estado: string;

  descricao: string | null;

}


/* =========================================================
   RELATÓRIOS
   ========================================================= */

export interface Relatorio {

  id_relatorio: number;

  id_estagio: number;

  tipo: 'semanal' | 'mensal' | 'final';

  periodo_referencia: string;

  atividades: string;

  resultados: string;

  constrangimentos: string | null;

  objetivos: string | null;

  submetido_em: string | null;

}


export interface DadosRelatorio {

  tipo: string;

  periodo_referencia: string;

  atividades: string;

  resultados: string;

  constrangimentos?: string | null;

  objetivos?: string | null;

}


/* =========================================================
   ASSIDUIDADE
   ========================================================= */

export interface Assiduidade {

  id_assiduidade: number;

  id_estagio: number;

  data: string;

  estado: 'presente' | 'atraso' | 'falta' | 'falta_justificada';

  hora_entrada: string | null;

  hora_saida: string | null;

  justificacao: string | null;

  justificacao_estado: 'pendente' | 'aprovada' | 'recusada' | null;

  motivo_recusa: string | null;

  justificacao_submetida_em: string | null;

  justificacao_decidida_em: string | null;

}


/* =========================================================
   ATIVIDADES
   ========================================================= */

export interface Atividade {

  id_atividade: number;

  id_estagio: number;

  data: string;

  titulo: string;

  descricao: string;

}


export interface DadosAtividade {

  data: string;

  titulo: string;

  descricao: string;

}


/* =========================================================
   AVALIAÇÕES
   ========================================================= */

export interface Avaliacao {

  id_avaliacao: number;

  id_estagio: number;

  id_supervisor: number | null;

  tipo: 'semanal' | 'mensal' | 'final';

  periodo_referencia: string;

  nota_atividades: string | null;

  nota_assiduidade: string | null;

  nota_pontualidade: string | null;

  nota: string | null;

  comentarios: string | null;

  pontos_fortes: string | null;

  areas_melhoria: string | null;

}


/* =========================================================
   FEEDBACK
   ========================================================= */

export interface Feedback {

  id_feedback: number;

  id_estagio: number;

  nota_geral: number;

  comentario: string | null;

  sugestoes: string | null;

}


export interface DadosFeedback {

  nota_geral: number;

  comentario?: string | null;

  sugestoes?: string | null;

}


/* =========================================================
   RESPOSTAS GENÉRICAS
   ========================================================= */

export interface RespostaMensagem {

  message: string;

}


@Injectable({
  providedIn: 'root'
})
export class EstagioEstagiarioService {

  private readonly http = inject(HttpClient);

  private readonly apiUrl =
    `${environment.apiUrl}/estagiario/estagio`;


  /* =======================================================
     ESTÁGIO
     ======================================================= */

  obter(): Observable<{ estagio: Estagio }> {

    return this.http.get<{ estagio: Estagio }>(
      this.apiUrl
    );

  }


  /* =======================================================
     CONTRATO
     ======================================================= */

  submeterContrato(
    ficheiro: File
  ): Observable<{
    message: string;
    documento: DocumentoContrato;
  }> {

    const dados = new FormData();

    dados.append('ficheiro', ficheiro);

    return this.http.post<{
      message: string;
      documento: DocumentoContrato;
    }>(
      `${this.apiUrl}/contrato`,
      dados
    );

  }


  descarregarContrato(
    idDocumentoEstagio: number
  ): Observable<Blob> {

    return this.http.get(
      `${environment.apiUrl}/documentos-estagio/${idDocumentoEstagio}`,
      {
        responseType: 'blob'
      }
    );

  }


  /* =======================================================
     RELATÓRIOS
     ======================================================= */

  relatorios(): Observable<{ relatorios: Relatorio[] }> {

    return this.http.get<{ relatorios: Relatorio[] }>(
      `${this.apiUrl}/relatorios`
    );

  }


  criarRelatorio(
    dados: DadosRelatorio
  ): Observable<{
    message: string;
    relatorio: Relatorio;
  }> {

    return this.http.post<{
      message: string;
      relatorio: Relatorio;
    }>(
      `${this.apiUrl}/relatorios`,
      dados
    );

  }


  atualizarRelatorio(
    id: number,
    dados: DadosRelatorio
  ): Observable<{
    message: string;
    relatorio: Relatorio;
  }> {

    return this.http.put<{
      message: string;
      relatorio: Relatorio;
    }>(
      `${this.apiUrl}/relatorios/${id}`,
      dados
    );

  }


  submeterRelatorio(
    id: number
  ): Observable<{
    message: string;
    relatorio: Relatorio;
  }> {

    return this.http.post<{
      message: string;
      relatorio: Relatorio;
    }>(
      `${this.apiUrl}/relatorios/${id}/submeter`,
      {}
    );

  }


  /* =======================================================
     ASSIDUIDADE
     ======================================================= */

  assiduidades(): Observable<{
    assiduidades: Assiduidade[];
  }> {

    return this.http.get<{
      assiduidades: Assiduidade[];
    }>(
      `${this.apiUrl}/assiduidades`
    );

  }


  justificarFalta(
    id: number,
    justificacao: string
  ): Observable<{
    message: string;
    assiduidade: Assiduidade;
  }> {

    return this.http.post<{
      message: string;
      assiduidade: Assiduidade;
    }>(
      `${this.apiUrl}/assiduidades/${id}/justificar`,
      {
        justificacao
      }
    );

  }


  /* =======================================================
     ATIVIDADES
     ======================================================= */

  atividades(): Observable<{ atividades: Atividade[] }> {

    return this.http.get<{ atividades: Atividade[] }>(
      `${this.apiUrl}/atividades`
    );

  }


  criarAtividade(
    dados: DadosAtividade
  ): Observable<{
    message: string;
    atividade: Atividade;
  }> {

    return this.http.post<{
      message: string;
      atividade: Atividade;
    }>(
      `${this.apiUrl}/atividades`,
      dados
    );

  }


  atualizarAtividade(
    id: number,
    dados: DadosAtividade
  ): Observable<{
    message: string;
    atividade: Atividade;
  }> {

    return this.http.put<{
      message: string;
      atividade: Atividade;
    }>(
      `${this.apiUrl}/atividades/${id}`,
      dados
    );

  }


  removerAtividade(
    id: number
  ): Observable<RespostaMensagem> {

    return this.http.delete<RespostaMensagem>(
      `${this.apiUrl}/atividades/${id}`
    );

  }


  /* =======================================================
     AVALIAÇÕES
     ======================================================= */

  avaliacoes(): Observable<{ avaliacoes: Avaliacao[] }> {

    return this.http.get<{ avaliacoes: Avaliacao[] }>(
      `${this.apiUrl}/avaliacoes`
    );

  }


  /* =======================================================
     FEEDBACK
     ======================================================= */

  feedback(): Observable<{ feedback: Feedback | null }> {

    return this.http.get<{ feedback: Feedback | null }>(
      `${this.apiUrl}/feedback`
    );

  }


  guardarFeedback(
    dados: DadosFeedback
  ): Observable<{
    message: string;
    feedback: Feedback;
  }> {

    return this.http.post<{
      message: string;
      feedback: Feedback;
    }>(
      `${this.apiUrl}/feedback`,
      dados
    );

  }

}
