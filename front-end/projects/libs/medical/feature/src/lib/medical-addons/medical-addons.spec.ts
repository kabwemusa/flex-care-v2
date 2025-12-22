import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalAddon } from './medical-addon';

describe('MedicalAddon', () => {
  let component: MedicalAddon;
  let fixture: ComponentFixture<MedicalAddon>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalAddon]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalAddon);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
