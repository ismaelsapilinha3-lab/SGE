import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

export interface Departamento {
  id_departamento: number;
  nome: string;
  descricao?: string | null;
}

export interface UtilizadorAuth {
  id_utilizador: number;
  nome: string;
  email: string;
  tipo_utilizador: string;
}

export interface RespostaCandidatura {
  id_candidatura: number;
  token: string;
  user: UtilizadorAuth;
}

export interface LimiteCandidaturas {
  limite: number;
  submetidas: number;
  vagas_restantes: number;
  limite_atingido: boolean;
  trimestre: number;
  ano: number;
  inicio: string;
  fim: string;
}

@Injectable({
  providedIn: 'root'
})
export class CandidaturaService {

  private http = inject(HttpClient);

  private readonly baseUrl = environment.apiUrl;

  listarDepartamentos(): Observable<Departamento[]> {
    return this.http.get<Departamento[]>(
      `${this.baseUrl}/departamentos`
    );
  }

  limiteTrimestral(): Observable<LimiteCandidaturas> {
    return this.http.get<LimiteCandidaturas>(
      `${this.baseUrl}/candidaturas/limite`
    );
  }

  submeter(
    dados: {
      nome: string;
      email: string;
      password: string;
      password_confirmation: string;
      curso: string;
      universidade: string;
      nascimento: string;
      sexo: string;
      bi: string;
      contacto: string;
      id_departamento_selecionado: number;
    },
    documentos: {
      key: string;
      arquivo: File | null;
    }[]
  ): Observable<RespostaCandidatura> {

    const formData = new FormData();

    // Dados do candidato
    formData.append('nome', dados.nome);
    formData.append('email', dados.email);
    formData.append('password', dados.password);
    formData.append(
      'password_confirmation',
      dados.password_confirmation
    );
    formData.append('curso', dados.curso);
    formData.append('universidade', dados.universidade);
    formData.append('nascimento', dados.nascimento);
    formData.append('sexo', dados.sexo);
    formData.append('bi', dados.bi);
    formData.append('contacto', dados.contacto);

    formData.append(
      'id_departamento_selecionado',
      String(dados.id_departamento_selecionado)
    );

    // Documentos
    documentos.forEach(documento => {
      if (documento.arquivo) {
        formData.append(
          `documentos[${documento.key}]`,
          documento.arquivo,
          documento.arquivo.name
        );
      }
    });

    return this.http.post<RespostaCandidatura>(
      `${this.baseUrl}/candidaturas`,
      formData
    );
  }
}

