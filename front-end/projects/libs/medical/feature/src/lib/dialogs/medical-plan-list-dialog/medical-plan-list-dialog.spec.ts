import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalPlanListDialog } from './medical-plan-list-dialog';

describe('MedicalPlanListDialog', () => {
  let component: MedicalPlanListDialog;
  let fixture: ComponentFixture<MedicalPlanListDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalPlanListDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalPlanListDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
