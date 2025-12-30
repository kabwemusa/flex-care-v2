import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalPlanList } from './medical-plan-list';

describe('MedicalPlanList', () => {
  let component: MedicalPlanList;
  let fixture: ComponentFixture<MedicalPlanList>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalPlanList]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalPlanList);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
