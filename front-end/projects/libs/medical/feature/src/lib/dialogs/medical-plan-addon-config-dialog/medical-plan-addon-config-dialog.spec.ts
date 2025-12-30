import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalPlanAddonConfigDialog } from './medical-plan-addon-config-dialog';

describe('MedicalPlanAddonConfigDialog', () => {
  let component: MedicalPlanAddonConfigDialog;
  let fixture: ComponentFixture<MedicalPlanAddonConfigDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalPlanAddonConfigDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalPlanAddonConfigDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
