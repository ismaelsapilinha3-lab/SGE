import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { vi } from 'vitest';

import {
  AdminService,
  EstagiosAdminResponse
} from '../../../services/admin.service';

import {
  EstagioAdminComponent
} from './estagio-admin';


describe('EstagioAdminComponent', () => {

  const respostaVazia: EstagiosAdminResponse = {
    data: [],
    total: 0,
    current_page: 1,
    last_page: 1,
    per_page: 15,
    trimestre: null
  };

  const adminService = {
    listarEstagios: vi.fn(() => of(respostaVazia))
  };

  beforeEach(async () => {

    vi.useFakeTimers({ toFake: ['Date'] });

    vi.setSystemTime(new Date(2026, 9, 26));

    await TestBed.configureTestingModule({
      imports: [EstagioAdminComponent],
      providers: [
        {
          provide: AdminService,
          useValue: adminService
        }
      ]
    }).compileComponents();

    adminService.listarEstagios.mockClear();

  });

  afterEach(() => {

    vi.useRealTimers();

  });


  it('começa no trimestre civil atual', () => {

    const fixture = TestBed.createComponent(EstagioAdminComponent);

    const componente = fixture.componentInstance;

    expect(componente.rotuloTrimestre())
      .toBe('4.º trimestre de 2026');

    expect(componente.periodoTrimestre())
      .toBe('01/10/2026 a 31/12/2026');

  });


  it('avança para o trimestre seguinte e recua para o anterior', () => {

    const fixture = TestBed.createComponent(EstagioAdminComponent);

    const componente = fixture.componentInstance;

    componente.trimestreSeguinte();

    expect(componente.rotuloTrimestre())
      .toBe('1.º trimestre de 2027');

    expect(componente.pagina()).toBe(1);

    componente.trimestreAnterior();

    expect(componente.rotuloTrimestre())
      .toBe('4.º trimestre de 2026');

  });

});
