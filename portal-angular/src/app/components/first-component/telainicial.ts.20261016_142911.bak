import { CommonModule } from '@angular/common';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { Router, RouterLink } from '@angular/router';

interface Departamento {
  sigla: string;
  nome: string;
  descricao: string;
}

@Component({
  selector: 'app-telainicial',
  imports: [CommonModule, RouterLink],
  templateUrl: './telainicial.html',
  styleUrls: ['./telainicial.css']
})
export class TelainicialComponent implements OnInit, OnDestroy {

  logoUrl = 'assets/img/multitel.png';

  departamentos: Departamento[] = [
    { sigla: 'DDI', nome: 'Direção de Desenho e Inovação', descricao: 'Área dedicada à conceção, desenvolvimento, implementação e manutenção de sistemas e soluções tecnológicas.' },
    { sigla: 'DF', nome: 'Finanças', descricao: 'Área responsável pelo acompanhamento financeiro, controlo de custos, orçamento e apoio à gestão da organização.' },
    { sigla: 'RH', nome: 'Recursos Humanos', descricao: 'Área orientada para a gestão de pessoas, recrutamento, formação e acompanhamento do desenvolvimento profissional.' },
    { sigla: 'DOM', nome: 'Telecomunicações e Redes', descricao: 'Área dedicada às infraestruturas de comunicação, redes, monitorização e suporte técnico especializado.' },
    { sigla: 'MC', nome: 'Marketing e Comunicação', descricao: 'Área responsável pela comunicação institucional, promoção de serviços, campanhas e relacionamento com o público.' },
    { sigla: 'DC', nome: 'Comercial', descricao: 'Área focada no relacionamento com clientes, apresentação de soluções, vendas e desenvolvimento de oportunidades.' }
  ];

  departamentoAtual = 0;
  private temporizador?: ReturnType<typeof setInterval>;
  private readonly INTERVALO_MS = 4500;

  constructor(private router: Router) {}

  ngOnInit(): void {
    this.reiniciarAutoplay();
  }

  ngOnDestroy(): void {
    clearInterval(this.temporizador);
  }

  abrirLogin(): void {
    this.router.navigate(['/acesso']);
  }

  mostrar(indice: number): void {
    this.departamentoAtual = indice;
  }

  proximoDept(): void {
    this.mostrar((this.departamentoAtual + 1) % this.departamentos.length);
    this.reiniciarAutoplay();
  }

  anteriorDept(): void {
    this.mostrar((this.departamentoAtual - 1 + this.departamentos.length) % this.departamentos.length);
    this.reiniciarAutoplay();
  }

  irParaDept(indice: number): void {
    this.mostrar(indice);
    this.reiniciarAutoplay();
  }

  pausarAutoplay(): void {
    clearInterval(this.temporizador);
  }

  reiniciarAutoplay(): void {
    clearInterval(this.temporizador);
    this.temporizador = setInterval(() => this.proximoDept(), this.INTERVALO_MS);
  }
}

