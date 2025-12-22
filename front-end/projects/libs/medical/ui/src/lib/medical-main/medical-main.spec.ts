import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalMain } from './medical-main';

describe('MedicalMain', () => {
  let component: MedicalMain;
  let fixture: ComponentFixture<MedicalMain>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalMain]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalMain);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
