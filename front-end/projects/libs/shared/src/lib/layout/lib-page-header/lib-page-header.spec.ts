import { ComponentFixture, TestBed } from '@angular/core/testing';

import { LibPageHeader } from './lib-page-header';

describe('LibPageHeader', () => {
  let component: LibPageHeader;
  let fixture: ComponentFixture<LibPageHeader>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [LibPageHeader]
    })
    .compileComponents();

    fixture = TestBed.createComponent(LibPageHeader);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
