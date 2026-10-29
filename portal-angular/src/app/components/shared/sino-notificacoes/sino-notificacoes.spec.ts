import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';

import {
  Notificacao,
  NotificacaoService
} from '../../../services/notificacao.service';

import { SinoNotificacoesComponent } from './sino-notificacoes';


describe('SinoNotificacoesComponent', () => {

  const router = {
    navigateByUrl: vi.fn()
  };

  const notificacaoService = {
    listar: vi.fn(() => of({
      notificacoes: [],
      resumo: {
        nao_lidas: 0,
        total: 0
      }
    }))
  };

  beforeEach(async () => {

    await TestBed.configureTestingModule({
      imports: [SinoNotificacoesComponent],
      providers: [
        {
          provide: Router,
          useValue: router
        },
        {
          provide: NotificacaoService,
          useValue: notificacaoService
        }
      ]
    }).compileComponents();

    router.navigateByUrl.mockClear();

  });


  it('abre o destino associado à notificação', () => {

    const fixture = TestBed.createComponent(
      SinoNotificacoesComponent
    );

    const componente = fixture.componentInstance;

    const notificacao: Notificacao = {
      id_notificacao: 1,
      id_utilizador: 3,
      id_candidatura: null,
      canal: 'sistema',
      titulo: 'Plano de estágio submetido',
      mensagem: 'O supervisor submeteu o plano do estágio #1.',
      url: '/coordenador?estagio=1&aba=plano',
      enviada_em: null,
      lida_em: new Date().toISOString()
    };

    componente.abrirDestino(notificacao);

    expect(router.navigateByUrl).toHaveBeenCalledWith(
      '/coordenador?estagio=1&aba=plano'
    );

    expect(componente.aberto()).toBe(false);

  });

});
