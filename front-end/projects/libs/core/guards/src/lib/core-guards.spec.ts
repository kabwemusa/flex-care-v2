import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CoreGuards } from './core-guards';

describe('CoreGuards', () => {
  let component: CoreGuards;
  let fixture: ComponentFixture<CoreGuards>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CoreGuards]
    })
    .compileComponents();

    fixture = TestBed.createComponent(CoreGuards);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
