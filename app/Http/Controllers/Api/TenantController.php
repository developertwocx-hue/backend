<?php

namespace App\Http\Controllers\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class TenantController extends ApiController
{
    public function registerBusiness(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255',
            'business_email' => 'required|string|email|max:255|unique:tenants,email',
            'business_phone' => 'nullable|string|max:20',
            'business_address' => 'nullable|string',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|string|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // Create tenant (business)
            $tenant = Tenant::create([
                'name' => $request->business_name,
                'email' => $request->business_email,
                'phone' => $request->business_phone,
                'address' => $request->business_address,
                'subscription_plan' => 'basic',
                'subscription_ends_at' => now()->addDays(30), // 30 days trial
            ]);

            // Create admin user for this tenant
            $admin = User::create([
                'tenant_id' => $tenant->id,
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'role' => 'admin', // Business owner/admin
            ]);

            // Create token
            $token = $admin->createToken('auth_token')->plainTextToken;

            DB::commit();

            return $this->successResponse([
                'tenant' => $tenant,
                'user' => $admin,
                'token' => $token,
            ], 'Business registered successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Business registration failed: ' . $e->getMessage(), 500);
        }
    }

    public function getCurrentTenant(Request $request)
    {
        $user = $request->user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return $this->errorResponse('No tenant associated with this user', 404);
        }

        return $this->successResponse($tenant, 'Tenant retrieved successfully');
    }

    public function updateTenant(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return $this->errorResponse('Only business admin can update tenant information', 403);
        }

        $tenant = $user->tenant;

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:tenants,email,' . $tenant->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $tenant->update($request->only(['name', 'email', 'phone', 'address']));

        return $this->successResponse($tenant, 'Tenant updated successfully');
    }
}
