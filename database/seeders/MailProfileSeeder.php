<?php

namespace Database\Seeders;

use App\Models\MailProfile;
use Illuminate\Database\Seeder;

class MailProfileSeeder extends Seeder
{
    public function run(): void
    {
        MailProfile::updateOrCreate(
            ['name' => 'Default SMTP'],
            [
                'company_id'  => null,
                'department_id'=> null,
                'usage'       => 'general',
                'host'        => 'smtp.yourhost.com',
                'port'        => 587,
                'encryption'  => 'tls',
                'username'    => 'no-reply@emsinfra.space',
                'password'    => 'CHANGE_ME',
                'from_name'   => 'EMS Infra ERP',
                'from_email'  => 'no-reply@emsinfra.space',
                'is_default'  => true,
                'is_active'   => true,
            ]
        );
    }
}
