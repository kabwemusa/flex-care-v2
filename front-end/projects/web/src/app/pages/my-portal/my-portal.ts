import { Component, signal, OnInit } from '@angular/core';
import { RouterModule } from '@angular/router';

@Component({
  selector: 'app-my-portal',
  standalone: true,
  imports: [RouterModule],
  templateUrl: './my-portal.html',
})
export class MyPortalPage implements OnInit {
  isLoggedIn = signal(false);

  ngOnInit() {
    this.isLoggedIn.set(!!localStorage.getItem('flex_token'));
  }

  logout() {
    localStorage.removeItem('flex_token');
    this.isLoggedIn.set(false);
  }
}
