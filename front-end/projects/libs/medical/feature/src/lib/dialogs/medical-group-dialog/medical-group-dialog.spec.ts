import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalGroupDialog } from './medical-group-dialog';

describe('MedicalGroupDialog', () => {
  let component: MedicalGroupDialog;
  let fixture: ComponentFixture<MedicalGroupDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalGroupDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalGroupDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
