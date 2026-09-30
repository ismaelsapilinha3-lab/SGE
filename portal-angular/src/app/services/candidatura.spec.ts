import { TestBed } from '@angular/core/testing';
import { Candidatura } from './candidatura';

describe('Candidatura', () => {
  let service: Candidatura;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(Candidatura);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
