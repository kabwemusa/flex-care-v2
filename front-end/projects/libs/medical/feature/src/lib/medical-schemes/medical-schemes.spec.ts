import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalSchemes } from './medical-schemes';

describe('MedicalSchemes', () => {
  let component: MedicalSchemes;
  let fixture: ComponentFixture<MedicalSchemes>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalSchemes]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalSchemes);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
