import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

export interface EstagioAtivo {
  id_estagio: number;
  id_candidatura: number;
  data_inicio: string;
  data_fim_prevista: string;
  estado_estagio: 'ativo';
}

@Injectable({ providedIn: 'root' })
export class EstagioAtivoService {
  private readonly http = inject(HttpClient);

  verificarEstagioAtivo(): Observable<EstagioAtivo> {
    return this.http.get<EstagioAtivo>(
      `${environment.apiUrl}/estagiario/estagio-ativo`
    );
  }
}