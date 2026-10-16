import { CommonModule, isPlatformBrowser } from '@angular/common';
import {
Component,
PLATFORM_ID,
computed,
inject,
signal
} from '@angular/core';
import { Router } from '@angular/router';

import { AuthService } from '../../core/auth.service';
import {
CandidaturaEstagiario,
CandidaturaEstagiarioService,
DocumentoEstagiario
} from '../../core/candidatura-estagiario.service';

type Estado = 'analise' | 'aceite' | 'recusado';

interface ConfigEstado {
css: string;
badge: string;
titulo: string;
texto: string;
p2: 'active' | 'done' | '';
p3: 'done' | 'bad' | '';
c3: string;
l3: string;
}

interface Historico {
classe: string;
icone: string;
titulo: string;
texto: string;
data: string;
}

const ESTADOS: Record<Estado, ConfigEstado> = {

analise: {
css: 'analise',
badge: 'Em análise',
titulo: 'Pedido em análise',
texto:
'A equipa responsÃ¡vel estÃ¡ a analisar a tua candidatura. O resultado aparecerá¡ nesta Ã¡rea.',
p2: 'active',
p3: '',
c3: '3',
l3: 'Resultado'
},

aceite: {
css: 'aceite',
badge: 'Aceite',
titulo: 'Candidatura aceite',
texto:
'Parabéns! A tua candidatura foi aceite. A Multitel vai contactar-te com os próximos passos do estágio.',
p2: 'done',
p3: 'done',
c3: 'âœ“',
l3: 'Aceite'
},

recusado: {
css: 'recusado',
badge: 'Não aceite',
titulo: 'Candidatura não aceite',
texto:
'A tua candidatura não foi aceite. Para mais informações, contacta a linha de apoio da Multitel.',
p2: 'done',
p3: 'bad',
c3: 'âœ•',
l3: 'Não aceite'
}
};

@Component({
selector: 'app-painelestagiario',
standalone: true,
imports: [CommonModule],
templateUrl: './painelestagiario.html',
styleUrl: './painelestagiario.css'
})
export class PainelestagiarioComponent {

private readonly platformId = inject(PLATFORM_ID);
private readonly router = inject(Router);

readonly authService = inject(AuthService);
private readonly candidaturaService =
inject(CandidaturaEstagiarioService);

readonly carregando = signal(true);
readonly erro = signal<string | null>(null);

readonly candidatura =
signal<CandidaturaEstagiario | null>(null);

readonly primeiroNome = computed(() => {
const utilizador = this.authService.utilizador();


if (!utilizador?.nome) {
  return 'EstagiÃ¡rio';
}

return utilizador.nome
  .trim()
  .split(/\s+/)[0];


});

readonly inicial = computed(() => {
const utilizador = this.authService.utilizador();


if (!utilizador?.nome) {
  return 'E';
}

return utilizador.nome
  .trim()
  .charAt(0)
  .toUpperCase();


});

readonly estado = computed<ConfigEstado>(() => {
const candidatura = this.candidatura();


if (!candidatura) {
  return ESTADOS.analise;
}

return ESTADOS[candidatura.estado] ?? ESTADOS.analise;


});

readonly texto = computed(() => {
return this.estado().texto;
});

readonly historico = computed<Historico[]>(() => {


const candidatura = this.candidatura();

if (!candidatura) {
  return [];
}

const historico: Historico[] = [];

historico.push({
  classe: 'done',
  icone: 'âœ“',
  titulo: 'Candidatura submetida',
  texto: 'A tua candidatura foi recebida pela Multitel.',
  data: candidatura.data_submissao
});

if (
  candidatura.estado === 'aceite' ||
  candidatura.estado === 'recusado'
) {
  historico.push({
    classe: 'done',
    icone: 'âœ“',
    titulo: 'Candidatura analisada',
    texto:
      'A candidatura foi analisada pela equipa responsÃ¡vel.',
    data: candidatura.data_submissao
  });
}

if (candidatura.estado === 'aceite') {
  historico.push({
    classe: 'done',
    icone: 'âœ“',
    titulo: 'Candidatura aceite',
    texto: 'A candidatura foi aceite pela Multitel.',
    data: candidatura.data_submissao
  });
}

if (candidatura.estado === 'recusado') {
  historico.push({
    classe: 'bad',
    icone: 'âœ•',
    titulo: 'Candidatura não aceite',
    texto: 'A candidatura não foi aceite.',
    data: candidatura.data_submissao
  });
}

return historico;


});

constructor() {


if (isPlatformBrowser(this.platformId)) {
  this.carregarPainel();
} else {
  this.carregando.set(false);
}


}

private carregarPainel(): void {


this.carregando.set(true);
this.erro.set(null);

this.candidaturaService.minhaCandidatura().subscribe({

  next: (dados) => {
    this.candidatura.set(dados);
    this.carregando.set(false);
  },

  error: (erro) => {

    console.error(
      'Erro ao carregar candidatura:',
      erro
    );

    this.candidatura.set(null);
    this.carregando.set(false);

    if (erro.status === 401) {
      this.erro.set(
        'A sessÃ£o expirou. FaÃ§a login novamente.'
      );
      return;
    }

    if (erro.status === 404) {
      this.erro.set(
        'Não foi encontrada uma candidatura associada a esta conta.'
      );
      return;
    }

    this.erro.set(
      'Não foi possÃ­vel carregar os dados da candidatura.'
    );
  }
});


}

recarregar(): void {
this.carregarPainel();
}

  irPara(rota: string): void {
    if (!rota) {
      return;
    }

    const id = rota.startsWith('#') ? rota.substring(1) : rota;
    const destino = document.getElementById(id);

    destino?.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });
  }

sair(): void {


this.authService.logout().subscribe({
  next: () => {
    this.router.navigate(['/acesso']);
  },

  error: () => {
    this.router.navigate(['/acesso']);
  }
});


}

extensao(documento: DocumentoEstagiario): string {


const nome = documento.nome_arquivo || '';

const partes = nome.split('.');

if (partes.length < 2) {
  return 'FILE';
}

return partes
  .pop()
  ?.toUpperCase() || 'FILE';


}

tituloDocumento(documento: DocumentoEstagiario): string {


if (documento.tipo_documento) {
  return documento.tipo_documento
    .replace(/_/g, ' ')
    .replace(/\b\w/g, letra => letra.toUpperCase());
}

if (documento.nome_arquivo) {
  return documento.nome_arquivo;
}

return 'Documento';


}

rotuloEstadoDocumento(
documento: DocumentoEstagiario
): string {


const estado =
  documento.estado?.toLowerCase();

switch (estado) {

  case 'aprovado':
  case 'aceite':
  case 'aceito':
    return 'Aprovado';

  case 'recusado':
  case 'rejeitado':
    return 'Recusado';

  case 'pendente':
  case 'em análise':
  case 'em_analise':
    return 'Em análise';

  default:
    return 'Recebido';
}


}

ver(documento: DocumentoEstagiario): void {


if (!isPlatformBrowser(this.platformId)) {
  return;
}

/*
 * Abre a janela imediatamente para evitar
 * bloqueio do popup pelo navegador.
 */
const janela = window.open(
  '',
  '_blank'
);

if (!janela) {
  this.erro.set(
    'O navegador bloqueou a abertura do documento.'
  );
  return;
}

this.candidaturaService
  .visualizarDocumento(documento.id_documento)
  .subscribe({

    next: (blob) => {

      const url =
        URL.createObjectURL(blob);

      janela.location.href = url;

      setTimeout(() => {
        URL.revokeObjectURL(url);
      }, 60000);
    },

    error: (erro) => {

      janela.close();

      console.error(
        'Erro ao abrir documento:',
        erro
      );

      this.erro.set(
        'Não foi possÃ­vel abrir este documento.'
      );
    }
  });


}

formatarData(
data: string | null | undefined
): string {


if (!data) {
  return '-';
}

const objeto = new Date(data);

if (Number.isNaN(objeto.getTime())) {
  return data;
}

return new Intl.DateTimeFormat(
  'pt-PT',
  {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  }
).format(objeto);


}

  navegarSecao(id: string): void {
    const secao = document.getElementById(id);

    if (!secao) {
      console.warn('Secção não encontrada:', id);
      return;
    }

    secao.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });
  }

}

