import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalSchemeDialog } from './medical-scheme-dialog';

describe('MedicalSchemeDialog', () => {
  let component: MedicalSchemeDialog;
  let fixture: ComponentFixture<MedicalSchemeDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalSchemeDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalSchemeDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
