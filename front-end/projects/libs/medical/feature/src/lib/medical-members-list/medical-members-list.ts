// libs/medical/feature/src/lib/members/medical-members-list.ts
import {
  Component,
  OnInit,
  AfterViewInit,
  ViewChild,
  inject,
  signal,
  computed,
  effect,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';

// Material Imports
import { MatTableDataSource, MatTableModule } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatSort, MatSortModule } from '@angular/material/sort';
import { MatDialog } from '@angular/material/dialog';
import { MatMenuModule } from '@angular/material/menu';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatDividerModule } from '@angular/material/divider';
import { MatSelectModule } from '@angular/material/select';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatDrawer, MatSidenavModule } from '@angular/material/sidenav';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTabsModule } from '@angular/material/tabs';

// Domain Imports
import {
  MemberStore,
  Member,
  MEMBER_TYPES,
  MEMBER_STATUSES,
  CARD_STATUSES,
  GENDERS,
  getLabelByValue,
  getStatusConfig,
  formatCurrency,
  calculateAge,
  WAITING_PERIOD_TYPES,
} from 'medical-data';
import { FeedbackService, PageHeaderComponent } from 'shared';
import { MedicalMemberDialog } from '../dialogs/medical-member-dialog/medical-member-dialog';

@Component({
  selector: 'lib-medical-members-list',
  standalone: true,
  imports: [
    CommonModule,
    // RouterLink,
    FormsModule,
    MatTableModule,
    MatPaginatorModule,
    MatSortModule,
    MatMenuModule,
    MatIconModule,
    MatButtonModule,
    MatTooltipModule,
    MatDividerModule,
    MatSelectModule,
    MatFormFieldModule,
    MatInputModule,
    MatSidenavModule,
    MatChipsModule,
    MatProgressSpinnerModule,
    MatTabsModule,
    // PageHeaderComponent,
  ],
  templateUrl: './medical-members-list.html',
  // template:`libs/medical/ui/src/lib/members/member-list.component.html

  // <mat-drawer-container class="h-full">
  //   <mat-drawer-content>
  //     <div class="bg-slate-50 min-h-screen p-6 md:p-10">
  //       <!-- Header -->
  //       <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
  //         <div>
  //           <h1 class="text-2xl font-bold text-slate-900">Member Registry</h1>
  //           <p class="text-sm text-slate-500">Manage insured members, cards and eligibility</p>
  //         </div>
  //         <div class="flex gap-2">
  //           <button mat-stroked-button (click)="exportToCsv()" class="!border-slate-200">
  //             <mat-icon fontSet="material-symbols-rounded">download</mat-icon>
  //             Export
  //           </button>
  //           <button mat-flat-button color="primary" (click)="openDialog()">
  //             <mat-icon fontSet="material-symbols-rounded">person_add</mat-icon>
  //             Add Member
  //           </button>
  //         </div>
  //       </div>

  //       <!-- KPI Cards -->
  //       <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
  //         <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
  //           <div class="flex items-center justify-between">
  //             <div>
  //               <p class="text-sm text-slate-500">Total</p>
  //               <p class="text-2xl font-semibold text-slate-900">{{ members().length }}</p>
  //             </div>
  //             <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100">
  //               <mat-icon fontSet="material-symbols-rounded" class="!text-slate-600">groups</mat-icon>
  //             </div>
  //           </div>
  //         </div>

  //         <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
  //           <div class="flex items-center justify-between">
  //             <div>
  //               <p class="text-sm text-slate-500">Active</p>
  //               <p class="text-2xl font-semibold text-green-600">{{ activeMembers().length }}</p>
  //             </div>
  //             <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100">
  //               <mat-icon fontSet="material-symbols-rounded" class="!text-green-600"
  //                 >check_circle</mat-icon
  //               >
  //             </div>
  //           </div>
  //         </div>

  //         <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
  //           <div class="flex items-center justify-between">
  //             <div>
  //               <p class="text-sm text-slate-500">Principals</p>
  //               <p class="text-2xl font-semibold text-blue-600">{{ principalMembers().length }}</p>
  //             </div>
  //             <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100">
  //               <mat-icon fontSet="material-symbols-rounded" class="!text-blue-600">person</mat-icon>
  //             </div>
  //           </div>
  //         </div>

  //         <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
  //           <div class="flex items-center justify-between">
  //             <div>
  //               <p class="text-sm text-slate-500">Dependents</p>
  //               <p class="text-2xl font-semibold text-amber-600">{{ dependentMembers().length }}</p>
  //             </div>
  //             <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100">
  //               <mat-icon fontSet="material-symbols-rounded" class="!text-amber-600"
  //                 >family_restroom</mat-icon
  //               >
  //             </div>
  //           </div>
  //         </div>

  //         <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
  //           <div class="flex items-center justify-between">
  //             <div>
  //               <p class="text-sm text-slate-500">With Loadings</p>
  //               <p class="text-2xl font-semibold text-red-600">{{ membersWithLoadings().length }}</p>
  //             </div>
  //             <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-100">
  //               <mat-icon fontSet="material-symbols-rounded" class="!text-red-600">warning</mat-icon>
  //             </div>
  //           </div>
  //         </div>
  //       </div>

  //       <!-- Filters -->
  //       <div class="rounded-xl border border-slate-200 bg-white p-4 mb-6 shadow-sm">
  //         <div class="flex flex-wrap gap-4 items-end">
  //           <mat-form-field
  //             appearance="outline"
  //             subscriptSizing="dynamic"
  //             class="flex-1 min-w-[280px]"
  //           >
  //             <mat-icon fontSet="material-symbols-rounded" matPrefix class="!text-slate-400"
  //               >search</mat-icon
  //             >
  //             <input
  //               matInput
  //               [ngModel]="searchTerm()"
  //               (ngModelChange)="onSearchChange($event)"
  //               placeholder="Search by member #, name, ID..."
  //             />
  //             @if (searchTerm()) {
  //             <button matSuffix mat-icon-button (click)="searchTerm.set(''); applyFilter()">
  //               <mat-icon fontSet="material-symbols-rounded">close</mat-icon>
  //             </button>
  //             }
  //           </mat-form-field>

  //           <mat-form-field appearance="outline" subscriptSizing="dynamic" class="w-40">
  //             <mat-select
  //               [ngModel]="statusFilter()"
  //               (ngModelChange)="onStatusChange($event)"
  //               placeholder="Status"
  //             >
  //               <mat-option value="">All Statuses</mat-option>
  //               @for (status of MEMBER_STATUSES; track status.value) {
  //               <mat-option [value]="status.value">{{ status.label }}</mat-option>
  //               }
  //             </mat-select>
  //           </mat-form-field>

  //           <mat-form-field appearance="outline" subscriptSizing="dynamic" class="w-40">
  //             <mat-select
  //               [ngModel]="typeFilter()"
  //               (ngModelChange)="onTypeChange($event)"
  //               placeholder="Type"
  //             >
  //               <mat-option value="">All Types</mat-option>
  //               @for (type of MEMBER_TYPES; track type.value) {
  //               <mat-option [value]="type.value">{{ type.label }}</mat-option>
  //               }
  //             </mat-select>
  //           </mat-form-field>

  //           @if (hasActiveFilters()) {
  //           <button mat-stroked-button (click)="clearFilters()" class="!border-slate-200">
  //             <mat-icon fontSet="material-symbols-rounded">filter_alt_off</mat-icon>
  //             Clear
  //           </button>
  //           }
  //         </div>
  //       </div>

  //       <!-- Table -->
  //       <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
  //         @if (isLoading()) {
  //         <div class="flex items-center justify-center h-64">
  //           <mat-spinner diameter="40"></mat-spinner>
  //         </div>
  //         } @else {
  //         <table mat-table [dataSource]="dataSource" matSort class="w-full">
  //           <!-- Status -->
  //           <ng-container matColumnDef="status">
  //             <th mat-header-cell *matHeaderCellDef mat-sort-header class="!pl-6 !w-28">Status</th>
  //             <td mat-cell *matCellDef="let row" class="!pl-6">
  //               <span
  //                 class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium"
  //                 [class]="getStatusClasses(row.status)"
  //               >
  //                 <span class="h-1.5 w-1.5 rounded-full" [class]="getStatusDot(row.status)"></span>
  //                 {{ getLabel(MEMBER_STATUSES, row.status) }}
  //               </span>
  //             </td>
  //           </ng-container>

  //           <!-- Member # -->
  //           <ng-container matColumnDef="member_number">
  //             <th mat-header-cell *matHeaderCellDef mat-sort-header>Member #</th>
  //             <td mat-cell *matCellDef="let row">
  //               <button
  //                 class="font-mono text-sm text-slate-700 hover:text-blue-600"
  //                 (click)="viewDetails(row); $event.stopPropagation()"
  //               >
  //                 {{ row.member_number }}
  //               </button>
  //             </td>
  //           </ng-container>

  //           <!-- Name -->
  //           <ng-container matColumnDef="name">
  //             <th mat-header-cell *matHeaderCellDef mat-sort-header>Name</th>
  //             <td mat-cell *matCellDef="let row">
  //               <div class="font-medium text-slate-900">
  //                 {{ row.full_name || row.first_name + ' ' + row.last_name }}
  //               </div>
  //               @if (row.national_id) {
  //               <div class="text-xs text-slate-500">{{ row.national_id }}</div>
  //               }
  //             </td>
  //           </ng-container>

  //           <!-- Type -->
  //           <ng-container matColumnDef="type">
  //             <th mat-header-cell *matHeaderCellDef mat-sort-header class="!w-32">Type</th>
  //             <td mat-cell *matCellDef="let row">
  //               <span class="flex items-center gap-1.5 text-sm text-slate-600">
  //                 <mat-icon fontSet="material-symbols-rounded" class="!text-slate-400 !text-[18px]">
  //                   {{ getMemberTypeIcon(row.member_type) }}
  //                 </mat-icon>
  //                 {{ getLabel(MEMBER_TYPES, row.member_type) }}
  //               </span>
  //             </td>
  //           </ng-container>

  //           <!-- Policy -->
  //           <ng-container matColumnDef="policy">
  //             <th mat-header-cell *matHeaderCellDef mat-sort-header>Policy</th>
  //             <td mat-cell *matCellDef="let row">
  //               @if (row.policy_id) {
  //               <span class="font-mono text-sm text-slate-700">{{ row.policy_id }}</span>
  //               } @else {
  //               <span class="text-slate-400">-</span>
  //               }
  //             </td>
  //           </ng-container>

  //           <!-- Age -->
  //           <ng-container matColumnDef="age">
  //             <th mat-header-cell *matHeaderCellDef mat-sort-header class="!text-center !w-20">
  //               Age
  //             </th>
  //             <td mat-cell *matCellDef="let row" class="!text-center text-slate-600">
  //               {{ row.age || calculateAge(row.date_of_birth) }}
  //             </td>
  //           </ng-container>

  //           <!-- Card -->
  //           <ng-container matColumnDef="card">
  //             <th mat-header-cell *matHeaderCellDef mat-sort-header class="!w-28">Card</th>
  //             <td mat-cell *matCellDef="let row">
  //               <span class="inline-flex items-center gap-1.5 text-sm">
  //                 <span
  //                   class="h-1.5 w-1.5 rounded-full"
  //                   [class]="getCardStatusDot(row.card_status)"
  //                 ></span>
  //                 {{ getLabel(CARD_STATUSES, row.card_status) || 'Pending' }}
  //               </span>
  //             </td>
  //           </ng-container>

  //           <!-- Actions -->
  //           <ng-container matColumnDef="actions">
  //             <th mat-header-cell *matHeaderCellDef class="!w-16 !pr-4"></th>
  //             <td mat-cell *matCellDef="let row" class="!pr-4">
  //               <button
  //                 mat-icon-button
  //                 [matMenuTriggerFor]="rowMenu"
  //                 (click)="$event.stopPropagation()"
  //               >
  //                 <mat-icon fontSet="material-symbols-rounded">more_vert</mat-icon>
  //               </button>
  //               <mat-menu #rowMenu="matMenu">
  //                 <button mat-menu-item (click)="viewDetails(row)">
  //                   <mat-icon fontSet="material-symbols-rounded">visibility</mat-icon>
  //                   <span>View Details</span>
  //                 </button>
  //                 <button mat-menu-item (click)="openDialog(row)">
  //                   <mat-icon fontSet="material-symbols-rounded">edit</mat-icon>
  //                   <span>Edit</span>
  //                 </button>
  //                 <mat-divider></mat-divider>

  //                 <!-- Card Actions -->
  //                 @if (!row.card_status || row.card_status === 'pending') {
  //                 <button mat-menu-item (click)="issueCard(row, $event)">
  //                   <mat-icon fontSet="material-symbols-rounded" class="!text-blue-500"
  //                     >add_card</mat-icon
  //                   >
  //                   <span>Issue Card</span>
  //                 </button>
  //                 } @if (row.card_status === 'issued') {
  //                 <button mat-menu-item (click)="activateCard(row, $event)">
  //                   <mat-icon fontSet="material-symbols-rounded" class="!text-green-500"
  //                     >check_circle</mat-icon
  //                   >
  //                   <span>Activate Card</span>
  //                 </button>
  //                 } @if (row.card_status === 'active') {
  //                 <button mat-menu-item (click)="blockCard(row, $event)">
  //                   <mat-icon fontSet="material-symbols-rounded" class="!text-amber-500"
  //                     >block</mat-icon
  //                   >
  //                   <span>Block Card</span>
  //                 </button>
  //                 }
  //                 <mat-divider></mat-divider>

  //                 <!-- Status Actions -->
  //                 @if (row.status === 'suspended') {
  //                 <button mat-menu-item (click)="activateMember(row, $event)">
  //                   <mat-icon fontSet="material-symbols-rounded" class="!text-green-500"
  //                     >play_arrow</mat-icon
  //                   >
  //                   <span>Activate</span>
  //                 </button>
  //                 } @if (row.status === 'active') {
  //                 <button mat-menu-item (click)="suspendMember(row, $event)">
  //                   <mat-icon fontSet="material-symbols-rounded" class="!text-amber-500"
  //                     >pause</mat-icon
  //                   >
  //                   <span>Suspend</span>
  //                 </button>
  //                 }
  //                 <button mat-menu-item (click)="terminateMember(row, $event)" class="!text-red-600">
  //                   <mat-icon fontSet="material-symbols-rounded" class="!text-red-600"
  //                     >person_off</mat-icon
  //                   >
  //                   <span>Terminate</span>
  //                 </button>
  //               </mat-menu>
  //             </td>
  //           </ng-container>

  //           <tr mat-header-row *matHeaderRowDef="displayedColumns" class="!bg-slate-50"></tr>
  //           <tr
  //             mat-row
  //             *matRowDef="let row; columns: displayedColumns"
  //             class="hover:bg-slate-50 cursor-pointer"
  //             (click)="viewDetails(row)"
  //           ></tr>

  //           <!-- Empty State -->
  //           <tr class="mat-row" *matNoDataRow>
  //             <td class="mat-cell text-center py-16" [attr.colspan]="displayedColumns.length">
  //               <div class="flex flex-col items-center">
  //                 <div
  //                   class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 mb-4"
  //                 >
  //                   <mat-icon fontSet="material-symbols-rounded" class="!text-[32px] !text-slate-400"
  //                     >groups</mat-icon
  //                   >
  //                 </div>
  //                 @if (hasActiveFilters()) {
  //                 <p class="text-slate-600 font-medium mb-1">No members match your filters</p>
  //                 <button mat-stroked-button (click)="clearFilters()" class="mt-4 !border-slate-200">
  //                   Clear Filters
  //                 </button>
  //                 } @else {
  //                 <p class="text-slate-600 font-medium mb-1">No members yet</p>
  //                 <button mat-flat-button color="primary" (click)="openDialog()" class="mt-4">
  //                   Add First Member
  //                 </button>
  //                 }
  //               </div>
  //             </td>
  //           </tr>
  //         </table>

  //         <mat-paginator
  //           [pageSizeOptions]="[10, 25, 50, 100]"
  //           [pageSize]="25"
  //           showFirstLastButtons
  //           class="!border-t !border-slate-100"
  //         ></mat-paginator>
  //         }
  //       </div>
  //     </div>
  //   </mat-drawer-content>

  //   <!-- Detail Drawer -->
  //   <mat-drawer #detailDrawer mode="over" position="end" class="!w-[480px]">
  //     @if (selectedMember(); as member) {
  //     <div class="h-full flex flex-col">
  //       <!-- Drawer Header -->
  //       <div
  //         class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between"
  //       >
  //         <div class="flex items-center gap-3">
  //           <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100">
  //             <mat-icon fontSet="material-symbols-rounded" class="!text-blue-600">
  //               {{ getMemberTypeIcon(member.member_type) }}
  //             </mat-icon>
  //           </div>
  //           <div>
  //             <h3 class="font-semibold text-slate-900">
  //               {{ member.full_name || member.first_name + ' ' + member.last_name }}
  //             </h3>
  //             <p class="text-sm text-slate-500 font-mono">{{ member.member_number }}</p>
  //           </div>
  //         </div>
  //         <button mat-icon-button (click)="closeDrawer()" class="!text-slate-400">
  //           <mat-icon fontSet="material-symbols-rounded">close</mat-icon>
  //         </button>
  //       </div>

  //       <!-- Drawer Content -->
  //       <div class="flex-1 overflow-y-auto p-6 space-y-5">
  //         <!-- Status Badges -->
  //         <div class="flex flex-wrap gap-2">
  //           <span
  //             class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium"
  //             [class]="getStatusClasses(member.status)"
  //           >
  //             <span class="h-1.5 w-1.5 rounded-full" [class]="getStatusDot(member.status)"></span>
  //             {{ getLabel(MEMBER_STATUSES, member.status) }}
  //           </span>
  //           <span
  //             class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 text-slate-700 rounded-full text-sm"
  //           >
  //             <mat-icon fontSet="material-symbols-rounded" class="!text-[16px]">
  //               {{ getMemberTypeIcon(member.member_type) }}
  //             </mat-icon>
  //             {{ getLabel(MEMBER_TYPES, member.member_type) }}
  //           </span>
  //         </div>

  //         <!-- Personal Info -->
  //         <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 space-y-3">
  //           <h4 class="font-medium text-slate-900 flex items-center gap-2">
  //             <mat-icon fontSet="material-symbols-rounded" class="!text-slate-400 !text-[20px]"
  //               >person</mat-icon
  //             >
  //             Personal Information
  //           </h4>
  //           <div class="grid grid-cols-3 gap-2 text-sm">
  //             <span class="text-slate-500">Gender</span>
  //             <span class="col-span-2 text-slate-900">{{
  //               getLabel(GENDERS, member.gender ?? '')
  //             }}</span>
  //           </div>
  //           <div class="grid grid-cols-3 gap-2 text-sm">
  //             <span class="text-slate-500">Date of Birth</span>
  //             <span class="col-span-2 text-slate-900">
  //               {{ member.date_of_birth | date : 'mediumDate' }}
  //               <span class="text-slate-400"
  //                 >({{ member.age || calculateAge(member.date_of_birth ?? '') }} yrs)</span
  //               >
  //             </span>
  //           </div>
  //           @if (member.national_id) {
  //           <div class="grid grid-cols-3 gap-2 text-sm">
  //             <span class="text-slate-500">National ID</span>
  //             <span class="col-span-2 font-mono text-slate-900">{{ member.national_id }}</span>
  //           </div>
  //           }
  //         </div>

  //         <!-- Contact Info -->
  //         <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 space-y-3">
  //           <h4 class="font-medium text-slate-900 flex items-center gap-2">
  //             <mat-icon fontSet="material-symbols-rounded" class="!text-slate-400 !text-[20px]"
  //               >contact_phone</mat-icon
  //             >
  //             Contact Details
  //           </h4>
  //           @if (member.email) {
  //           <div class="grid grid-cols-3 gap-2 text-sm">
  //             <span class="text-slate-500">Email</span>
  //             <a [href]="'mailto:' + member.email" class="col-span-2 text-blue-600 hover:underline">
  //               {{ member.email }}
  //             </a>
  //           </div>
  //           } @if (member.phone || member.mobile) {
  //           <div class="grid grid-cols-3 gap-2 text-sm">
  //             <span class="text-slate-500">Phone</span>
  //             <span class="col-span-2 text-slate-900">{{ member.mobile || member.phone }}</span>
  //           </div>
  //           }
  //         </div>

  //         <!-- Policy Info -->
  //         <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 space-y-3">
  //           <h4 class="font-medium text-blue-900 flex items-center gap-2">
  //             <mat-icon fontSet="material-symbols-rounded" class="!text-blue-600 !text-[20px]"
  //               >description</mat-icon
  //             >
  //             Policy Information
  //           </h4>
  //           <div class="grid grid-cols-3 gap-2 text-sm">
  //             <span class="text-blue-700">Policy #</span>
  //             <span class="col-span-2 font-mono text-blue-900">{{ member.policy_id || 'N/A' }}</span>
  //           </div>
  //           <div class="grid grid-cols-3 gap-2 text-sm">
  //             <span class="text-blue-700">Cover Start</span>
  //             <span class="col-span-2 text-blue-900">{{
  //               member.cover_start_date | date : 'mediumDate'
  //             }}</span>
  //           </div>
  //         </div>

  //         <!-- Card Info -->
  //         <div class="rounded-lg border border-green-200 bg-green-50 p-4 space-y-3">
  //           <h4 class="font-medium text-green-900 flex items-center gap-2">
  //             <mat-icon fontSet="material-symbols-rounded" class="!text-green-600 !text-[20px]"
  //               >credit_card</mat-icon
  //             >
  //             Medical Card
  //           </h4>
  //           <div class="grid grid-cols-3 gap-2 text-sm">
  //             <span class="text-green-700">Status</span>
  //             <span class="col-span-2 flex items-center gap-1.5">
  //               <span
  //                 class="h-1.5 w-1.5 rounded-full"
  //                 [class]="getCardStatusDot(member.card_status)"
  //               ></span>
  //               {{ getLabel(CARD_STATUSES, member.card_status ?? '') || 'Pending' }}
  //             </span>
  //           </div>
  //           @if (member.card_number) {
  //           <div class="grid grid-cols-3 gap-2 text-sm">
  //             <span class="text-green-700">Card #</span>
  //             <span class="col-span-2 font-mono text-green-900">{{ member.card_number }}</span>
  //           </div>
  //           }

  //           <div class="flex gap-2 pt-2">
  //             @if (!member.card_status || member.card_status === 'pending') {
  //             <button
  //               mat-stroked-button
  //               (click)="issueCard(member)"
  //               class="!border-green-300 !text-green-700"
  //             >
  //               <mat-icon fontSet="material-symbols-rounded">add_card</mat-icon>
  //               Issue Card
  //             </button>
  //             } @if (member.card_status === 'issued') {
  //             <button mat-stroked-button color="primary" (click)="activateCard(member)">
  //               Activate
  //             </button>
  //             } @if (member.card_status === 'active') {
  //             <button mat-stroked-button color="warn" (click)="blockCard(member)">Block</button>
  //             }
  //           </div>
  //         </div>

  //         <!-- Premium Info -->
  //         @if (member.premium) {
  //         <div class="rounded-lg border border-purple-200 bg-purple-50 p-4 space-y-3">
  //           <h4 class="font-medium text-purple-900 flex items-center gap-2">
  //             <mat-icon fontSet="material-symbols-rounded" class="!text-purple-600 !text-[20px]"
  //               >payments</mat-icon
  //             >
  //             Premium
  //           </h4>
  //           <div class="grid grid-cols-3 gap-2 text-sm">
  //             <span class="text-purple-700">Base</span>
  //             <span class="col-span-2 text-purple-900">{{ formatCurrency(member.premium) }}</span>
  //           </div>
  //           @if (member.loading_amount > 0) {
  //           <div class="grid grid-cols-3 gap-2 text-sm">
  //             <span class="text-purple-700">Loadings</span>
  //             <span class="col-span-2 text-red-600"
  //               >+{{ formatCurrency(member.loading_amount) }}</span
  //             >
  //           </div>
  //           }
  //           <div class="grid grid-cols-3 gap-2 text-sm font-medium">
  //             <span class="text-purple-700">Total</span>
  //             <span class="col-span-2 text-purple-900">
  //               {{ formatCurrency((member.premium || 0) + (member.loading_amount || 0)) }}
  //             </span>
  //           </div>
  //         </div>
  //         }

  //         <!-- Loadings & Exclusions Summary -->
  //         <div class="grid grid-cols-2 gap-4">
  //           <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-center">
  //             <p class="text-2xl font-semibold text-red-700">{{ member.loadings?.length || 0 }}</p>
  //             <p class="text-sm text-red-600">Loadings</p>
  //           </div>
  //           <div class="rounded-lg border border-orange-200 bg-orange-50 p-4 text-center">
  //             <p class="text-2xl font-semibold text-orange-700">
  //               {{ member.exclusions?.length || 0 }}
  //             </p>
  //             <p class="text-sm text-orange-600">Exclusions</p>
  //           </div>
  //         </div>
  //       </div>

  //       <!-- Drawer Footer -->
  //       <div
  //         class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between"
  //       >
  //         <a
  //           mat-stroked-button
  //           [routerLink]="['/medical/members', member.id]"
  //           (click)="closeDrawer()"
  //           class="!border-slate-200"
  //         >
  //           <mat-icon fontSet="material-symbols-rounded">open_in_new</mat-icon>
  //           Full Profile
  //         </a>
  //         <button mat-flat-button color="primary" (click)="openDialog(member)">
  //           <mat-icon fontSet="material-symbols-rounded">edit</mat-icon>
  //           Edit
  //         </button>
  //       </div>
  //     </div>
  //     }
  //   </mat-drawer>
  // </mat-drawer-container>
  // `,
})
export class MedicalMembersList implements OnInit, AfterViewInit {
  readonly store = inject(MemberStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  // Table
  displayedColumns = [
    'status',
    'member_number',
    'name',
    'type',
    'policy',
    'age',
    'card',
    'actions',
  ];
  dataSource = new MatTableDataSource<Member>([]);

  @ViewChild(MatPaginator) paginator!: MatPaginator;
  @ViewChild(MatSort) sort!: MatSort;
  @ViewChild('detailDrawer') detailDrawer!: MatDrawer;

  // Filters
  searchTerm = signal('');
  statusFilter = signal('');
  typeFilter = signal('');

  // Selected item for drawer
  selectedMember = signal<Member | null>(null);

  // Constants
  readonly MEMBER_TYPES = MEMBER_TYPES;
  readonly MEMBER_STATUSES = MEMBER_STATUSES;
  readonly CARD_STATUSES = CARD_STATUSES;
  readonly GENDERS = GENDERS;
  readonly getLabelByValue = getLabelByValue;
  readonly formatCurrency = formatCurrency;
  readonly calculateAge = calculateAge;

  // Computed Properties for Logic
  readonly hasActiveFilters = computed(
    () => this.searchTerm() !== '' || this.statusFilter() !== '' || this.typeFilter() !== ''
  );

  // Local KPIs (derived from store list for immediate feedback)
  // Note: store.activeMembers, etc are already in the store, but we add 'With Loadings' here
  readonly membersWithLoadings = computed(() =>
    this.store
      .members()
      .filter((m) => (m.loadings && m.loadings.length > 0) || m.loading_amount > 0)
  );

  constructor() {
    effect(() => {
      const members = this.store.members();
      this.dataSource.data = members;
    });
  }

  ngOnInit(): void {
    this.store.loadAll();
    // this.store.loadStats(); // Optional if stats endpoint is separate
    this.setupFilter();
  }

  ngAfterViewInit(): void {
    this.dataSource.paginator = this.paginator;
    this.dataSource.sort = this.sort;
  }

  private setupFilter(): void {
    this.dataSource.filterPredicate = (data: Member, filter: string) => {
      const searchData = JSON.parse(filter);

      const textMatch =
        !searchData.search ||
        data.member_number.toLowerCase().includes(searchData.search) ||
        data.first_name.toLowerCase().includes(searchData.search) ||
        data.last_name.toLowerCase().includes(searchData.search) ||
        (data.full_name?.toLowerCase().includes(searchData.search) ?? false) ||
        (data.national_id?.toLowerCase().includes(searchData.search) ?? false) ||
        (data.policy_id?.toLowerCase().includes(searchData.search) ?? false);

      const statusMatch = !searchData.status || data.status === searchData.status;
      const typeMatch = !searchData.type || data.member_type === searchData.type;

      return textMatch && statusMatch && typeMatch;
    };
  }

  private applyFilter(): void {
    const filterValue = JSON.stringify({
      search: this.searchTerm().toLowerCase(),
      status: this.statusFilter(),
      type: this.typeFilter(),
    });
    this.dataSource.filter = filterValue;

    if (this.dataSource.paginator) {
      this.dataSource.paginator.firstPage();
    }
  }

  onSearchChange(value: string): void {
    this.searchTerm.set(value);
    this.applyFilter();
  }

  onStatusChange(value: string): void {
    this.statusFilter.set(value);
    this.applyFilter();
  }

  onTypeChange(value: string): void {
    this.typeFilter.set(value);
    this.applyFilter();
  }

  clearSearch(): void {
    this.searchTerm.set('');
    this.applyFilter();
  }

  clearFilters(): void {
    this.searchTerm.set('');
    this.statusFilter.set('');
    this.typeFilter.set('');
    this.applyFilter();
  }

  // =========================================================================
  // DRAWER & DIALOGS
  // =========================================================================

  viewDetails(member: Member): void {
    this.store.loadOne(member.id).subscribe((res) => {
      if (res?.data) {
        this.selectedMember.set(res.data);
        this.detailDrawer.open();
      }
    });
  }

  closeDrawer(): void {
    this.detailDrawer.close();
    this.selectedMember.set(null);
  }

  openDialog(member?: Member): void {
    const dialogRef = this.dialog.open(MedicalMemberDialog, {
      width: '70vw',
      minWidth: '70vw',
      maxHeight: '90vh',
      data: { member },
      disableClose: true,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.store.loadAll();
        this.feedback.success(
          member ? 'Member updated successfully' : 'Member created successfully'
        );
      }
    });
  }

  // =========================================================================
  // ACTIONS
  // =========================================================================

  async activateMember(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();
    const confirmed = await this.feedback.confirm(
      'Activate Member',
      `Are you sure you want to activate "${member.full_name || member.first_name}"?`
    );
    if (confirmed) {
      this.store.activate(member.id).subscribe({
        next: () => this.feedback.success('Member activated successfully'),
        error: (err) => this.feedback.error(err.error?.message),
      });
    }
  }

  async suspendMember(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();
    const confirmed = await this.feedback.confirm(
      'Suspend Member',
      `Are you sure you want to suspend "${member.full_name || member.first_name}"?`
    );
    if (confirmed) {
      this.store.suspend(member.id).subscribe({
        next: () => this.feedback.success('Member suspended successfully'),
        error: (err) => this.feedback.error(err.error?.message),
      });
    }
  }

  async terminateMember(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();
    const confirmed = await this.feedback.confirm(
      'Terminate Member',
      `Are you sure you want to terminate "${member.full_name || member.first_name}"?`
    );
    if (confirmed) {
      this.store.terminate(member.id, 'voluntary').subscribe({
        next: () => {
          this.feedback.success('Member terminated successfully');
          if (this.selectedMember()?.id === member.id) this.closeDrawer();
        },
        error: (err) => this.feedback.error(err.error?.message),
      });
    }
  }

  // =========================================================================
  // CARD ACTIONS
  // =========================================================================

  async issueCard(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (await this.feedback.confirm('Issue Card', 'Issue a new card?')) {
      this.store.issueCard(member.id).subscribe({
        next: () => this.feedback.success('Card issued successfully'),
        error: (err) => this.feedback.error(err.error?.message),
      });
    }
  }

  async activateCard(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (await this.feedback.confirm('Activate Card', 'Activate this card?')) {
      this.store.activateCard(member.id).subscribe({
        next: () => this.feedback.success('Card activated successfully'),
        error: (err) => this.feedback.error(err.error?.message),
      });
    }
  }

  async blockCard(member: Member, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (await this.feedback.confirm('Block Card', 'Block this card?')) {
      this.store.blockCard(member.id).subscribe({
        next: () => this.feedback.success('Card blocked successfully'),
        error: (err) => this.feedback.error(err.error?.message),
      });
    }
  }

  // =========================================================================
  // HELPERS
  // =========================================================================

  getStatusClasses(status: string): string {
    const config = getStatusConfig(MEMBER_STATUSES, status);
    return config ? `${config.bgColor} ${config.color}` : 'bg-slate-100 text-slate-600';
  }

  getCardStatusClasses(status: string | undefined): string {
    if (!status) return 'bg-slate-100 text-slate-600';
    const config = CARD_STATUSES.find((s) => s.value === status);
    return config?.color || 'text-slate-600';
  }

  getCardStatusDotColor(status: string | undefined): string {
    if (!status || status === 'pending') return 'bg-slate-400';
    if (status === 'issued') return 'bg-blue-500';
    if (status === 'active') return 'bg-green-500';
    if (status === 'blocked') return 'bg-red-500';
    if (status === 'expired') return 'bg-amber-500';
    return 'bg-slate-400';
  }

  getMemberTypeIcon(type: string): string {
    return MEMBER_TYPES.find((t) => t.value === type)?.icon || 'person';
  }

  getStatusDotColor(status: string): string {
    const config = getStatusConfig(MEMBER_STATUSES, status);
    if (!config) return 'bg-slate-400';
    const match = config.bgColor.match(/bg-(\w+)-\d+/);
    return match ? `bg-${match[1]}-500` : 'bg-slate-400';
  }

  getWaitingPeriodStatus(member: Member): { active: boolean; type?: string; daysLeft?: number } {
    const today = new Date();
    // Simplified logic, would usually check specific member exclusions
    const waitingPeriods = [{ type: 'General', end: WAITING_PERIOD_TYPES[0].defaultDays }];
    // This is placeholder logic as actual WP dates are on exclusions/cover dates
    if (member.waiting_period_end_date) {
      const endDate = new Date(member.waiting_period_end_date);
      if (endDate > today) {
        const daysLeft = Math.ceil((endDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
        return { active: true, type: 'General', daysLeft };
      }
    }
    return { active: false };
  }

  exportToCsv(): void {
    const data = this.dataSource.filteredData;
    if (data.length === 0) {
      this.feedback.error('No data to export');
      return;
    }

    const headers = [
      'Member #',
      'Name',
      'National ID',
      'Type',
      'Gender',
      'Age',
      'Status',
      'Policy ID',
      'Card Status',
    ];

    const csvContent = [
      headers.join(','),
      ...data.map((m) =>
        [
          `"${m.member_number}"`,
          `"${m.full_name || m.first_name + ' ' + m.last_name}"`,
          `"${m.national_id || ''}"`,
          `"${getLabelByValue(MEMBER_TYPES, m.member_type)}"`,
          `"${m.gender || ''}"`,
          m.age || calculateAge(m.date_of_birth ?? ''),
          `"${m.status}"`,
          `"${m.policy_id || ''}"`,
          `"${m.card_status || 'pending'}"`,
        ].join(',')
      ),
    ].join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `members_${new Date().toISOString().split('T')[0]}.csv`;
    link.click();
    window.URL.revokeObjectURL(url);
  }
}
