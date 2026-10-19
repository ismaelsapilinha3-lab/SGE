import { CommonModule } from '@angular/common';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { AdminService, Departamento as DepartamentoApi } from '../../services/admin.service';

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
  departamentos: Departamento[] = [];
  departamentoAtual = 0;

  private temporizador?: ReturnType<typeof setInterval>;
  private readonly INTERVALO_MS = 4500;

  constructor(
    private router: Router,
    private adminService: AdminService
  ) {}

  ngOnInit(): void {
    this.carregarDepartamentos();
    this.reiniciarAutoplay();
  }

  ngOnDestroy(): void {
    clearInterval(this.temporizador);
  }

  carregarDepartamentos(): void {
    this.adminService.listarDepartamentos().subscribe({
      next: (resposta: DepartamentoApi[]) => {
        this.departamentos = (resposta ?? [])
          .filter(departamento => departamento.ativo !== false)
          .map(departamento => ({
            sigla: departamento.nome
              .trim()
              .split(/\s+/)
              .map(palavra => palavra.charAt(0))
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
        this.departamentos = [];
        this.departamentoAtual = 0;
      }
    });
  }

  abrirLogin(): void {
    this.router.navigate(['/acesso']);
  }

  mostrar(indice: number): void {
    if (indice < 0 || indice >= this.departamentos.length) {
      return;
    }

    this.departamentoAtual = indice;
  }

  proximoDept(): void {
    if (this.departamentos.length === 0) {
      return;
    }

    this.mostrar((this.departamentoAtual + 1) % this.departamentos.length);
    this.reiniciarAutoplay();
  }

  anteriorDept(): void {
    if (this.departamentos.length === 0) {
      return;
    }

    this.mostrar(
      (this.departamentoAtual - 1 + this.departamentos.length)
        % this.departamentos.length
    );
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

    this.temporizador = setInterval(
      () => this.proximoDept(),
      this.INTERVALO_MS
    );
  }
}