// candidatura.service.ts
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class CandidaturaService {
  private base = 'http://localhost:8000/api';

  constructor(private http: HttpClient) {}

  listarDepartamentos(): Observable<any[]> {
    return this.http.get<any[]>(`${this.base}/departamentos`);
  }

  criarCandidatura(dados: any): Observable<any> {
    return this.http.post(`${this.base}/candidaturas`, dados);
  }

  enviarDocumento(idCandidatura: number, tipo: string, ficheiro: File): Observable<any> {
    const formData = new FormData();
    formData.append('id_candidatura', String(idCandidatura));
    formData.append('tipo_documento', tipo);
    formData.append('ficheiro', ficheiro);

    return this.http.post(`${this.base}/documentos`, formData);
  }
}
