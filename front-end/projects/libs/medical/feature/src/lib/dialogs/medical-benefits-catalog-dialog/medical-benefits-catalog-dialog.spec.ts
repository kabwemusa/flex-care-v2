import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalBenefitsCatalogDialog } from './medical-benefits-catalog-dialog';

describe('MedicalBenefitsCatalogDialog', () => {
  let component: MedicalBenefitsCatalogDialog;
  let fixture: ComponentFixture<MedicalBenefitsCatalogDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalBenefitsCatalogDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalBenefitsCatalogDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
