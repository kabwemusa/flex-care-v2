import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalPlanRateCardConfig } from './medical-plan-rate-card-config';

describe('MedicalPlanRateCardConfig', () => {
  let component: MedicalPlanRateCardConfig;
  let fixture: ComponentFixture<MedicalPlanRateCardConfig>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalPlanRateCardConfig]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalPlanRateCardConfig);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
