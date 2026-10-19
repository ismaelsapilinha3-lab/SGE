import { TestBed } from '@angular/core/testing';
import { Supervisor } from './supervisor';

describe('Supervisor', () => {
  let service: Supervisor;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(Supervisor);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
