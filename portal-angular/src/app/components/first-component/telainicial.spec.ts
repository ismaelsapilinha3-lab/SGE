import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';

import { CandidaturaService } from '../../services/candidatura.service';

import { TelainicialComponent } from './telainicial';


describe('TelainicialComponent', () => {

  const router = {
    navigate: vi.fn()
  };

  const candidaturaService = {
    listarDepartamentos: vi.fn(() => of([]))
  };

  const scrollIntoView = vi.fn();

  beforeEach(async () => {

    Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', {
      configurable: true,
      writable: true,
      value: scrollIntoView
    });

    await TestBed.configureTestingModule({
      imports: [TelainicialComponent],
      providers: [
        {
          provide: Router,
          useValue: router
        },
        {
          provide: CandidaturaService,
          useValue: candidaturaService
        }
      ]
    }).compileComponents();

    router.navigate.mockClear();

    scrollIntoView.mockClear();

  });


  afterEach(() => {

    delete (HTMLElement.prototype as { scrollIntoView?: unknown })
      .scrollIntoView;

  });


  it('faz scroll para a secção indicada e fecha o menu', () => {

    const fixture = TestBed.createComponent(TelainicialComponent);

    const componente = fixture.componentInstance;

    const alvo = document.createElement('section');

    alvo.id = 'como-funciona';

    document.body.appendChild(alvo);

    const evento = {
      preventDefault: vi.fn()
    } as unknown as Event;

    componente.menuAberto = true;

    componente.irParaSeccao('como-funciona', evento);

    expect(evento.preventDefault).toHaveBeenCalled();

    expect(scrollIntoView).toHaveBeenCalledWith({
      behavior: 'smooth',
      block: 'start'
    });

    expect(componente.menuAberto).toBe(false);

    alvo.remove();

  });


  it('mostra a galeria e abre a fotografia ao clicar no cartão', () => {

    const fixture = TestBed.createComponent(TelainicialComponent);

    fixture.detectChanges();

    const cartoes = fixture.nativeElement
      .querySelectorAll('.gallery-card');

    expect(cartoes.length).toBe(6);

    cartoes[0].click();

    fixture.detectChanges();

    expect(
      fixture.nativeElement.querySelector('.gallery-lightbox')
    ).not.toBeNull();

  });


  it('abre a fotografia ampliada e navega entre fotografias', () => {

    const fixture = TestBed.createComponent(TelainicialComponent);

    const componente = fixture.componentInstance;

    componente.abrirGaleria(0);

    expect(componente.fotoAmpliada)
      .toBe(componente.fotosGaleria[0]);

    componente.fotoSeguinte();

    expect(componente.fotoAmpliada)
      .toBe(componente.fotosGaleria[1]);

    componente.fotoAnterior();

    expect(componente.fotoAmpliada)
      .toBe(componente.fotosGaleria[0]);

  });


  it('ignora as fotografias ainda não disponíveis', () => {

    const fixture = TestBed.createComponent(TelainicialComponent);

    const componente = fixture.componentInstance;

    componente.fotosGaleria[1].indisponivel = true;

    componente.abrirGaleria(1);

    expect(componente.fotoAmpliada).toBeNull();

    componente.abrirGaleria(0);

    componente.fotoSeguinte();

    expect(componente.fotoAmpliada)
      .toBe(componente.fotosGaleria[2]);

  });


  it('fecha a galeria com a tecla Escape', () => {

    const fixture = TestBed.createComponent(TelainicialComponent);

    const componente = fixture.componentInstance;

    componente.abrirGaleria(0);

    componente.aoTeclaGaleria(
      new KeyboardEvent('keydown', { key: 'Escape' })
    );

    expect(componente.fotoAmpliada).toBeNull();

  });


  it('fecha o menu ao abrir o login', () => {

    const fixture = TestBed.createComponent(TelainicialComponent);

    const componente = fixture.componentInstance;

    componente.menuAberto = true;

    componente.abrirLogin();

    expect(componente.menuAberto).toBe(false);

    expect(router.navigate).toHaveBeenCalledWith(['/acesso']);

  });

});
