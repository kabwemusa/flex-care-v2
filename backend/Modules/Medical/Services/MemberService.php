<?php
namespace Modules\Medical\Services;

use Modules\Medical\Models\Member;
use Illuminate\Support\Str;

class MemberService
{
    public function generateMemberNumber(): string
    {
        $year = date('Y');
        $prefix = "MEM-{$year}";

        $lastMember = Member::where('member_number', 'like', "{$prefix}-%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastMember 
            ? (int) Str::afterLast($lastMember->member_number, '-') + 1 
            : 1;

        return "{$prefix}-" . str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }
}