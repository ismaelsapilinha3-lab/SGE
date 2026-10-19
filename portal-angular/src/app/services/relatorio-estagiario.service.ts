import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

export type TipoRelatorio = 'semanal' | 'mensal' | 'final';

export interface NovoRelatorio {
  tipo: TipoRelatorio;
  periodo_referencia: string;
  atividades: string;
  resultados: string;
  constrangimentos: string;
  objetivos: string;
}

export interface RelatorioEstagiario {
  id_relatorio: number;
  id_estagio: number;
  tipo: TipoRelatorio;
  periodo_referencia: string;
  atividades: string;
  resultados: string;
  constrangimentos?: string | null;
  objetivos?: string | null;
  submetido_em?: string | null;
}

export interface RespostaRelatorio {
  message: string;
  data: RelatorioEstagiario;
}

@Injectable({ providedIn: 'root' })
export class RelatorioEstagiarioService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  listarMeusRelatorios(): Observable<{
    data: RelatorioEstagiario[];
  }> {
    return this.http.get<{ data: RelatorioEstagiario[] }>(
      `${this.apiUrl}/estagiario/relatorios`
    );
  }

  submeterRelatorio(
    dados: NovoRelatorio
  ): Observable<RespostaRelatorio> {
    return this.http.post<RespostaRelatorio>(
      `${this.apiUrl}/estagiario/relatorios`,
      dados
    );
  }
}