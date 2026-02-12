import { Component } from '@angular/core';
import { RouterModule } from '@angular/router';

@Component({
  selector: 'app-provider-layout',
  standalone: true,
  imports: [RouterModule],
  template: `
    <!-- Provider Portal Shell — sidebar nav expanded in Step 5 -->
    <div class="flex min-h-screen bg-gray-50">

      <!-- Sidebar -->
      <aside class="w-60 bg-gray-900 text-white flex-shrink-0 flex flex-col">
        <div class="p-6 border-b border-gray-700">
          <span class="text-lg font-semibold text-cyan-400">FlexCare</span>
          <p class="text-xs text-gray-400 mt-0.5">Provider Portal</p>
        </div>

        <nav class="flex-1 p-4 flex flex-col gap-1">
          <a
            routerLink="/provider"
            routerLinkActive="bg-gray-700 text-white"
            [routerLinkActiveOptions]="{ exact: true }"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors"
          >
            <span class="material-symbols-rounded" style="font-size: 20px">dashboard</span>
            Dashboard
          </a>
          <a
            routerLink="/provider/eligibility"
            routerLinkActive="bg-gray-700 text-white"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors"
          >
            <span class="material-symbols-rounded" style="font-size: 20px">verified</span>
            Eligibility
          </a>
          <a
            routerLink="/provider/preauth"
            routerLinkActive="bg-gray-700 text-white"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors"
          >
            <span class="material-symbols-rounded" style="font-size: 20px">approval</span>
            Pre-Auth
          </a>
          <a
            routerLink="/provider/claims"
            routerLinkActive="bg-gray-700 text-white"
            class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors"
          >
            <span class="material-symbols-rounded" style="font-size: 20px">receipt_long</span>
            Claims
          </a>
        </nav>

        <!-- Sign out placeholder — wired in Step 5 -->
        <div class="p-4 border-t border-gray-700">
          <a href="#" class="flex items-center gap-3 px-3 py-2.5 text-sm text-gray-400 hover:text-white transition-colors">
            <span class="material-symbols-rounded" style="font-size: 20px">logout</span>
            Sign Out
          </a>
        </div>
      </aside>

      <!-- Main Content -->
      <main class="flex-1 min-h-screen overflow-auto">
        <router-outlet></router-outlet>
      </main>
    </div>
  `,
})
export class ProviderLayout {}
