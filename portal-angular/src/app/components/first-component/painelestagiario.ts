import { AfterViewInit, Component, Inject, PLATFORM_ID } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { Router } from '@angular/router';

type Estado = 'analise' | 'aceite' | 'recusado';

interface DadosEstagiario {
  nome: string;
  bi: string;
  contacto: string;
  email: string;
  nascimento: string;
  sexo: string;
  curso: string;
  universidade: string;
  estado: Estado;
  data: string;
}

interface DocumentoDB {
  id?: string;
  titulo: string;
  nome: string;
  ficheiro?: File;
}

interface ConfigEstado {
  badge: string;
  titulo: string;
  texto: string;
  p2: 'active' | 'done' | '';
  p3: 'done' | 'bad' | '';
  c3: string;
  l3: string;
}

const ESTADOS: Record<Estado, ConfigEstado> = {
  analise: {
    badge: 'Em análise',
    titulo: 'Pedido em análise',
    texto: 'A equipa responsável está a analisar a tua candidatura. O resultado aparece nesta área.',
    p2: 'active',
    p3: '',
    c3: '3',
    l3: 'Resultado',
  },
  aceite: {
    badge: 'Aceite',
    titulo: 'Candidatura aceite',
    texto: 'Parabéns! A tua candidatura foi aceite. A Multitel vai contactar-te com os próximos passos do estágio.',
    p2: 'done',
    p3: 'done',
    c3: '✓',
    l3: 'Aceite',
  },
  recusado: {
    badge: 'Não aceite',
    titulo: 'Candidatura não aceite',
    texto: 'Desta vez a tua candidatura não foi aceite. Para mais informações, contacta a linha de apoio: 223 530 000.',
    p2: 'done',
    p3: 'bad',
    c3: '✕',
    l3: 'Não aceite',
  },
};

@Component({
  selector: 'app-painelestagiario',
  standalone: true,
  templateUrl: './painelestagiario.html',
  styleUrl: './painelestagiario.css',
})
export class PainelestagiarioComponent implements AfterViewInit {
  private nDocs = 0;

  constructor(
    @Inject(PLATFORM_ID) private platformId: Object,
    private router: Router
  ) {}

  ngAfterViewInit(): void {
    if (!isPlatformBrowser(this.platformId)) return;

    let dados = this.obterEstagiario();

    if (!dados) {
      dados = {
        nome: 'Estagiário Exemplo',
        bi: '000000000LA000',
        contacto: '+244 923 000 000',
        email: 'estagiario@exemplo.com',
        nascimento: '2003-05-15',
        sexo: 'Masculino',
        curso: 'Engenharia Informática',
        universidade: 'Instituição de Ensino',
        estado: 'analise',
        data: new Date().toISOString(),
      };
      this.guardarEstagiario(dados);
    }

    this.preencherArea(dados);

    const ano = document.getElementById('ano-atual');
    if (ano) ano.textContent = String(new Date().getFullYear());
  }

  /* ----------------- PERSISTÊNCIA (localStorage) ----------------- */

  private guardarEstagiario(dados: DadosEstagiario): void {
    localStorage.setItem('multitel_estagiario', JSON.stringify(dados));
  }

  private obterEstagiario(): DadosEstagiario | null {
    const dados = localStorage.getItem('multitel_estagiario');
    return dados ? (JSON.parse(dados) as DadosEstagiario) : null;
  }

  /* ----------------- DOCUMENTOS GUARDADOS (IndexedDB) ----------------- */

  private abrirDB(): Promise<IDBDatabase> {
    return new Promise((ok, ko) => {
      const r = indexedDB.open('multitel_docs', 1);
      r.onupgradeneeded = () => r.result.createObjectStore('docs', { keyPath: 'id' });
      r.onsuccess = () => ok(r.result);
      r.onerror = () => ko(r.error);
    });
  }

  private async lerDocsDB(): Promise<DocumentoDB[]> {
    try {
      const db = await this.abrirDB();
      return await new Promise<DocumentoDB[]>((ok) => {
        const r = db.transaction('docs').objectStore('docs').getAll();
        r.onsuccess = () => ok(r.result as DocumentoDB[]);
        r.onerror = () => ok([]);
      });
    } catch (e) {
      return [];
    }
  }

  private async renderDocs(): Promise<void> {
    const lista = await this.lerDocsDB();
    this.nDocs = lista.length;
    this.renderHist();

    const el = document.getElementById('est-docs-lista');
    if (!el) return;
    el.textContent = '';

    const documentos: DocumentoDB[] = lista.length
      ? lista
      : [
          { titulo: 'Cópia do BI', nome: 'BI.pdf' },
          { titulo: 'Declaração de matrícula', nome: 'Declaracao_de_matricula.pdf' },
          { titulo: 'Carta a pedido do estágio', nome: 'Carta_de_pedido_de_estagio.pdf' },
          { titulo: 'Declaração', nome: 'Declaracao.pdf' },
          { titulo: 'Currículo Vitae', nome: 'Curriculo_Vitae.pdf' },
          { titulo: 'Foto tipo passe', nome: 'Foto_tipo_passe.jpg' },
        ];

    documentos.forEach((d) => {
      const a = document.createElement('article');
      a.className = 'est-doc';

      const prev = document.createElement('div');
      prev.className = 'est-doc-prev';

      if (d.ficheiro && (d.ficheiro.type || '').startsWith('image/')) {
        const img = document.createElement('img');
        img.alt = 'Pré-visualização de ' + d.titulo;
        img.src = URL.createObjectURL(d.ficheiro);
        prev.appendChild(img);
      } else {
        prev.textContent = (d.nome.split('.').pop() || 'DOC').toUpperCase().slice(0, 4);
      }

      const cab = document.createElement('div');
      cab.className = 'est-doc-head';

      const h = document.createElement('h3');
      h.textContent = d.titulo;

      const st = document.createElement('span');
      st.className = 'est-doc-status';
      st.textContent = 'Submetido';

      cab.append(h, st);

      const p = document.createElement('p');
      p.textContent = d.nome;

      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'est-doc-ver';
      b.textContent = 'Ver documento';
      b.onclick = () => {
        if (!d.ficheiro) return;
        const u = URL.createObjectURL(d.ficheiro);
        window.open(u, '_blank');
        setTimeout(() => URL.revokeObjectURL(u), 60000);
      };

      a.append(prev, cab, p, b);
      el.appendChild(a);
    });
  }

  /* ----------------- HISTÓRICO ----------------- */

  private renderHist(): void {
    const ol = document.getElementById('est-hist');
    if (!ol) return;

    const d = this.obterEstagiario() || ({} as Partial<DadosEstagiario>);
    const e: Estado = d.estado || 'analise';
    const data = d.data ? new Date(d.data).toLocaleDateString('pt-AO') : '';
    const n = this.nDocs || 0;

    const res: [string, string, string, string] =
      e === 'aceite'
        ? ['feito', '✓', 'Candidatura aceite', 'Vais receber os próximos passos do estágio.']
        : e === 'recusado'
        ? ['bad', '✕', 'Candidatura não aceite', 'Para mais informações, contacta a linha de apoio: 223 530 000.']
        : ['', '', 'Resultado', 'Ainda sem decisão. Vai aparecer aqui.'];

    const itens: string[][] = [
      ['feito', '✓', 'Candidatura submetida', 'Os teus dados foram enviados com sucesso.', data],
      [
        'feito',
        '✓',
        'Documentos recebidos',
        n ? n + ' documentos guardados e bloqueados para alterações.' : 'Documentos guardados e bloqueados para alterações.',
        data,
      ],
      [
        e === 'analise' ? 'atual' : 'feito',
        e === 'analise' ? '•' : '✓',
        'Análise da candidatura',
        'A equipa da Multitel avalia o teu pedido e os documentos.',
        '',
      ],
      [...res, ''],
    ];

    ol.innerHTML = itens
      .map(
        (i) =>
          '<li class="' + i[0] + '"><i>' + i[1] + '</i><div><strong>' + i[2] + '</strong><span>' + i[3] + '</span></div><time>' + i[4] + '</time></li>'
      )
      .join('');
  }

  /* ----------------- ESTADOS DA CANDIDATURA ----------------- */

  private aplicarEstado(e: Estado): void {
    const c = ESTADOS[e] || ESTADOS.analise;
    const estadoEl = document.querySelector<HTMLElement>('.est-status');
    if (estadoEl) estadoEl.dataset['estado'] = ESTADOS[e] ? e : 'analise';

    const tituloEl = document.getElementById('est-estado-titulo');
    if (tituloEl) tituloEl.textContent = c.titulo;

    const textoEl = document.getElementById('est-estado-texto');
    if (textoEl) textoEl.textContent = c.texto;

    const badgeEl = document.getElementById('est-estado-badge');
    if (badgeEl) badgeEl.textContent = c.badge;

    const passo2 = document.getElementById('est-passo2');
    if (passo2) passo2.className = 'est-step ' + c.p2;

    const p3 = document.getElementById('est-passo3');
    if (p3) {
      p3.className = 'est-step ' + c.p3;
      const circle = p3.querySelector<HTMLElement>('.est-step-circle');
      if (circle) circle.textContent = c.c3;
      const label = p3.querySelector<HTMLElement>('span');
      if (label) label.textContent = c.l3;
    }

    const demo = document.getElementById('est-demo-select') as HTMLSelectElement | null;
    if (demo) demo.value = ESTADOS[e] ? e : 'analise';

    this.renderHist();
  }

  /** Chamado pelo (change) do <select id="est-demo-select"> no template */
  simularEstado(valor: string): void {
    const e = valor as Estado;
    const d = this.obterEstagiario();
    if (d) {
      d.estado = e;
      this.guardarEstagiario(d);
    }
    this.aplicarEstado(e);
  }

  /* ----------------- PREENCHIMENTO DA ÁREA ----------------- */

  private preencherArea(dados: DadosEstagiario): void {
    const _pn = (dados.nome || 'Estagiário').trim().split(/\s+/)[0];
    const primeiroNome = _pn.charAt(0).toUpperCase() + _pn.slice(1).toLowerCase();

    const _av = document.getElementById('est-avatar');
    if (_av) _av.textContent = primeiroNome.charAt(0).toUpperCase();

    this.setText('nome-topo', 'Bem-vindo, ' + primeiroNome);
    this.setText('nome-hero', primeiroNome + '!');

    this.setText('perfil-nome', dados.nome || 'Não informado');
    this.setText('perfil-bi', dados.bi || 'Não informado');
    this.setText('perfil-contacto', dados.contacto || 'Não informado');
    this.setText('perfil-email', dados.email || 'Não informado');
    this.setText(
      'perfil-nascimento',
      dados.nascimento ? new Date(dados.nascimento + 'T00:00:00').toLocaleDateString('pt-AO') : 'Não informado'
    );
    this.setText('perfil-sexo', dados.sexo || 'Não informado');
    this.setText('perfil-curso', dados.curso || 'Não informado');
    this.setText('perfil-universidade', dados.universidade || 'Não informado');

    this.aplicarEstado(dados.estado || 'analise');

    const dc = document.getElementById('data-candidatura');
    if (dc && dados.data) dc.textContent = new Date(dados.data).toLocaleDateString('pt-AO');

    void this.renderDocs();
  }

  private setText(id: string, valor: string): void {
    const el = document.getElementById(id);
    if (el) el.textContent = valor;
  }

  /* ----------------- NAVEGAÇÃO E SESSÃO (chamadas pelo template) ----------------- */

  /** Chamado pelo (click) dos botões de navegação no template */
  irPara(id: string): void {
    const elemento = document.getElementById(id);
    if (elemento) elemento.scrollIntoView({ behavior: 'smooth' });
  }

  /** Chamado pelo (click) do botão "Sair" no template */
  voltarAoAcesso(): void {
    sessionStorage.removeItem('multitel_sessao');
    this.router.navigate(['/telainicial']);
  }
}