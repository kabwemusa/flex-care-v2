import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalAddPlanBenefitsDialog } from './medical-add-plan-benefits-dialog';

describe('MedicalAddPlanBenefitsDialog', () => {
  let component: MedicalAddPlanBenefitsDialog;
  let fixture: ComponentFixture<MedicalAddPlanBenefitsDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalAddPlanBenefitsDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalAddPlanBenefitsDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
