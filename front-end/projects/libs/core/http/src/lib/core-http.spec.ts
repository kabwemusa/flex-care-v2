import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CoreHttp } from './core-http';

describe('CoreHttp', () => {
  let component: CoreHttp;
  let fixture: ComponentFixture<CoreHttp>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CoreHttp]
    })
    .compileComponents();

    fixture = TestBed.createComponent(CoreHttp);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
