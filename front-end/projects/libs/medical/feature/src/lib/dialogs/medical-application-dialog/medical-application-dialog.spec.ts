import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalApplicationDialog } from './medical-application-dialog';

describe('MedicalApplicationDialog', () => {
  let component: MedicalApplicationDialog;
  let fixture: ComponentFixture<MedicalApplicationDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalApplicationDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalApplicationDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
