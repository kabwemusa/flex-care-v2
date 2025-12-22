import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalAddonDialog } from './medical-addon-dialog';

describe('MedicalAddonDialog', () => {
  let component: MedicalAddonDialog;
  let fixture: ComponentFixture<MedicalAddonDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalAddonDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalAddonDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
