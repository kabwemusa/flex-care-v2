import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalDiscounts } from './medical-discounts';

describe('MedicalDiscounts', () => {
  let component: MedicalDiscounts;
  let fixture: ComponentFixture<MedicalDiscounts>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalDiscounts]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalDiscounts);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
