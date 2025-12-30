import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalBenefitsCatalog } from './medical-benefits-catalog';

describe('MedicalBenefitsCatalog', () => {
  let component: MedicalBenefitsCatalog;
  let fixture: ComponentFixture<MedicalBenefitsCatalog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalBenefitsCatalog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalBenefitsCatalog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
