import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalRateCardTierDialog } from './medical-rate-card-tier-dialog';

describe('MedicalRateCardTierDialog', () => {
  let component: MedicalRateCardTierDialog;
  let fixture: ComponentFixture<MedicalRateCardTierDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalRateCardTierDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalRateCardTierDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
