import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalPoliciesList } from './medical-policies-list';

describe('MedicalPoliciesList', () => {
  let component: MedicalPoliciesList;
  let fixture: ComponentFixture<MedicalPoliciesList>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalPoliciesList]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalPoliciesList);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
