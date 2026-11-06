import { ComponentFixture, TestBed } from '@angular/core/testing';
import { AddProgram } from './add-program';

describe('AddProgram', () => {
  let component: AddProgram;
  let fixture: ComponentFixture<AddProgram>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AddProgram],
    }).compileComponents();

    fixture = TestBed.createComponent(AddProgram);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
