import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalPolicyDialog } from './medical-policy-dialog';

describe('MedicalPolicyDialog', () => {
  let component: MedicalPolicyDialog;
  let fixture: ComponentFixture<MedicalPolicyDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalPolicyDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalPolicyDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
