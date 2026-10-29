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
   ESTAGIÁRIOS POR DEPARTAMENTO (DASHBOARD EM ESCADA)
   ========================================================= */

export interface EstagiariosPorDepartamentoItem {
  id_departamento: number | null;
  nome: string;
  total_estagiarios: number;
  por_estado: Record<string, number>;
}


export interface EstagiariosPorDepartamento {
  total_estagiarios: number;
  estados: string[];
  totais_estado: Record<string, number>;
  departamentos: EstagiariosPorDepartamentoItem[];
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
  entrevista?: Entrevista | null;
}


/* =========================================================
   ENTREVISTA
   ========================================================= */

export type ResultadoEntrevista =
  | 'pendente'
  | 'aprovado'
  | 'reprovado';


export interface Entrevista {
  id_entrevista: number;
  id_candidatura: number;

  data_hora: string;
  resultado: ResultadoEntrevista;
  motivo_reprovacao?: string | null;

  administrador?: {
    id_utilizador: number;
    nome: string;
    email: string;
  } | null;

  supervisor_representante?: {
    id_utilizador: number;
    nome: string;
    email: string;
  } | null;
}


export interface DadosAgendamentoEntrevista {
  data_hora: string;
  id_supervisor_representante: number;
}


export interface RespostaEntrevista {
  message: string;
  entrevista: Entrevista;
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
  duracao_meses?: number | null;
  data_fim_prevista?: string | null;
  data_fim_real?: string | null;

  estado_estagio?: string | null;
  motivo_suspensao?: string | null;
  dia_limite_relatorio?: number | null;
}


export type TipoRelatorioAdmin = 'semanal' | 'mensal' | 'final';

export type EstadoRelatorioAdmin =
  | 'pendente'
  | 'aprovado'
  | 'rejeitado'
  | 'em_atraso';

export interface RelatorioAdmin {
  id_relatorio: number;
  id_estagio: number;
  tipo: TipoRelatorioAdmin;
  periodo_referencia: string;
  mes_referencia?: string | null;
  mes_referencia_legivel?: string | null;
  prazo_envio?: string | null;
  prazo_envio_legivel?: string | null;
  atividades: string;
  resultados: string;
  constrangimentos?: string | null;
  objetivos?: string | null;
  submetido_em: string;
  enviado_em?: string | null;
  data_envio_legivel?: string | null;
  entregue?: boolean;
  em_atraso?: boolean;
  estado_avaliacao: EstadoRelatorioAdmin;
  comentario_admin?: string | null;
  ficheiro_nome?: string | null;
  tem_ficheiro?: boolean;
  ficheiro_url?: string | null;
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
  origem?: 'supervisor' | 'administrador';
  nome_arquivo?: string | null;
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

  plano_atual_supervisor?: PlanoEstagioAdmin | null;
}


export interface TrimestreEstagio {
  ano: number;
  numero: number;
  inicio: string;
  fim: string;
  rotulo: string;
}


export interface EstagiosAdminResponse {
  data: EstagioAdmin[];
  total: number;
  current_page: number;
  last_page: number;
  per_page: number;
  trimestre?: TrimestreEstagio | null;
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
     ESTAGIÁRIOS POR DEPARTAMENTO
     ======================================================= */

  estagiariosPorDepartamento(): Observable<EstagiariosPorDepartamento> {
    return this.http.get<EstagiariosPorDepartamento>(
      `${this.apiUrl}/estagiarios-por-departamento`
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
    porPagina: number = 50
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
     ENTREVISTAS
     ======================================================= */

  agendarEntrevista(
    idCandidatura: number,
    dados: DadosAgendamentoEntrevista
  ): Observable<RespostaEntrevista> {

    return this.http.post<RespostaEntrevista>(
      `${this.apiUrl}/candidaturas/${idCandidatura}/entrevista`,
      dados
    );
  }


  reagendarEntrevista(
    idEntrevista: number,
    dados: DadosAgendamentoEntrevista
  ): Observable<RespostaEntrevista> {

    return this.http.put<RespostaEntrevista>(
      `${this.apiUrl}/entrevistas/${idEntrevista}`,
      dados
    );
  }


  registarResultadoEntrevista(
    idEntrevista: number,
    resultado: 'aprovado' | 'reprovado',
    motivoReprovacao?: string | null
  ): Observable<RespostaEntrevista> {

    return this.http.patch<RespostaEntrevista>(
      `${this.apiUrl}/entrevistas/${idEntrevista}/resultado`,
      {
        resultado,
        motivo_reprovacao: motivoReprovacao ?? null
      }
    );
  }


  /* =======================================================
     LISTAR ESTAGIÁRIOS
     ======================================================= */

  /* =======================================================
     RELATORIOS ADMINISTRATIVOS
     ======================================================= */

  listarRelatoriosAtraso(): Observable<{
    total: number;
    data: RelatorioAdmin[];
  }> {
    return this.http.get<{ total: number; data: RelatorioAdmin[] }>(
      `${this.apiUrl}/relatorios/atraso`
    );
  }

  visualizarFicheiroRelatorio(id: number): Observable<Blob> {
    return this.http.get(
      `${this.apiUrl}/relatorios/${id}/ficheiro`,
      { responseType: 'blob' }
    );
  }

  /* =======================================================
     PLANO DE ESTAGIO
     ======================================================= */

  obterPlanoEstagio(
    idEstagio: number
  ): Observable<{
    id_plano_estagio: number;
    nome_arquivo: string;
    formato_legivel: string;
    versao: number;
    data_upload_legivel?: string | null;
  } | null> {
    return this.http.get<{
      id_plano_estagio: number;
      nome_arquivo: string;
      formato_legivel: string;
      versao: number;
      data_upload_legivel?: string | null;
    } | null>(`${this.apiUrl}/estagios/${idEstagio}/plano`);
  }

  carregarPlanoEstagio(
    idEstagio: number,
    ficheiro: File
  ): Observable<{
    message: string;
    data: {
      id_plano_estagio: number;
      nome_arquivo: string;
      formato_legivel: string;
      versao: number;
      data_upload_legivel?: string | null;
    };
  }> {
    const formData = new FormData();

    formData.append('id_estagio', String(idEstagio));
    formData.append('ficheiro', ficheiro);

    return this.http.post<{
      message: string;
      data: {
        id_plano_estagio: number;
        nome_arquivo: string;
        formato_legivel: string;
        versao: number;
        data_upload_legivel?: string | null;
      };
    }>(`${this.apiUrl}/planos-estagio`, formData);
  }

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
    porPagina: number = 50
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
     LISTAR ESTÁGIOS (CANÓNICO)
     ======================================================= */

  listarEstagios(
    pesquisa: string = '',
    estado: string = '',
    trimestre: string = '',
    pagina: number = 1,
    porPagina: number = 50
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

    if (trimestre) {
      params = params.set('trimestre', trimestre);
    }

    return this.http.get<EstagiosAdminResponse>(
      `${this.apiUrl}/estagios`,
      { params }
    );
  }

  /* =======================================================
     ATUALIZAR ESTADO DO ESTÁGIO
     ======================================================= */

  atualizarEstadoEstagio(
    idCandidatura: number,
    dados: {
      estado: 'ativo' | 'suspenso' | 'concluido' | 'cancelado';
      data_inicio?: string;
      duracao_meses?: number;
      data_fim_prevista?: string;
      motivo_suspensao?: string;
      dia_limite_relatorio?: number;
    }
  ): Observable<{ message: string; data: unknown }> {
    return this.http.patch<{ message: string; data: unknown }>(
      `${this.apiUrl}/estagiarios/${idCandidatura}/estado`,
      dados
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
