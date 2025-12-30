import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalLoadingRuleDialog } from './medical-loading-rule-dialog';

describe('MedicalLoadingRuleDialog', () => {
  let component: MedicalLoadingRuleDialog;
  let fixture: ComponentFixture<MedicalLoadingRuleDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalLoadingRuleDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalLoadingRuleDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
