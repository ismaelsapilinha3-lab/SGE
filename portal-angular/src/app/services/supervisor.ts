import { inject, Injectable } from '@angular/core';

import { HttpClient, HttpHeaders } from '@angular/common/http';

import { Observable } from 'rxjs';

import { environment } from '../../environments/environment';

import { AuthService } from '../core/auth.service';


export interface CriarSupervisor {
  nome: string;
  email: string;
  password: string;
  password_confirmation: string;
  telefone?: string;
}


export interface SupervisorCriado {
  id_utilizador: number;
  nome: string;
  email: string;
  telefone: string | null;
  tipo_utilizador: 'supervisor';
  ativo: boolean;
  id_departamento: number;
}


export interface RespostaCriarSupervisor {
  message: string;
  supervisor: SupervisorCriado;
}


export interface SupervisorListado {
  id_utilizador: number;
  nome: string;
  email: string;
  telefone: string | null;
  id_departamento: number | null;
}


export interface RespostaListarSupervisores {
  supervisores: SupervisorListado[];
}


@Injectable({
  providedIn: 'root'
})
export class SupervisorService {

  private readonly http = inject(HttpClient);

  private readonly authService = inject(AuthService);


  private readonly apiUrl =
    `${environment.apiUrl}/coordenador/supervisores`;


  criar(
    dados: CriarSupervisor
  ): Observable<RespostaCriarSupervisor> {

    const token = this.authService.token();

    if (!token) {
      throw new Error(
        'A sessão expirou. Inicia sessão novamente.'
      );
    }

    const headers = new HttpHeaders({
      Authorization: `Bearer ${token}`,
      Accept: 'application/json'
    });

    return this.http.post<RespostaCriarSupervisor>(
      this.apiUrl,
      dados,
      { headers }
    );
  }


  listar(): Observable<RespostaListarSupervisores> {

    const token = this.authService.token();

    if (!token) {
      throw new Error(
        'A sessão expirou. Inicia sessão novamente.'
      );
    }

    const headers = new HttpHeaders({
      Authorization: `Bearer ${token}`,
      Accept: 'application/json'
    });

    return this.http.get<RespostaListarSupervisores>(
      this.apiUrl,
      { headers }
    );
  }

}

