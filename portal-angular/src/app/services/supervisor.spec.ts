import { provideHttpClient } from '@angular/common/http';
import {
  provideHttpClientTesting
} from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';

import { AuthService } from '../core/auth.service';
import { SupervisorService } from './supervisor';


describe('SupervisorService', () => {

  let service: SupervisorService;

  beforeEach(() => {

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        {
          provide: AuthService,
          useValue: {
            token: () => 'token-de-teste'
          }
        }
      ]
    });

    service = TestBed.inject(SupervisorService);

  });

  it('should be created', () => {

    expect(service).toBeTruthy();

  });

});
