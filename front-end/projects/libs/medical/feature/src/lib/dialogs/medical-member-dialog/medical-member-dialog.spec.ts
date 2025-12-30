import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalMemberDialog } from './medical-member-dialog';

describe('MedicalMemberDialog', () => {
  let component: MedicalMemberDialog;
  let fixture: ComponentFixture<MedicalMemberDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalMemberDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalMemberDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
