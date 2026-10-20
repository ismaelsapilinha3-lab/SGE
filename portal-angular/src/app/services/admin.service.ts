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

export type EstadoCandidatura =
  | 'em_analise'
  | 'aprovada'
  | 'recusada'
  | 'encerrada';


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

  estado: EstadoCandidatura;

  motivo_recusa?: string | null;
  data_decisao?: string | null;

  id_coordenador?: number | null;
  encaminhada_em?: string | null;

  bi_ativo?: boolean | null;

  created_at: string;
  updated_at: string;

  departamento?: Departamento;
  documentos?: Documento[];
}


/* =========================================================
   ENCAMINHAMENTO
   ========================================================= */

export interface DadosEncaminhamento {
  id_departamento: number;
}


export interface RespostaEncaminhamento {
  message: string;
  candidatura: Candidatura;
  coordenador?: {
    id_utilizador: number;
    nome: string;
    email: string;
    id_departamento: number;
  };
}


/* =========================================================
   RESPOSTAS PAGINADAS
   ========================================================= */

export interface CandidaturasResponse {
  current_page: number;
  data: Candidatura[];

  first_page_url: string;
  from: number | null;

  last_page: number;
  last_page_url: string;

  next_page_url: string | null;
  prev_page_url: string | null;

  path: string;
  per_page: number;
  to?: number | null;
  total: number;
}


/* =========================================================
   ESTAGIÁRIOS
   ========================================================= */

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
  data_fim_prevista?: string | null;
  data_fim_real?: string | null;

  estado_estagio?: string | null;
  motivo_suspensao?: string | null;
}


export interface EstagiariosResponse {
  data: Estagiario[];
  total: number;
  current_page: number;
  last_page: number;
}


/* =========================================================
   ESTÁGIOS (GESTÃO ADMINISTRATIVA)
   ========================================================= */

export interface SupervisorEstagio {
  id_estagio_supervisor?: number;
  id_supervisor: number;
  atribuido_em?: string | null;
  supervisor?: {
    id_utilizador: number;
    nome: string;
    email: string;
  } | null;
}


export interface PlanoEstagioAdmin {
  id_plano_estagio: number;
  objetivos: string;
  atividades_previstas: string;
  cronograma: string;
  data_inicio: string;
  data_fim: string;
  estado: string;
  motivo_recusa: string | null;
  data_decisao: string | null;
}


export interface EstagioAdmin {
  id_estagio: number;
  id_candidatura: number;
  id_estagiario: number | null;

  data_inicio: string | null;
  data_fim_prevista: string | null;
  data_fim_real: string | null;

  estado: 'ativo' | 'suspenso' | 'concluido' | 'cancelado';
  motivo_suspensao: string | null;

  estagiario?: {
    id_utilizador: number;
    nome: string;
    email: string;
  } | null;

  candidatura?: {
    id_candidatura: number;
    nome: string;
    email: string;
    curso?: string | null;
    universidade?: string | null;
    telefone?: string | null;
    departamento?: {
      id_departamento: number;
      nome: string;
    } | null;
  } | null;

  supervisores_ativos?: SupervisorEstagio[];

  plano_aprovado?: PlanoEstagioAdmin | null;
}


export interface EstagiosAdminResponse {
  data: EstagioAdmin[];
  total: number;
  current_page: number;
  last_page: number;
  per_page: number;
}


/* =========================================================
   SERVIÇO ADMINISTRATIVO
   ========================================================= */

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
      `${this.apiUrl}/departamentos`
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
      { params }
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
     ENCAMINHAR CANDIDATURA
     ======================================================= */

  encaminharCandidatura(
    id: number,
    dados: DadosEncaminhamento
  ): Observable<RespostaEncaminhamento> {

    return this.http.patch<RespostaEncaminhamento>(
      `${this.apiUrl}/candidaturas/${id}/encaminhar`,
      dados
    );
  }


  /* =======================================================
     LISTAR ESTAGIÁRIOS
     ======================================================= */

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

    return this.http.get<EstagiariosResponse>(
      `${this.apiUrl}/estagiarios`,
      { params }
    );
  }


  /* =======================================================
     ATUALIZAR ESTADO DO ESTÁGIO
     ======================================================= */

  atualizarEstadoEstagio(
    idCandidatura: number,
    dados: {
      estado:
        | 'ativo'
        | 'suspenso'
        | 'concluido'
        | 'cancelado';

      data_inicio?: string;
      data_fim_prevista?: string;
      motivo_suspensao?: string;
    }
  ): Observable<{
    message: string;
    data: unknown;
  }> {

    return this.http.patch<{
      message: string;
      data: unknown;
    }>(
      `${this.apiUrl}/estagiarios/${idCandidatura}/estado`,
      dados
    );
  }


  /* =======================================================
     LISTAR ESTÁGIOS (CANÓNICO)
     ======================================================= */

  listarEstagios(
    pesquisa: string = '',
    estado: string = '',
    pagina: number = 1,
    porPagina: number = 15
  ): Observable<EstagiosAdminResponse> {

    let params = new HttpParams()
      .set('page', pagina)
      .set('por_pagina', porPagina);

    if (pesquisa.trim()) {
      params = params.set('pesquisa', pesquisa.trim());
    }

    if (estado) {
      params = params.set('estado', estado);
    }

    return this.http.get<EstagiosAdminResponse>(
      `${this.apiUrl}/estagios`,
      { params }
    );
  }


  /* =======================================================
     CONSULTAR UM ESTÁGIO
     ======================================================= */

  obterEstagio(id: number): Observable<EstagioAdmin> {
    return this.http.get<EstagioAdmin>(
      `${this.apiUrl}/estagios/${id}`
    );
  }


  /* =======================================================
     VISUALIZAR DOCUMENTO
     ======================================================= */

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
