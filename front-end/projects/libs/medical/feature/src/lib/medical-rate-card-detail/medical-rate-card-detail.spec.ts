import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalRateCardDetail } from './medical-rate-card-detail';

describe('MedicalRateCardDetail', () => {
  let component: MedicalRateCardDetail;
  let fixture: ComponentFixture<MedicalRateCardDetail>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalRateCardDetail]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalRateCardDetail);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
