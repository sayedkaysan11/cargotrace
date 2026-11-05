<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /** Demo password for every seeded account: "password" (documented in README). */
    public function run(): void
    {
        $hash = Hash::make('password');

        $accounts = [
            ['Admin CargoTrace', 'admin@cargotrace.test', UserRole::Admin, 'CargoTrace HQ'],
            ['Sarah Johnson', 'operator1@cargotrace.test', UserRole::Operator, 'CargoTrace Operations'],
            ['Budi Santoso', 'operator2@cargotrace.test', UserRole::Operator, 'CargoTrace Operations'],
            ['PT Nusantara Textile', 'customer1@example.test', UserRole::Customer, 'PT Nusantara Textile (ID)'],
            ['Shenzhen Electronics', 'customer2@example.test', UserRole::Customer, 'Shenzhen Electronics Co., Ltd. (CN)'],
            ['Saigon Furniture', 'customer3@example.test', UserRole::Customer, 'Saigon Furniture JSC (VN)'],
            ['Rotterdam Retail', 'customer4@example.test', UserRole::Customer, 'Rotterdam Retail BV (NL)'],
            ['Pacific Coast Imports', 'customer5@example.test', UserRole::Customer, 'Pacific Coast Imports LLC (US)'],
            ['Outback Supplies', 'customer6@example.test', UserRole::Customer, 'Outback Supplies Pty Ltd (AU)'],
        ];

        foreach ($accounts as [$name, $email, $role, $company]) {
            // role / is_active are not mass-assignable, so set them explicitly.
            $user = User::firstOrNew(['email' => $email]);
            $user->name = $name;
            $user->password = $hash;
            $user->company_name = $company;
            $user->role = $role;
            $user->is_active = true;
            $user->email_verified_at = now();
            $user->save();
        }
    }
}
