import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalPlanBenefitsConfig } from './medical-plan-benefits-config';

describe('MedicalPlanBenefitsConfig', () => {
  let component: MedicalPlanBenefitsConfig;
  let fixture: ComponentFixture<MedicalPlanBenefitsConfig>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalPlanBenefitsConfig]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalPlanBenefitsConfig);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
