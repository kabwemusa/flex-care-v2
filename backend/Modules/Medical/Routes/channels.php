<?php

use Illuminate\Support\Facades\Broadcast;
use Modules\Medical\Models\Claim;
use Modules\Medical\Models\PreAuthorization;
use Modules\Medical\Models\Member;
use Modules\Medical\Models\Provider;

/*
|--------------------------------------------------------------------------
| Medical Module Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| medical module supports. Channel authorization callbacks verify that
| the authenticated user can listen on the channel.
|
*/

/**
 * Claim-specific channel
 * Allows claim owner (member) or related provider to listen
 */
Broadcast::channel('claim.{claimId}', function ($user, $claimId) {
    $claim = Claim::find($claimId);

    if (!$claim) {
        return false;
    }

    // Allow if user is the member
    if ($user->member_id && $user->member_id === $claim->member_id) {
        return true;
    }

    // Allow if user is from the provider
    if ($user->provider_id && $user->provider_id === $claim->provider_id_fk) {
        return true;
    }

    // Allow staff with claims permission
    if ($user->hasPermission('medical.claims.view')) {
        return true;
    }

    return false;
});

/**
 * Pre-authorization channel
 */
Broadcast::channel('preauth.{preauthId}', function ($user, $preauthId) {
    $preauth = PreAuthorization::find($preauthId);

    if (!$preauth) {
        return false;
    }

    // Allow if user is the member
    if ($user->member_id && $user->member_id === $preauth->member_id) {
        return true;
    }

    // Allow if user is from the provider
    if ($user->provider_id && $user->provider_id === $preauth->provider_id) {
        return true;
    }

    // Allow staff with preauth permission
    if ($user->hasPermission('medical.preauth.view')) {
        return true;
    }

    return false;
});

/**
 * Member-specific channel
 * Only the member themselves or authorized staff can listen
 */
Broadcast::channel('member.{memberId}', function ($user, $memberId) {
    // Allow if user is the member
    if ($user->member_id && $user->member_id === $memberId) {
        return true;
    }

    // Allow staff with member view permission
    if ($user->hasPermission('medical.members.view')) {
        return true;
    }

    return false;
});

/**
 * Member benefits channel
 */
Broadcast::channel('member.{memberId}.benefits', function ($user, $memberId) {
    // Same authorization as member channel
    if ($user->member_id && $user->member_id === $memberId) {
        return true;
    }

    if ($user->hasPermission('medical.members.view')) {
        return true;
    }

    return false;
});

/**
 * Provider-specific channel
 * Only users from that provider or authorized staff can listen
 */
Broadcast::channel('provider.{providerId}', function ($user, $providerId) {
    // Allow if user is from the provider
    if ($user->provider_id && $user->provider_id === $providerId) {
        return true;
    }

    // Allow staff with provider view permission
    if ($user->hasPermission('medical.providers.view')) {
        return true;
    }

    return false;
});

/**
 * Fraud alerts channel (staff only)
 */
Broadcast::channel('fraud-alerts', function ($user) {
    return $user->hasPermission('medical.fraud.view');
});

/**
 * Fraud alerts by severity (staff only)
 */
Broadcast::channel('fraud-alerts.{severity}', function ($user, $severity) {
    // Only allow valid severities
    if (!in_array($severity, ['low', 'medium', 'high', 'critical'])) {
        return false;
    }

    return $user->hasPermission('medical.fraud.view');
});

/**
 * Claims processing queue status (staff only)
 */
Broadcast::channel('claims-queue', function ($user) {
    return $user->hasPermission('medical.claims.process');
});

/**
 * Real-time dashboard updates (staff only)
 */
Broadcast::channel('medical-dashboard', function ($user) {
    return $user->hasAnyPermission([
        'medical.claims.view',
        'medical.preauth.view',
        'medical.fraud.view',
    ]);
});
