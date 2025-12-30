import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalMembersList } from './medical-members-list';

describe('MedicalMembersList', () => {
  let component: MedicalMembersList;
  let fixture: ComponentFixture<MedicalMembersList>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalMembersList]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalMembersList);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
