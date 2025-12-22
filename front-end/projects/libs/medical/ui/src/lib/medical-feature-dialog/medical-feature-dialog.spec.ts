import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalFeatureDialog } from './medical-feature-dialog';

describe('MedicalFeatureDialog', () => {
  let component: MedicalFeatureDialog;
  let fixture: ComponentFixture<MedicalFeatureDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalFeatureDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalFeatureDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
