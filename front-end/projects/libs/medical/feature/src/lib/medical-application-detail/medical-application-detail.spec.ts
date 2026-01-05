import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalApplicationDetail } from './medical-application-detail';

describe('MedicalApplicationDetail', () => {
  let component: MedicalApplicationDetail;
  let fixture: ComponentFixture<MedicalApplicationDetail>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalApplicationDetail]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalApplicationDetail);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
