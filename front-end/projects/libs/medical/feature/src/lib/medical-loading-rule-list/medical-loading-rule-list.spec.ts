import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalLoadingRuleList } from './medical-loading-rule-list';

describe('MedicalLoadingRuleList', () => {
  let component: MedicalLoadingRuleList;
  let fixture: ComponentFixture<MedicalLoadingRuleList>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalLoadingRuleList]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalLoadingRuleList);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
