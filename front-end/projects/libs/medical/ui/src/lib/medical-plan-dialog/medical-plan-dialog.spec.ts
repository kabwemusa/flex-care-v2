import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalPlanDialog } from './medical-plan-dialog';

describe('MedicalPlanDialog', () => {
  let component: MedicalPlanDialog;
  let fixture: ComponentFixture<MedicalPlanDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalPlanDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalPlanDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
