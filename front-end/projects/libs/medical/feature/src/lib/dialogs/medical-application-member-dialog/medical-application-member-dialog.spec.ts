import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalApplicationMemberDialog } from './medical-application-member-dialog';

describe('MedicalApplicationMemberDialog', () => {
  let component: MedicalApplicationMemberDialog;
  let fixture: ComponentFixture<MedicalApplicationMemberDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalApplicationMemberDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalApplicationMemberDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
