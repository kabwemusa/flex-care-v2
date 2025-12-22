import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalBenefitMatrixDialog } from './medical-benefit-matrix-dialog';

describe('MedicalBenefitMatrixDialog', () => {
  let component: MedicalBenefitMatrixDialog;
  let fixture: ComponentFixture<MedicalBenefitMatrixDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalBenefitMatrixDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalBenefitMatrixDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
