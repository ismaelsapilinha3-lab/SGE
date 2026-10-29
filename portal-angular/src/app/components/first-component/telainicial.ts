import { CommonModule } from '@angular/common';

import {
  Component,
  HostListener,
  OnDestroy,
  OnInit
} from '@angular/core';

import { Router } from '@angular/router';

import { CandidaturaService } from '../../services/candidatura.service';

interface Departamento {
  sigla: string;
  nome: string;
  descricao: string;
}

interface FotoGaleria {
  src: string;
  alt: string;
  legenda: string;
  indisponivel: boolean;
}

@Component({
  selector: 'app-telainicial',
  standalone: true,
  imports: [
    CommonModule
  ],
  templateUrl: './telainicial.html',
  styleUrls: ['./telainicial.css']
})
export class TelainicialComponent implements OnInit, OnDestroy {

  // ============================================================
  // LOGÓTIPO
  // ============================================================

  logoUrl = 'assets/img/multitel.png';


  // ============================================================
  // DEPARTAMENTOS E DIREÇÕES
  // ============================================================

  /*
  | Os departamentos são carregados a partir da API. A lista abaixo é
  | apenas abase de partida, usada enquanto o pedido está em curso ou
  | caso a API não responda.
  */
  departamentos: Departamento[] = [

    {
      sigla: 'DDI',
      nome: 'Direção de Desenho e Inovação',
      descricao: 'Área dedicada à conceção, desenvolvimento, implementação e manutenção de sistemas e soluções tecnológicas.'
    },

    {
      sigla: 'DF',
      nome: 'Finanças',
      descricao: 'Área responsável pelo acompanhamento financeiro, controlo de custos, orçamento e apoio à gestão da organização.'
    },

    {
      sigla: 'RH',
      nome: 'Recursos Humanos',
      descricao: 'Área orientada para a gestão de pessoas, recrutamento, formação e acompanhamento do desenvolvimento profissional.'
    },

    {
      sigla: 'DOM',
      nome: 'Telecomunicações e Redes',
      descricao: 'Área dedicada às infraestruturas de comunicação, redes, monitorização e suporte técnico especializado.'
    },

    {
      sigla: 'MC',
      nome: 'Marketing e Comunicação',
      descricao: 'Área responsável pela comunicação institucional, promoção de serviços, campanhas e relacionamento com o público.'
    },

    {
      sigla: 'DC',
      nome: 'Comercial',
      descricao: 'Área focada no relacionamento com clientes, apresentação de soluções, vendas e desenvolvimento de oportunidades.'
    }

  ];


  // ============================================================
  // CONTROLO DO CARROSSEL
  // ============================================================

  departamentoAtual = 0;

  private temporizador?: ReturnType<typeof setInterval>;

  private readonly INTERVALO_MS = 4500;


  // ============================================================
  // GALERIA
  // ============================================================

  /*
  | As fotografias ficam em public/assets/img/galeria/. Enquanto um
  | ficheiro não existir, o cartão mostra um marcador visual com a
  | legenda em vez de uma imagem quebrada.
  */
  readonly fotosGaleria: FotoGaleria[] = [

    {
      src: 'assets/img/galeria/equipa.jpg',
      alt: 'Equipa da Multitel em ambiente de trabalho',
      legenda: 'A nossa equipa',
      indisponivel: false
    },

    {
      src: 'assets/img/galeria/formacao.jpg',
      alt: 'Sessão de formação com estagiários',
      legenda: 'Formação e aprendizagem',
      indisponivel: false
    },

    {
      src: 'assets/img/galeria/infraestruturas.jpg',
      alt: 'Infraestruturas de redes e telecomunicações',
      legenda: 'Infraestruturas e redes',
      indisponivel: false
    },

    {
      src: 'assets/img/galeria/data-center.jpg',
      alt: 'Sala técnica do Data Center da Multitel',
      legenda: 'Data Center',
      indisponivel: false
    },

    {
      src: 'assets/img/galeria/acompanhamento.jpg',
      alt: 'Supervisor a acompanhar o trabalho de um estagiário',
      legenda: 'Acompanhamento próximo',
      indisponivel: false
    },

    {
      src: 'assets/img/galeria/talento.jpg',
      alt: 'Jovens profissionais em início de carreira',
      legenda: 'Novos talentos',
      indisponivel: false
    }

  ];

  fotoAmpliada: FotoGaleria | null = null;

  indiceAmpliado = 0;


  // ============================================================
  // MENU DE NAVEGAÇÃO
  // ============================================================

  menuAberto = false;


  // ============================================================
  // CONSTRUTOR
  // ============================================================

  constructor(
    private router: Router,
    private candidaturaService: CandidaturaService
  ) {}


  // ============================================================
  // CICLO DE VIDA
  // ============================================================

  ngOnInit(): void {

    this.carregarDepartamentos();

    this.reiniciarAutoplay();
  }


  ngOnDestroy(): void {

    this.pausarAutoplay();
  }


  // ============================================================
  // CARREGAR DEPARTAMENTOS DA API
  // ============================================================

  carregarDepartamentos(): void {

    this.candidaturaService.listarDepartamentos().subscribe({
      next: (departamentos) => {

        if (!departamentos?.length) {
          return;
        }

        this.departamentos = departamentos.map((departamento) => ({
          sigla: departamento.nome
            .trim()
            .split(/\s+/)
            .map((palavra) => palavra.charAt(0))
            .join('')
            .toUpperCase()
            .slice(0, 3),
          nome: departamento.nome,
          descricao: departamento.descricao ?? ''
        }));

        this.departamentoAtual = 0;
      },
      error: (erro: unknown) => {
        console.error('Erro ao carregar departamentos:', erro);
      }
    });
  }


  // ============================================================
  // NAVEGAÇÃO PARA O LOGIN
  // ============================================================

  abrirLogin(): void {

    this.fecharMenu();

    this.router.navigate([
      '/acesso'
    ]);
  }


  // ============================================================
  // MENU RESPONSIVO
  // ============================================================

  alternarMenu(): void {

    this.menuAberto = !this.menuAberto;
  }


  fecharMenu(): void {

    this.menuAberto = false;
  }


  // ============================================================
  // NAVEGAÇÃO ENTRE SECÇÕES
  // ============================================================

  irParaSeccao(id: string, evento?: Event): void {

    evento?.preventDefault();

    this.fecharMenu();

    if (typeof document === 'undefined') {

      return;
    }

    const alvo = document.getElementById(id);

    if (!alvo) {

      return;
    }

    if (typeof alvo.scrollIntoView === 'function') {

      alvo.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });

    }

    history.replaceState(null, '', `#${id}`);
  }


  // ============================================================
  // GALERIA DE FOTOGRAFIAS
  // ============================================================

  abrirGaleria(indice: number): void {

    const foto = this.fotosGaleria[indice];

    if (!foto || foto.indisponivel) {
      return;
    }

    this.indiceAmpliado = indice;

    this.fotoAmpliada = foto;
  }


  fecharGaleria(): void {

    this.fotoAmpliada = null;
  }


  fotoAnterior(evento?: Event): void {

    evento?.stopPropagation();

    this.moverGaleria(-1);
  }


  fotoSeguinte(evento?: Event): void {

    evento?.stopPropagation();

    this.moverGaleria(1);
  }


  marcarFotoIndisponivel(foto: FotoGaleria): void {

    foto.indisponivel = true;

    if (this.fotoAmpliada === foto) {

      this.fotoAmpliada = null;

    }
  }


  @HostListener('document:keydown', ['$event'])
  aoTeclaGaleria(evento: KeyboardEvent): void {

    if (!this.fotoAmpliada) {
      return;
    }

    if (evento.key === 'Escape') {

      this.fecharGaleria();

      return;
    }

    if (evento.key === 'ArrowLeft') {

      this.moverGaleria(-1);

      return;
    }

    if (evento.key === 'ArrowRight') {

      this.moverGaleria(1);
    }
  }


  /**
   * Percorre a galeria ignorando as fotografias ainda não disponíveis.
   */
  private moverGaleria(passo: number): void {

    const total = this.fotosGaleria.length;

    if (total === 0) {
      return;
    }

    let indice = this.indiceAmpliado;

    for (let tentativa = 0; tentativa < total; tentativa++) {

      indice = (indice + passo + total) % total;

      const foto = this.fotosGaleria[indice];

      if (!foto.indisponivel) {

        this.indiceAmpliado = indice;

        this.fotoAmpliada = foto;

        return;
      }
    }
  }


  // ============================================================
  // MOSTRAR DEPARTAMENTO
  // ============================================================

  mostrar(indice: number): void {

    if (
      indice < 0 ||
      indice >= this.departamentos.length
    ) {
      return;
    }

    this.departamentoAtual = indice;
  }


  // ============================================================
  // DEPARTAMENTO SEGUINTE
  // ============================================================

  proximoDept(): void {

    if (this.departamentos.length === 0) {
      return;
    }

    this.mostrar(
      (
        this.departamentoAtual + 1
      ) % this.departamentos.length
    );
  }


  // ============================================================
  // DEPARTAMENTO ANTERIOR
  // ============================================================

  anteriorDept(): void {

    if (this.departamentos.length === 0) {
      return;
    }

    this.mostrar(
      (
        this.departamentoAtual - 1 +
        this.departamentos.length
      ) % this.departamentos.length
    );
  }


  // ============================================================
  // SELECIONAR DEPARTAMENTO PELOS INDICADORES
  // ============================================================

  irParaDept(indice: number): void {

    this.mostrar(indice);

    this.reiniciarAutoplay();
  }


  // ============================================================
  // PAUSAR MUDANÇA AUTOMÁTICA
  // ============================================================

  pausarAutoplay(): void {

    if (this.temporizador !== undefined) {

      clearInterval(this.temporizador);

      this.temporizador = undefined;
    }
  }


  // ============================================================
  // REINICIAR MUDANÇA AUTOMÁTICA
  // ============================================================

  reiniciarAutoplay(): void {

    this.pausarAutoplay();

    if (this.departamentos.length <= 1) {
      return;
    }

    this.temporizador = setInterval(() => {

      this.proximoDept();

    }, this.INTERVALO_MS);
  }

}
