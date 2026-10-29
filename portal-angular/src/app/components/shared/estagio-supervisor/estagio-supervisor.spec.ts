import { SimpleChange } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { vi } from 'vitest';

import {
  EstagioSupervisorService
} from '../../../services/estagio-supervisor.service';

import { EstagioSupervisorComponent } from './estagio-supervisor';


describe('EstagioSupervisorComponent', () => {

  const estagioService = {
    estagios: vi.fn(() => of({
      estagios: [
        { id_estagio: 1 },
        { id_estagio: 2 }
      ]
    })),
    plano: vi.fn(() => of({
      plano: null,
      historico: []
    })),
    relatorios: vi.fn(() => of({
      relatorios: []
    }))
  };

  beforeEach(async () => {

    await TestBed.configureTestingModule({
      imports: [EstagioSupervisorComponent],
      providers: [
        {
          provide: EstagioSupervisorService,
          useValue: estagioService
        }
      ]
    }).compileComponents();

  });


  it('abre o estágio e a aba pedidos pela notificação', () => {

    const fixture = TestBed.createComponent(
      EstagioSupervisorComponent
    );

    const componente = fixture.componentInstance;

    componente.estagioSolicitado = 2;

    componente.abaSolicitada = 'relatorios';

    componente.ngOnChanges({
      estagioSolicitado: new SimpleChange(null, 2, false),
      abaSolicitada: new SimpleChange(
        null,
        'relatorios',
        false
      )
    });

    expect(componente.estagioSelecionadoId()).toBe(2);

    expect(componente.aba()).toBe('relatorios');

    expect(estagioService.relatorios).toHaveBeenCalledWith(2);

  });


  it('usa a aba do plano quando o destino não é reconhecido', () => {

    const fixture = TestBed.createComponent(
      EstagioSupervisorComponent
    );

    const componente = fixture.componentInstance;

    componente.estagioSolicitado = 2;

    componente.abaSolicitada = 'inexistente';

    componente.ngOnChanges({
      estagioSolicitado: new SimpleChange(null, 2, false),
      abaSolicitada: new SimpleChange(
        null,
        'inexistente',
        false
      )
    });

    expect(componente.estagioSelecionadoId()).toBe(2);

    expect(componente.aba()).toBe('plano');

    expect(estagioService.plano).toHaveBeenCalledWith(2);

  });


  it('volta a aplicar o mesmo destino depois de este ser limpo', () => {

    const fixture = TestBed.createComponent(
      EstagioSupervisorComponent
    );

    const componente = fixture.componentInstance;

    componente.estagioSolicitado = 2;

    componente.abaSolicitada = 'relatorios';

    componente.ngOnChanges({
      estagioSolicitado: new SimpleChange(null, 2, false),
      abaSolicitada: new SimpleChange(
        null,
        'relatorios',
        false
      )
    });

    componente.selecionarEstagio(1);

    componente.estagioSolicitado = null;

    componente.abaSolicitada = null;

    componente.ngOnChanges({
      estagioSolicitado: new SimpleChange(2, null, false),
      abaSolicitada: new SimpleChange(
        'relatorios',
        null,
        false
      )
    });

    componente.estagioSolicitado = 2;

    componente.abaSolicitada = 'relatorios';

    componente.ngOnChanges({
      estagioSolicitado: new SimpleChange(null, 2, false),
      abaSolicitada: new SimpleChange(
        null,
        'relatorios',
        false
      )
    });

    expect(componente.estagioSelecionadoId()).toBe(2);

    expect(componente.aba()).toBe('relatorios');

  });

});
