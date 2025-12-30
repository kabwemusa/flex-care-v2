import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalRateCardBulkDialog } from './medical-rate-card-bulk-dialog';

describe('MedicalRateCardBulkDialog', () => {
  let component: MedicalRateCardBulkDialog;
  let fixture: ComponentFixture<MedicalRateCardBulkDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalRateCardBulkDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalRateCardBulkDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
