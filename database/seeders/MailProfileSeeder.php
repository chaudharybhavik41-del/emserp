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
                'code' => 'DEFAULT',
                'company_id' => null,
                'department_id' => null,
                'smtp_host' => 'smtp.yourhost.com',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_username' => 'no-reply@emsinfra.space',
                'smtp_password' => 'CHANGE_ME',
                'from_name' => 'EMS Infra ERP',
                'from_email' => 'no-reply@emsinfra.space',
                'reply_to' => 'no-reply@emsinfra.space',
                'is_default' => true,
                'is_active' => true,
            ]
        );
    }
}
