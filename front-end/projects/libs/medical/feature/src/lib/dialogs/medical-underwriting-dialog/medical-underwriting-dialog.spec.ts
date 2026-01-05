import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalUnderwritingDialog } from './medical-underwriting-dialog';

describe('MedicalUnderwritingDialog', () => {
  let component: MedicalUnderwritingDialog;
  let fixture: ComponentFixture<MedicalUnderwritingDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalUnderwritingDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalUnderwritingDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
