import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalSchemesList } from './medical-scheme-list';

describe('MedicalSchemeList', () => {
  let component: MedicalSchemesList;
  let fixture: ComponentFixture<MedicalSchemesList>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalSchemesList],
    }).compileComponents();

    fixture = TestBed.createComponent(MedicalSchemesList);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
