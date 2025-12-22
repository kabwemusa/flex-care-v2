import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalPlans } from './medical-plans';

describe('MedicalPlans', () => {
  let component: MedicalPlans;
  let fixture: ComponentFixture<MedicalPlans>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalPlans]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalPlans);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
