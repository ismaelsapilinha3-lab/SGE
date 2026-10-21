import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

export type TipoRelatorio = 'semanal' | 'mensal' | 'final';

export type EstadoAvaliacaoRelatorio =
  | 'pendente'
  | 'aprovado'
  | 'rejeitado'
  | 'em_atraso';

/* =========================================================
   RELATÓRIO DO ESTAGIÁRIO
   ========================================================= */

export interface RelatorioEstagiario {
  id_relatorio: number;
  id_estagio: number;
  tipo: TipoRelatorio;
  mes_referencia?: string | null;
  mes_referencia_legivel?: string | null;
  prazo_envio?: string | null;
  prazo_envio_legivel?: string | null;
  atividades: string;
  resultados: string;
  constrangimentos?: string | null;
  objetivos?: string | null;
  submetido_em?: string | null;
  enviado_em?: string | null;
  data_envio_legivel?: string | null;
  dia_envio?: string | null;
  mes_envio?: string | null;
  ano_envio?: string | null;
  hora_envio?: string | null;
  entregue?: boolean;
  em_atraso?: boolean;
  estado_avaliacao: EstadoAvaliacaoRelatorio;
  estado_avaliacao_legivel?: string;
  comentario_admin?: string | null;
  ficheiro_nome?: string | null;
  tem_ficheiro?: boolean;
  ficheiro_url?: string | null;
}

/* =========================================================
   PLANO DE ESTÁGIO
   ========================================================= */

export interface PlanoEstagio {
  id_plano_estagio: number;
  nome_arquivo: string;
  formato: string;
  formato_legivel: string;
  tamanho?: number | null;
  tamanho_legivel?: string;
  versao: number;
  data_upload?: string | null;
  data_upload_legivel?: string | null;
  estado?: string | null;
  url: string;
}

/* =========================================================
   DOCUMENTO DA CANDIDATURA
   ========================================================= */

export interface DocumentoEstagiario {
  id_documento: number;
  tipo_documento: string;
  tipo_legivel: string;
  versao: number;
  nome_arquivo: string;
  data_upload?: string | null;
  data_upload_legivel?: string | null;
  estado?: string | null;
  descricao?: string | null;
  url: string;
  pode_substituir: boolean;
}

/* =========================================================
   ALERTAS E NOTIFICAÇÕES
   ========================================================= */

export interface AlertaEstagiario {
  tipo: 'atraso' | 'rejeitado' | 'notificacao';
  id_relatorio?: number;
  id_notificacao?: number;
  titulo: string;
  mensagem: string;
  data?: string | null;
}

/* =========================================================
   PAINEL DO ESTÁGIO ATIVO
   ========================================================= */

export interface PainelEstagio {
  message: string;

  estagio: {
    id_estagio: number;
    id_candidatura: number;
    estado_estagio: string;
    estado_estagio_legivel: string;
    data_inicio?: string | null;
    data_fim_prevista?: string | null;
    duracao_meses?: number | null;
    dia_limite_relatorio: number;
    motivo_suspensao?: string | null;
  };

  candidatura: {
    id_candidatura: number;
    nome: string;
    email: string;
    bi: string;
    contacto: string;
    nascimento?: string | null;
    sexo: string;
    curso: string;
    universidade: string;
    estado: string;
  } | null;

  departamento: {
    id_departamento: number;
    nome: string;
    descricao?: string | null;
  } | null;

  plano_estagio: PlanoEstagio | null;

  planos_anteriores: PlanoEstagio[];

  documentos: DocumentoEstagiario[];

  relatorios: RelatorioEstagiario[];

  alertas: AlertaEstagiario[];
}

/* =========================================================
   DADOS PARA ENVIAR UM RELATÓRIO
   ========================================================= */

export interface NovoRelatorio {
  tipo: TipoRelatorio;
  mes_referencia: string;
  atividades: string;
  resultados: string;
  constrangimentos: string;
  objetivos: string;
}

export interface RespostaRelatorio {
  message: string;
  data: RelatorioEstagiario;
}

@Injectable({ providedIn: 'root' })
export class RelatorioEstagiarioService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;

  /**
   * Dados completos do estágio ativo (candidatura, plano, relatórios e alertas).
   */
  carregarPainel(): Observable<PainelEstagio> {
    return this.http.get<PainelEstagio>(
      `${this.apiUrl}/estagiario/painel`
    );
  }

  listarMeusRelatorios(): Observable<{
    data: RelatorioEstagiario[];
    alertas: AlertaEstagiario[];
  }> {
    return this.http.get<{
      data: RelatorioEstagiario[];
      alertas: AlertaEstagiario[];
    }>(`${this.apiUrl}/estagiario/relatorios`);
  }

  /**
   * Primeira forma de envio: preencher o formulário no portal.
   */
  submeterRelatorio(
    dados: NovoRelatorio
  ): Observable<RespostaRelatorio> {
    return this.http.post<RespostaRelatorio>(
      `${this.apiUrl}/estagiario/relatorios`,
      dados
    );
  }

  /**
   * Segunda forma de envio: carregar o documento em PDF ou Word.
   */
  carregarRelatorio(
    ficheiro: File,
    dados: {
      tipo: TipoRelatorio;
      mes_referencia?: string;
      relatorio_id?: number;
    }
  ): Observable<RespostaRelatorio> {
    const formData = new FormData();

    formData.append('ficheiro', ficheiro);
    formData.append('tipo', dados.tipo);

    if (dados.mes_referencia) {
      formData.append('mes_referencia', dados.mes_referencia);
    }

    if (dados.relatorio_id) {
      formData.append('relatorio_id', String(dados.relatorio_id));
    }

    return this.http.post<RespostaRelatorio>(
      `${this.apiUrl}/estagiario/relatorios/ficheiro`,
      formData
    );
  }

  /**
   * Abre o ficheiro de um relatório ou de um plano de estágio.
   */
  obterFicheiro(url: string): Observable<Blob> {
    const caminho = url.startsWith('http')
      ? url
      : `${environment.apiUrl.replace(/\/api$/, '')}${url}`;

    return this.http.get(caminho, { responseType: 'blob' });
  }

  /**
   * Substitui um documento da candidatura por uma nova versão.
   */
  substituirDocumento(
    idDocumento: number,
    ficheiro: File
  ): Observable<{ message: string; documento: DocumentoEstagiario }> {
    const formData = new FormData();

    formData.append('ficheiro', ficheiro);

    return this.http.post<{ message: string; documento: DocumentoEstagiario }>(
      `${environment.apiUrl}/documentos/${idDocumento}/substituir`,
      formData
    );
  }
}
