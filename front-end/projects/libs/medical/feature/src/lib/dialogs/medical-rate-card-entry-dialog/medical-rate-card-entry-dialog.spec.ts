import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalRateCardEntryDialog } from './medical-rate-card-entry-dialog';

describe('MedicalRateCardEntryDialog', () => {
  let component: MedicalRateCardEntryDialog;
  let fixture: ComponentFixture<MedicalRateCardEntryDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalRateCardEntryDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalRateCardEntryDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
