import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalBenefitsCategoryDialog } from './medical-benefits-category-dialog';

describe('MedicalBenefitsCategoryDialog', () => {
  let component: MedicalBenefitsCategoryDialog;
  let fixture: ComponentFixture<MedicalBenefitsCategoryDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalBenefitsCategoryDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalBenefitsCategoryDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
