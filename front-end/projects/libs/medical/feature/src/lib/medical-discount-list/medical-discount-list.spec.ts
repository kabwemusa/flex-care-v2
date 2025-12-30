import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalDiscountList } from './medical-discount-list';

describe('MedicalDiscountList', () => {
  let component: MedicalDiscountList;
  let fixture: ComponentFixture<MedicalDiscountList>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalDiscountList]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalDiscountList);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
