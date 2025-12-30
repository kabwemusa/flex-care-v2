import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalRateCardsListDialog } from './medical-rate-cards-list-dialog';

describe('MedicalRateCardsListDialog', () => {
  let component: MedicalRateCardsListDialog;
  let fixture: ComponentFixture<MedicalRateCardsListDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalRateCardsListDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalRateCardsListDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
