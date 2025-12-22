import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CoreConfig } from './core-config';

describe('CoreConfig', () => {
  let component: CoreConfig;
  let fixture: ComponentFixture<CoreConfig>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CoreConfig]
    })
    .compileComponents();

    fixture = TestBed.createComponent(CoreConfig);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
