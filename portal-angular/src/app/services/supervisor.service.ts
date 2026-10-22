import { Injectable, inject } from '@angular/core';

import { HttpClient } from '@angular/common/http';

import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';


export interface UtilizadorSessao {
  id_utilizador: number;

  nome: string;

  email: string;

  tipo_utilizador: string;
}


export interface EstagioSupervisionado {
  id_estagio: number;

  data_inicio: string | null;

  data_fim_prevista: string | null;

  estado_estagio: string | null;

  nome_candidatura: string | null;

  email_candidatura: string | null;

  nome_departamento: string | null;

  nome_estagiario: string | null;
}


export interface ResumoSupervisor {
  estagios_ativos: number;
}


export interface PainelSupervisor {
  message: string;

  utilizador: UtilizadorSessao;

  resumo: ResumoSupervisor;

  estagios: EstagioSupervisionado[];
}


@Injectable({
  providedIn: 'root'
})
export class SupervisorService {

  private readonly http = inject(HttpClient);

  private readonly apiUrl =
    `${environment.apiUrl}/supervisor`;


  /**
   * Visão de leitura do supervisor: estágios activos e estagiários
   * associados. Não altera estados.
   */
  dashboard(): Observable<PainelSupervisor> {

    return this.http.get<PainelSupervisor>(
      `${this.apiUrl}/dashboard`
    );
  }

}
