import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalPlanAddonsDialog } from './medical-plan-addons-dialog';

describe('MedicalPlanAddonsDialog', () => {
  let component: MedicalPlanAddonsDialog;
  let fixture: ComponentFixture<MedicalPlanAddonsDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalPlanAddonsDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalPlanAddonsDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
