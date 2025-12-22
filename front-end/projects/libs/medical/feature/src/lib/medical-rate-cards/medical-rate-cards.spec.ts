import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalRateCards } from './medical-rate-cards';

describe('MedicalRateCard', () => {
  let component: MedicalRateCards;
  let fixture: ComponentFixture<MedicalRateCards>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalRateCards],
    }).compileComponents();

    fixture = TestBed.createComponent(MedicalRateCards);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
