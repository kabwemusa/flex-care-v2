import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalRateCardsList } from './medical-rate-cards-list';

describe('MedicalRateCardsList', () => {
  let component: MedicalRateCardsList;
  let fixture: ComponentFixture<MedicalRateCardsList>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalRateCardsList]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalRateCardsList);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
