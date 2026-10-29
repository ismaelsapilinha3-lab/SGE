import { ComponentFixture, TestBed } from '@angular/core/testing';
import {
  ActivatedRoute,
  ParamMap,
  convertToParamMap
} from '@angular/router';
import { Subject } from 'rxjs';
import { vi } from 'vitest';

import { PainelAdmin } from './painel-admin';


describe('PainelAdmin', () => {

  let component: PainelAdmin;

  let fixture: ComponentFixture<PainelAdmin>;

  let parametros: Subject<ParamMap>;

  beforeEach(async () => {

    parametros = new Subject<ParamMap>();

    await TestBed.configureTestingModule({
      imports: [PainelAdmin],
      providers: [
        {
          provide: ActivatedRoute,
          useValue: {
            queryParamMap: parametros.asObservable()
          }
        }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(PainelAdmin);

    component = fixture.componentInstance;

    await fixture.whenStable();

  });


  it('should create', () => {

    expect(component).toBeTruthy();

  });


  it('abre a secção pedida pelo destino da notificação', () => {

    const irParaSecao = vi
      .spyOn(PainelAdmin.prototype, 'irParaSecao')
      .mockImplementation(() => undefined);

    parametros.next(
      convertToParamMap({ secao: 'relatorios' })
    );

    expect(irParaSecao).toHaveBeenCalledWith('relatorios');

    irParaSecao.mockRestore();

  });


  it('traduz e classifica o resultado da entrevista', () => {

    expect(component.nomeResultadoEntrevista('aprovado'))
      .toBe('Aprovada');

    expect(component.nomeResultadoEntrevista('reprovado'))
      .toBe('Reprovada');

    expect(component.nomeResultadoEntrevista('pendente'))
      .toBe('Pendente');

    expect(component.classeResultadoEntrevista('aprovado'))
      .toBe('estado-aprovada');

    expect(component.classeResultadoEntrevista('reprovado'))
      .toBe('estado-recusada');

    expect(component.classeResultadoEntrevista('pendente'))
      .toBe('estado-em-analise');

  });

});
