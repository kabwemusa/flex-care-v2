import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalPlanBenefitsConfigDialog } from './medical-plan-benefits-config-dialog';

describe('MedicalPlanBenefitsConfigDialog', () => {
  let component: MedicalPlanBenefitsConfigDialog;
  let fixture: ComponentFixture<MedicalPlanBenefitsConfigDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalPlanBenefitsConfigDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalPlanBenefitsConfigDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
