import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalPlanAddonConfig } from './medical-plan-addon-config';

describe('MedicalPlanAddonConfig', () => {
  let component: MedicalPlanAddonConfig;
  let fixture: ComponentFixture<MedicalPlanAddonConfig>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalPlanAddonConfig]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalPlanAddonConfig);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
