import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalGroupsList } from './medical-groups-list';

describe('MedicalGroupsList', () => {
  let component: MedicalGroupsList;
  let fixture: ComponentFixture<MedicalGroupsList>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalGroupsList]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalGroupsList);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
