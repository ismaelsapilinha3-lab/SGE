import { TestBed } from '@angular/core/testing';
import {
  ActivatedRoute,
  Router,
  convertToParamMap
} from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';

import { AuthService } from '../../../core/auth.service';

import {
  PainelEstagio,
  RelatorioEstagiarioService
} from '../../../services/relatorio-estagiario.service';

import { AreaReservadaComponent } from './area-reservada';


describe('AreaReservadaComponent', () => {

  const parametros = convertToParamMap({ separador: 'documentos' });

  const route = {
    queryParamMap: of(parametros),
    snapshot: {
      queryParamMap: parametros
    }
  };

  const router = {
    navigate: vi.fn()
  };

  const authService = {
    terminarLocal: vi.fn()
  };

  const painel: PainelEstagio = {
    message: 'ok',
    estagio: {
      id_estagio: 1,
      id_candidatura: 1,
      estado_estagio: 'ativo',
      estado_estagio_legivel: 'Ativo',
      data_inicio: null,
      data_fim_prevista: null,
      duracao_meses: null,
      dia_limite_relatorio: 5,
      motivo_suspensao: null
    },
    candidatura: null,
    departamento: null,
    plano_estagio: null,
    planos_anteriores: [],
    documentos: [],
    relatorios: [],
    alertas: []
  };

  const relatorioService = {
    carregarPainel: vi.fn(() => of(painel))
  };

  beforeEach(async () => {

    router.navigate.mockClear();

    await TestBed.configureTestingModule({
      imports: [AreaReservadaComponent],
      providers: [
        {
          provide: ActivatedRoute,
          useValue: route
        },
        {
          provide: Router,
          useValue: router
        },
        {
          provide: AuthService,
          useValue: authService
        },
        {
          provide: RelatorioEstagiarioService,
          useValue: relatorioService
        }
      ]
    }).compileComponents();

  });


  it('abre o separador pedido pelo destino da notificação', () => {

    const fixture = TestBed.createComponent(
      AreaReservadaComponent
    );

    fixture.detectChanges();

    expect(fixture.componentInstance.separadorAtivo())
      .toBe('documentos');

  });


  it('limpa o separador da URL ao navegar manualmente', () => {

    const fixture = TestBed.createComponent(
      AreaReservadaComponent
    );

    fixture.detectChanges();

    fixture.componentInstance.selecionarSeparador('geral');

    expect(router.navigate).toHaveBeenCalledWith(
      [],
      expect.objectContaining({
        queryParams: { separador: null }
      })
    );

  });

});
