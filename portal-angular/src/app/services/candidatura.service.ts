import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface Departamento {
  id_departamento: number;
  nome: string;
}

@Injectable({ providedIn: 'root' })
export class CandidaturaService {
  private base = 'http://localhost:8000/api';

  constructor(private http: HttpClient) {}

  listarDepartamentos(): Observable<Departamento[]> {
    return this.http.get<Departamento[]>(`${this.base}/departamentos`);
  }

  submeter(
    dados: Record<string, any>,
    documentos: { key: string; arquivo: File | null }[]
  ): Observable<{ id_candidatura: number }> {
    const formData = new FormData();

    Object.entries(dados).forEach(([campo, valor]) => {
      if (valor !== null && valor !== undefined) {
        formData.append(campo, String(valor));
      }
    });

    documentos.forEach(doc => formData.append(`documentos[${doc.key}]`, doc.arquivo as File));

    return this.http.post<{ id_candidatura: number }>(`${this.base}/candidaturas`, formData);
  }
}