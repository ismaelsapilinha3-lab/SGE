import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';


export type TipoUtilizador =
  | 'administrador'
  | 'coordenador'
  | 'supervisor'
  | 'estagiario';


export interface UtilizadorAdmin {
  id_utilizador: number;
  nome: string;
  email: string;
  telefone: string | null;
  foto?: string | null;
  foto_url?: string | null;
  tipo_utilizador: TipoUtilizador;
  ativo: boolean;
  id_departamento?: number | null;
  departamento?: string | null;
  created_at?: string;
}


export interface UtilizadoresPaginados {
  data: UtilizadorAdmin[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}


export interface DepartamentoAdmin {
  id_departamento: number;
  nome: string;
  descricao?: string | null;
  ativo?: boolean;
}


export interface CriarUtilizadorAdmin {
  nome: string;
  email: string;
  password: string;
  password_confirmation: string;
  telefone?: string;
  tipo_utilizador: 'coordenador' | 'supervisor' | 'estagiario';
  id_departamento?: number | null;
}


export interface ActualizarUtilizadorAdmin {
  nome?: string;
  email?: string;
  telefone?: string | null;
  tipo_utilizador?: 'coordenador' | 'supervisor' | 'estagiario';
  id_departamento?: number | null;
  password?: string;
  password_confirmation?: string;
}


export interface RespostaUtilizador {
  message: string;
  utilizador: UtilizadorAdmin;
}


@Injectable({
  providedIn: 'root'
})
export class UtilizadorAdminService {

  private readonly http = inject(HttpClient);

  private readonly api =
    `${environment.apiUrl}/admin/utilizadores`;


  // ============================================================
  // LISTAR UTILIZADORES
  // ============================================================

  listar(
    pesquisa = '',
    tipoUtilizador = '',
    ativo: boolean | null = null,
    pagina = 1,
    porPagina = 15
  ): Observable<UtilizadoresPaginados> {

    let params = new HttpParams()
      .set('page', pagina)
      .set('per_page', porPagina);

    if (pesquisa.trim()) {
      params = params.set('pesquisa', pesquisa.trim());
    }

    if (tipoUtilizador) {
      params = params.set('tipo_utilizador', tipoUtilizador);
    }

    if (ativo !== null) {
      params = params.set('ativo', ativo ? '1' : '0');
    }

    return this.http.get<UtilizadoresPaginados>(
      this.api,
      { params }
    );
  }


  // ============================================================
  // OBTER UM UTILIZADOR
  // ============================================================

  obter(id: number): Observable<{
    utilizador: UtilizadorAdmin;
    perfil: unknown;
  }> {

    return this.http.get<{
      utilizador: UtilizadorAdmin;
      perfil: unknown;
    }>(`${this.api}/${id}`);
  }


  // ============================================================
  // CRIAR UTILIZADOR
  // ============================================================

  criar(
    dados: CriarUtilizadorAdmin
  ): Observable<RespostaUtilizador> {

    return this.http.post<RespostaUtilizador>(
      this.api,
      dados
    );
  }


  // ============================================================
  // ACTUALIZAR UTILIZADOR
  // ============================================================

  actualizar(
    id: number,
    dados: ActualizarUtilizadorAdmin
  ): Observable<RespostaUtilizador> {

    return this.http.put<RespostaUtilizador>(
      `${this.api}/${id}`,
      dados
    );
  }


  // ============================================================
  // ACTIVAR / DESACTIVAR CONTA
  // ============================================================

  alterarEstado(
    id: number
  ): Observable<RespostaUtilizador> {

    return this.http.patch<RespostaUtilizador>(
      `${this.api}/${id}/estado`,
      {}
    );
  }


  // ============================================================
  // ELIMINAR UTILIZADOR
  // ============================================================

  eliminar(id: number): Observable<{ message: string }> {

    return this.http.delete<{ message: string }>(
      `${this.api}/${id}`
    );
  }


  // ============================================================
  // LISTAR DIREÇÕES
  // ============================================================

  listarDepartamentos(): Observable<DepartamentoAdmin[]> {

    return this.http.get<DepartamentoAdmin[]>(
      `${environment.apiUrl}/departamentos`
    );
  }

}