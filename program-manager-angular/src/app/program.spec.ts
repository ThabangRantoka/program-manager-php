import { TestBed } from '@angular/core/testing';
import { ProgramService } from './program';

describe('Program', () => {
  let service: ProgramService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(ProgramService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
