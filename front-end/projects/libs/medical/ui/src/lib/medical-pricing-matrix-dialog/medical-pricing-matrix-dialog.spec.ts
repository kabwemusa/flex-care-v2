import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalPricingMatrixDialog } from './medical-pricing-matrix-dialog';

describe('MedicalPricingMatrixDialog', () => {
  let component: MedicalPricingMatrixDialog;
  let fixture: ComponentFixture<MedicalPricingMatrixDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalPricingMatrixDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalPricingMatrixDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
