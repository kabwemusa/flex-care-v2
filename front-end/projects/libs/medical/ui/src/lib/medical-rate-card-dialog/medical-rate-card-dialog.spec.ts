import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalRateCardDialog } from './medical-rate-card-dialog';

describe('MedicalRateCardDialog', () => {
  let component: MedicalRateCardDialog;
  let fixture: ComponentFixture<MedicalRateCardDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalRateCardDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalRateCardDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
