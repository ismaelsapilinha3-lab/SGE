import { TestBed } from '@angular/core/testing';
import { RelatorioEstagiario } from './relatorio-estagiario';

describe('RelatorioEstagiario', () => {
  let service: RelatorioEstagiario;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(RelatorioEstagiario);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
