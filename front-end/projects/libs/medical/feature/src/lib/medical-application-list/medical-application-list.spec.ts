import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalApplicationList } from './medical-application-list';

describe('MedicalApplicationList', () => {
  let component: MedicalApplicationList;
  let fixture: ComponentFixture<MedicalApplicationList>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalApplicationList]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalApplicationList);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
