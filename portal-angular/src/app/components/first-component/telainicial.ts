import { CommonModule } from '@angular/common';

import {
  Component,
  OnDestroy,
  OnInit
} from '@angular/core';

import { Router } from '@angular/router';

interface Departamento {
  sigla: string;
  nome: string;
  descricao: string;
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
  // CONSTRUTOR
  // ============================================================

  constructor(
    private router: Router
  ) {}


  // ============================================================
  // CICLO DE VIDA
  // ============================================================

  ngOnInit(): void {

    this.reiniciarAutoplay();
  }


  ngOnDestroy(): void {

    this.pausarAutoplay();
  }


  // ============================================================
  // NAVEGAÇÃO PARA O LOGIN
  // ============================================================

  abrirLogin(): void {

    this.router.navigate([
      '/acesso'
    ]);
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
