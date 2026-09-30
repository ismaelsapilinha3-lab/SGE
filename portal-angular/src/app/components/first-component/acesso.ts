import { CommonModule } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { CandidaturaService, Departamento } from '../../services/candidatura.service';

interface DadosEstagiario {
  nome: string;
  curso: string;
  universidade: string;
  nascimento: string;
  sexo: string;
  bi: string;
  email: string;
  contacto: string;
  id_departamento_selecionado: number | null;
}

interface DocumentoUpload {
  key: string;
  rotulo: string;
  dica: string;
  accept: string;
  arquivo: File | null;
}

interface Mensagem {
  texto: string;
  tipo: '' | 'ok' | 'erro';
}

@Component({
  selector: 'app-acesso',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './acesso.html',
  styleUrls: ['./acesso.css']
})
export class AcessoComponent implements OnInit {

  abaAtiva: 'login' | 'cadastro' = 'login';

  login = { email: '', senha: '' };
  loginMensagem: Mensagem = { texto: '', tipo: '' };

  cadastro: DadosEstagiario = {
    nome: '', curso: '', universidade: '', nascimento: '',
    sexo: '', bi: '', email: '', contacto: '',
    id_departamento_selecionado: null
  };

  cadastroMensagem: Mensagem = { texto: '', tipo: '' };

  departamentos: Departamento[] = [];

  documentos: DocumentoUpload[] = [
    { key: 'declaracao_escolar', rotulo: 'Declaração <br>ou<br> Certificado Académico', dica: 'Arraste ou clique para escolher', accept: '.pdf,.jpg,.jpeg,.png', arquivo: null },
    { key: 'foto', rotulo: 'Foto tipo passe', dica: 'Arraste ou clique para escolher', accept: '.jpg,.jpeg,.png', arquivo: null },
    { key: 'bi', rotulo: 'Cópia do BI <br>ou<br> Passaporte', dica: 'Arraste ou clique para escolher', accept: '.pdf,.jpg,.jpeg,.png', arquivo: null },
    { key: 'carta_solicitacao', rotulo: 'Carta de solicitação', dica: 'Indica a área em que queres trabalhar (PDF)', accept: '.pdf', arquivo: null },
    { key: 'cv', rotulo: 'Curriculum Vitae', dica: 'Arraste ou clique para escolher', accept: '.pdf,.jpg,.jpeg,.png', arquivo: null }
  ];

  constructor(
    private router: Router,
    private candidaturaService: CandidaturaService
  ) {}

  ngOnInit(): void {
    this.candidaturaService.listarDepartamentos().subscribe({
      next: (deps: Departamento[]) => (this.departamentos = deps),
      error: () =>
        (this.cadastroMensagem = {
          texto: 'Não foi possível carregar os departamentos.',
          tipo: 'erro'
        })
    });
  }

  mostrarFormulario(tipo: 'login' | 'cadastro'): void {
    this.abaAtiva = tipo;
  }

  onFileChange(event: Event, doc: DocumentoUpload): void {
    const input = event.target as HTMLInputElement;
    doc.arquivo = input.files && input.files.length > 0 ? input.files[0] : null;
  }

  onFileDrop(event: DragEvent, doc: DocumentoUpload): void {
    event.preventDefault();
    const arquivos = event.dataTransfer?.files;
    if (arquivos && arquivos.length > 0) {
      doc.arquivo = arquivos[0];
    }
  }

  onSubmitCadastro(): void {
    const faltam = this.documentos.filter(d => !d.arquivo);
    if (faltam.length > 0) {
      this.cadastroMensagem = { texto: 'Envia todos os documentos antes de continuar.', tipo: 'erro' };
      return;
    }

    this.candidaturaService.submeter(this.cadastro, this.documentos).subscribe({
      next: () => {
        this.guardarEstagiario(this.cadastro);
        this.cadastroMensagem = { texto: 'Candidatura enviada com sucesso!', tipo: 'ok' };
        setTimeout(() => this.abrirAreaEstagiario(this.cadastro), 800);
      },
      error: (err: HttpErrorResponse) => {
        if (err.status === 429) {
          this.cadastroMensagem = { texto: 'Demasiadas tentativas. Aguarda um minuto.', tipo: 'erro' };
          return;
        }
        const erros = err?.error?.errors;
        const primeiro = erros ? (Object.values(erros)[0] as string[])[0] : null;
        this.cadastroMensagem = {
          texto: primeiro ?? err?.error?.message ?? 'Erro ao enviar a candidatura.',
          tipo: 'erro'
        };
      }
    });
  }

  onSubmitLogin(): void {
    const dados = this.obterEstagiario();

    if (!dados) {
      this.loginMensagem = { texto: 'Ainda não existe uma conta. Cria a tua conta primeiro.', tipo: 'erro' };
      return;
    }

    if (dados.email.toLowerCase() === this.login.email.trim().toLowerCase()) {
      this.loginMensagem = { texto: 'Acesso autorizado. A abrir a tua área...', tipo: 'ok' };
      setTimeout(() => this.abrirAreaEstagiario(dados), 400);
    } else {
      this.loginMensagem = { texto: 'E-mail ou palavra-passe incorretos.', tipo: 'erro' };
    }
  }

  voltarParaPaginaInicial(): void {
    this.router.navigate(['/telainicial']);
  }

  private guardarEstagiario(dados: DadosEstagiario): void {
    localStorage.setItem('multitel_estagiario', JSON.stringify(dados));
  }

  private obterEstagiario(): DadosEstagiario | null {
    const dados = localStorage.getItem('multitel_estagiario');
    return dados ? JSON.parse(dados) : null;
  }

  private abrirAreaEstagiario(dados: DadosEstagiario): void {
    sessionStorage.setItem('multitel_sessao', 'ativa');
    this.router.navigate(['/estagiario'], { state: { estagiario: dados } });
  }
}