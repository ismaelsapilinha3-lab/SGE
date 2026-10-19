
import { Injectable, inject } from '@angular/core';
import {
  HttpClient,
  HttpParams
} from '@angular/common/http';

import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';


/* =========================================================
   DASHBOARD
   ========================================================= */

export interface DashboardAdmin {
  total_candidaturas: number;
  em_analise: number;
  aprovadas: number;
  recusadas: number;
}


/* =========================================================
   DEPARTAMENTO
   ========================================================= */

export interface Departamento {
  id_departamento: number;
  nome: string;
  descricao?: string | null;
  ativo?: boolean;
  estagiarios_ativos?: number;
}


export interface DepartamentoDados {
  nome: string;
  descricao?: string | null;
  ativo?: boolean;
}


export interface RespostaDepartamento {
  mensagem: string;
  departamento: Departamento;
}


/* =========================================================
   DOCUMENTO
   ========================================================= */

export interface Documento {
  id_documento: number;
  id_candidatura: number;
  id_estagio?: number | null;
  tipo_documento: string;
  versao?: number;
  nome_arquivo: string;
  caminho_arquivo: string;
  data_upload: string;
  validade?: string | null;
  estado?: string | null;
  descricao?: string | null;
  created_at?: string;
  updated_at?: string;
}


/* =========================================================
   CANDIDATURA
   ========================================================= */

export interface Candidatura {
  id_candidatura: number;
  nome: string;
  email: string;
  curso: string;
  universidade: string;
  nascimento: string;
  sexo: string;
  bi: string;
  contacto: string;
  id_departamento_selecionado: number;

  estado:
    | 'em_analise'
    | 'aprovada'
    | 'recusada'
    | 'encerrada';

  motivo_recusa?: string | null;
  data_decisao?: string | null;
  id_coordenador?: number | null;
  encaminhada_em?: string | null;
  bi_ativo?: string | null;
  created_at: string;
  updated_at: string;
  departamento?: Departamento;
  documentos?: Documento[];
}


/* =========================================================
   RESPOSTA PAGINADA
   ========================================================= */

export interface CandidaturasResponse {
  current_page: number;
  data: Candidatura[];
  first_page_url: string;
  from: number | null;
  last_page: number;
  last_page_url: string;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  total: number;
}


/* =========================================================
   SERVIÇO ADMINISTRATIVO
   ========================================================= */

/* =======================================================
   ESTAGIARIOS
   ======================================================= */

export interface Estagiario {
  id_estagio: number | null;
  id_candidatura: number;
  id_estagiario: number | null;
  nome: string;
  email: string;
  telefone?: string | null;
  curso?: string | null;
  universidade?: string | null;
  departamento?: string | null;
  data_inicio?: string | null;
  duracao_meses?: number | null;
  data_fim_prevista?: string | null;
  data_fim_real?: string | null;
  estado_estagio?: string | null;
  motivo_suspensao?: string | null;
}

export type TipoRelatorioAdmin = 'semanal' | 'mensal' | 'final';
export type EstadoRelatorioAdmin = 'pendente' | 'aprovado' | 'rejeitado';

export interface RelatorioAdmin {
  id_relatorio: number;
  id_estagio: number;
  tipo: TipoRelatorioAdmin;
  periodo_referencia: string;
  atividades: string;
  resultados: string;
  constrangimentos?: string | null;
  objetivos?: string | null;
  submetido_em: string;
  estado_avaliacao: EstadoRelatorioAdmin;
  comentario_admin?: string | null;
  ficheiro_nome?: string | null;
  estagio?: {
    estagiario?: {
      id_utilizador: number;
      nome: string;
      email: string;
    } | null;
  } | null;
}

export interface RelatoriosAdminResponse {
  data: RelatorioAdmin[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}
export interface EstagiariosResponse {
  data: Estagiario[];
  total: number;
  current_page: number;
  last_page: number;
}
@Injectable({
  providedIn: 'root'
})
export class AdminService {

  private readonly http = inject(HttpClient);

  private readonly apiUrl =
    `${environment.apiUrl}/admin`;


  /* =======================================================
     DASHBOARD
     ======================================================= */

  dashboard(): Observable<DashboardAdmin> {
    return this.http.get<DashboardAdmin>(
      `${this.apiUrl}/dashboard`
    );
  }


  /* =======================================================
     DEPARTAMENTOS
     ======================================================= */

  listarDepartamentos(): Observable<Departamento[]> {
    return this.http.get<Departamento[]>(
      `${environment.apiUrl}/departamentos`
    );
  }


  /* =======================================================
     CRIAR DEPARTAMENTO
     ======================================================= */

  criarDepartamento(
    dados: DepartamentoDados
  ): Observable<RespostaDepartamento> {
    return this.http.post<RespostaDepartamento>(
      `${this.apiUrl}/departamentos`,
      {
        nome: dados.nome,
        descricao: dados.descricao ?? null
      }
    );
  }


  /* =======================================================
     EDITAR DEPARTAMENTO
     ======================================================= */

  atualizarDepartamento(
    id: number,
    dados: DepartamentoDados
  ): Observable<RespostaDepartamento> {
    return this.http.patch<RespostaDepartamento>(
      `${this.apiUrl}/departamentos/${id}`,
      dados
    );
  }


  /* =======================================================
     LISTAR CANDIDATURAS
     ======================================================= */

  listarCandidaturas(
    pesquisa?: string,
    estado?: string,
    idDepartamento?: number,
    pagina: number = 1,
    porPagina: number = 15
  ): Observable<CandidaturasResponse> {

    let params = new HttpParams()
      .set('page', pagina)
      .set('por_pagina', porPagina);

    if (pesquisa?.trim()) {
      params = params.set(
        'pesquisa',
        pesquisa.trim()
      );
    }

    if (estado) {
      params = params.set(
        'estado',
        estado
      );
    }

    if (idDepartamento) {
      params = params.set(
        'id_departamento',
        idDepartamento
      );
    }

    return this.http.get<CandidaturasResponse>(
      `${this.apiUrl}/candidaturas`,
      {
        params
      }
    );
  }


  /* =======================================================
     OBTER CANDIDATURA
     ======================================================= */

  obterCandidatura(
    id: number
  ): Observable<Candidatura> {
    return this.http.get<Candidatura>(
      `${this.apiUrl}/candidaturas/${id}`
    );
  }


  /* =======================================================
     APROVAR CANDIDATURA
     ======================================================= */

  aprovarCandidatura(
    id: number
  ): Observable<any> {
    return this.http.patch(
      `${this.apiUrl}/candidaturas/${id}/aprovar`,
      {}
    );
  }


  /* =======================================================
     RECUSAR CANDIDATURA
     ======================================================= */

  recusarCandidatura(
    id: number,
    motivo: string
  ): Observable<any> {
    return this.http.patch(
      `${this.apiUrl}/candidaturas/${id}/recusar`,
      {
        motivo
      }
    );
  }


  /* =======================================================
     VISUALIZAR DOCUMENTO
     ======================================================= */

  /* =======================================================
     RELATORIOS ADMINISTRATIVOS
     ======================================================= */

  listarRelatoriosAdmin(
    pesquisa: string = '',
    estado: string = '',
    tipo: string = '',
    pagina: number = 1
  ): Observable<RelatoriosAdminResponse> {
    let params = new HttpParams()
      .set('page', pagina);

    if (pesquisa.trim()) {
      params = params.set('pesquisa', pesquisa.trim());
    }

    if (estado) {
      params = params.set('estado', estado);
    }

    if (tipo) {
      params = params.set('tipo', tipo);
    }

    return this.http.get<RelatoriosAdminResponse>(
      `${this.apiUrl}/relatorios`,
      { params }
    );
  }

  avaliarRelatorioAdmin(
    id: number,
    estado: 'aprovado' | 'rejeitado',
    comentario: string = ''
  ): Observable<{
    message: string;
    data: {
      id_relatorio: number;
      estado_avaliacao: EstadoRelatorioAdmin;
      comentario_admin: string | null;
    };
  }> {
    return this.http.patch<{
      message: string;
      data: {
        id_relatorio: number;
        estado_avaliacao: EstadoRelatorioAdmin;
        comentario_admin: string | null;
      };
    }>(
      `${this.apiUrl}/relatorios/${id}/avaliacao`,
      {
        estado_avaliacao: estado,
        comentario_admin: comentario.trim() || null
      }
    );
  }

  listarEstagiarios(
    pesquisa: string = '',
    estado: string = '',
    pagina: number = 1,
    porPagina: number = 15
  ): Observable<EstagiariosResponse> {
    let params = new HttpParams()
      .set('page', pagina)
      .set('por_pagina', porPagina);

    if (pesquisa.trim()) {
      params = params.set('pesquisa', pesquisa.trim());
    }

    if (estado) {
      params = params.set('estado', estado);
    }

    return this.http.get<EstagiariosResponse>(
      `${this.apiUrl}/estagiarios`,
      { params }
    );
  }

  atualizarEstadoEstagio(
    idCandidatura: number,
    dados: {
      estado: 'ativo' | 'suspenso' | 'concluido' | 'cancelado';
      data_inicio?: string;
      duracao_meses?: number;
      data_fim_prevista?: string;
      motivo_suspensao?: string;
    }
  ): Observable<{ message: string; data: unknown }> {
    return this.http.patch<{ message: string; data: unknown }>(
      `${this.apiUrl}/estagiarios/${idCandidatura}/estado`,
      dados
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
