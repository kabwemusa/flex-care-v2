import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalAddonCatalog } from './medical-addon-catalog';

describe('MedicalAddonCatalog', () => {
  let component: MedicalAddonCatalog;
  let fixture: ComponentFixture<MedicalAddonCatalog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalAddonCatalog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalAddonCatalog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
