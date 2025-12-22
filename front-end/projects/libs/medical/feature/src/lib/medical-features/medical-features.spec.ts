import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalFeatures } from './medical-features';

describe('MedicalFeatures', () => {
  let component: MedicalFeatures;
  let fixture: ComponentFixture<MedicalFeatures>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalFeatures]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalFeatures);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
