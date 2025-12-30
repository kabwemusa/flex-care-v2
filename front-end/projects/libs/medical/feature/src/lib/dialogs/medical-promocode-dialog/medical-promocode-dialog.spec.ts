import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalPromocodeDialog } from './medical-promocode-dialog';

describe('MedicalPromocodeDialog', () => {
  let component: MedicalPromocodeDialog;
  let fixture: ComponentFixture<MedicalPromocodeDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalPromocodeDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalPromocodeDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
