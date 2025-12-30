import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalPlanDetail } from './medical-plan-detail';

describe('MedicalPlanDetail', () => {
  let component: MedicalPlanDetail;
  let fixture: ComponentFixture<MedicalPlanDetail>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalPlanDetail]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalPlanDetail);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
