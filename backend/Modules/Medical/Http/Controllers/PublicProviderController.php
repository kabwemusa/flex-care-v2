<?php

namespace Modules\Medical\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Medical\Models\Provider;
use Modules\Medical\Models\ProviderContact;
use Throwable;

class PublicProviderController
{
    use ApiResponse;

    /**
     * Self-register a new provider (hospital / clinic / etc).
     * POST /v1/medical/public/providers/register
     *
     * Creates the provider in 'pending' status. An admin must approve
     * before API credentials are issued and the provider becomes active.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'trading_name'        => 'nullable|string|max:255',
            'provider_type'       => 'required|in:hospital,clinic,pharmacy,lab,optical,dental,specialist',
            'license_number'      => 'nullable|string|max:100',
            'registration_number' => 'nullable|string|max:100',
            'address'             => 'required|string|max:500',
            'city'                => 'required|string|max:100',
            'region'              => 'nullable|string|max:100',
            'country'             => 'nullable|string|max:100',
            'postal_code'         => 'nullable|string|max:20',
            'phone'               => 'required|string|max:50',
            'email'               => 'required|email|max:255|unique:med_providers',
            'website'             => 'nullable|url|max:255',
            'contact_name'        => 'required|string|max:255',
            'contact_phone'       => 'nullable|string|max:50',
            'contact_email'       => 'required|email|max:255',
            'services'            => 'nullable|array',
            'services.*'          => 'string|max:100',
            'notes'               => 'nullable|string|max:2000',
        ]);

        try {
            return DB::transaction(function () use ($validated): JsonResponse {
                $provider = Provider::create([
                    'name'                => $validated['name'],
                    'trading_name'        => $validated['trading_name'] ?? null,
                    'provider_type'       => $validated['provider_type'],
                    'license_number'      => $validated['license_number'] ?? null,
                    'registration_number' => $validated['registration_number'] ?? null,
                    'address'             => $validated['address'],
                    'city'                => $validated['city'],
                    'region'              => $validated['region'] ?? null,
                    'country'             => $validated['country'] ?? 'Zambia',
                    'postal_code'         => $validated['postal_code'] ?? null,
                    'phone'               => $validated['phone'],
                    'email'               => $validated['email'],
                    'website'             => $validated['website'] ?? null,
                    'contact_person'      => $validated['contact_name'],
                    'contact_phone'       => $validated['contact_phone'] ?? null,
                    'contact_email'       => $validated['contact_email'],
                    'contracted_services' => $validated['services'] ?? [],
                    'status'              => 'pending',
                    'notes'               => $validated['notes'] ?? null,
                ]);

                ProviderContact::create([
                    'provider_id'            => $provider->id,
                    'name'                   => $validated['contact_name'],
                    'email'                  => $validated['contact_email'],
                    'phone'                  => $validated['contact_phone'] ?? null,
                    'is_primary'             => true,
                    'is_billing_contact'     => true,
                    'is_technical_contact'   => true,
                    'receives_notifications' => true,
                ]);

                return $this->success([
                    'id'            => $provider->id,
                    'provider_code' => $provider->provider_code,
                    'name'          => $provider->name,
                    'status'        => $provider->status,
                    'email'         => $provider->email,
                ], 'Registration submitted successfully. Your application is under review.', 201);
            });
        } catch (Throwable $e) {
            return $this->error('Registration failed: ' . $e->getMessage(), 500);
        }
    }
}
