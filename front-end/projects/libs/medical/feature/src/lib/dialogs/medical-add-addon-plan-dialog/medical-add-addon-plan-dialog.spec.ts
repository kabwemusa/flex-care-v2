import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalAddAddonPlanDialog } from './medical-add-addon-plan-dialog';

describe('MedicalAddAddonPlanDialog', () => {
  let component: MedicalAddAddonPlanDialog;
  let fixture: ComponentFixture<MedicalAddAddonPlanDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalAddAddonPlanDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalAddAddonPlanDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
