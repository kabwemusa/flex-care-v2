import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MedicalAddonCatalogDialog } from './medical-addon-catalog-dialog';

describe('MedicalAddonCatalogDialog', () => {
  let component: MedicalAddonCatalogDialog;
  let fixture: ComponentFixture<MedicalAddonCatalogDialog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MedicalAddonCatalogDialog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(MedicalAddonCatalogDialog);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
