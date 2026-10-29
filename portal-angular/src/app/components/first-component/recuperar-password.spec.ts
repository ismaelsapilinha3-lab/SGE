import { HttpErrorResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router } from '@angular/router';
import { of, throwError } from 'rxjs';
import { vi } from 'vitest';

import { AuthService } from '../../core/auth.service';

import { RecuperarPasswordComponent } from './recuperar-password';


describe('RecuperarPasswordComponent', () => {

  const router = {
    navigate: vi.fn()
  };

  const authService = {
    pedirRecuperacao: vi.fn(() => of({
      message: 'Se existir uma conta, enviámos um link.'
    })),
    redefinirPassword: vi.fn(() => of({
      message: 'Palavra-passe redefinida com sucesso.'
    }))
  };

  const route = {
    snapshot: {
      data: {} as Record<string, unknown>,
      queryParamMap: {
        get: vi.fn((_chave: string): string | null => null)
      }
    }
  };

  beforeEach(async () => {

    route.snapshot.data = {};

    route.snapshot.queryParamMap.get.mockImplementation(
      (_chave: string) => null
    );

    await TestBed.configureTestingModule({
      imports: [RecuperarPasswordComponent],
      providers: [
        {
          provide: Router,
          useValue: router
        },
        {
          provide: ActivatedRoute,
          useValue: route
        },
        {
          provide: AuthService,
          useValue: authService
        }
      ]
    }).compileComponents();

    router.navigate.mockClear();
    authService.pedirRecuperacao.mockClear();
    authService.redefinirPassword.mockClear();

  });


  it('pede o link com o e-mail indicado', () => {

    const fixture = TestBed.createComponent(RecuperarPasswordComponent);

    const componente = fixture.componentInstance;

    componente.email = '  teste@example.com  ';

    componente.submeterPedido();

    expect(authService.pedirRecuperacao)
      .toHaveBeenCalledWith('teste@example.com');

    expect(componente.mensagem.tipo).toBe('ok');

  });


  it('rejeita um e-mail inválido sem chamar a API', () => {

    const fixture = TestBed.createComponent(RecuperarPasswordComponent);

    const componente = fixture.componentInstance;

    componente.email = 'sem-arroba';

    componente.submeterPedido();

    expect(authService.pedirRecuperacao).not.toHaveBeenCalled();

    expect(componente.mensagem.tipo).toBe('erro');

  });


  it('lê o token e o e-mail do link de redefinição', () => {

    route.snapshot.data = { modo: 'redefinicao' };

    route.snapshot.queryParamMap.get.mockImplementation(
      (chave: string) => chave === 'token'
        ? 'token-123'
        : 'teste@example.com'
    );

    const fixture = TestBed.createComponent(RecuperarPasswordComponent);

    fixture.detectChanges();

    const componente = fixture.componentInstance;

    expect(componente.modo).toBe('redefinicao');
    expect(componente.token).toBe('token-123');
    expect(componente.email).toBe('teste@example.com');
    expect(componente.linkInvalido).toBe(false);

  });


  it('marca como inválido um link sem token', () => {

    route.snapshot.data = { modo: 'redefinicao' };

    const fixture = TestBed.createComponent(RecuperarPasswordComponent);

    fixture.detectChanges();

    expect(fixture.componentInstance.linkInvalido).toBe(true);

  });


  it('rejeita palavras-passe diferentes', () => {

    route.snapshot.data = { modo: 'redefinicao' };

    route.snapshot.queryParamMap.get.mockImplementation(
      (chave: string) => chave === 'token'
        ? 'token-123'
        : 'teste@example.com'
    );

    const fixture = TestBed.createComponent(RecuperarPasswordComponent);

    fixture.detectChanges();

    const componente = fixture.componentInstance;

    componente.password = 'NovaPalavra123';
    componente.passwordConfirmation = 'OutraPalavra123';

    componente.submeterRedefinicao();

    expect(authService.redefinirPassword).not.toHaveBeenCalled();

    expect(componente.mensagem.tipo).toBe('erro');

  });


  it('redefine a palavra-passe e volta ao login', () => {

    vi.useFakeTimers();

    try {

      route.snapshot.data = { modo: 'redefinicao' };

      route.snapshot.queryParamMap.get.mockImplementation(
        (chave: string) => chave === 'token'
          ? 'token-123'
          : 'teste@example.com'
      );

      const fixture = TestBed.createComponent(RecuperarPasswordComponent);

      fixture.detectChanges();

      const componente = fixture.componentInstance;

      componente.password = 'NovaPalavra123';
      componente.passwordConfirmation = 'NovaPalavra123';

      componente.submeterRedefinicao();

      expect(authService.redefinirPassword).toHaveBeenCalledWith({
        token: 'token-123',
        email: 'teste@example.com',
        password: 'NovaPalavra123',
        password_confirmation: 'NovaPalavra123'
      });

      expect(componente.mensagem.tipo).toBe('ok');

      vi.advanceTimersByTime(1500);

      expect(router.navigate).toHaveBeenCalledWith(['/acesso']);

    } finally {

      vi.useRealTimers();

    }

  });


  it('mostra a mensagem devolvida pela API quando o token expirou', () => {

    route.snapshot.data = { modo: 'redefinicao' };

    route.snapshot.queryParamMap.get.mockImplementation(
      (chave: string) => chave === 'token'
        ? 'token-expirado'
        : 'teste@example.com'
    );

    authService.redefinirPassword.mockReturnValueOnce(
      throwError(() => new HttpErrorResponse({
        status: 422,
        error: {
          message: 'O link de recuperação é inválido ou expirou.'
        }
      }))
    );

    const fixture = TestBed.createComponent(RecuperarPasswordComponent);

    fixture.detectChanges();

    const componente = fixture.componentInstance;

    componente.password = 'NovaPalavra123';
    componente.passwordConfirmation = 'NovaPalavra123';

    componente.submeterRedefinicao();

    expect(componente.mensagem.tipo).toBe('erro');

    expect(componente.mensagem.texto)
      .toBe('O link de recuperação é inválido ou expirou.');

  });

});
