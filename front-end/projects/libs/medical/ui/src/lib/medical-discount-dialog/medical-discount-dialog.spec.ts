import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalDiscountDialog } from './medical-discount-dialog';

describe('MedicalDiscountDialog', () => {
  let component: MedicalDiscountDialog;
  let fixture: ComponentFixture<MedicalDiscountDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalDiscountDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalDiscountDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
