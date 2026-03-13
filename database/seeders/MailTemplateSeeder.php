<?php

namespace Database\Seeders;

use App\Models\MailTemplate;
use Illuminate\Database\Seeder;

class MailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        MailTemplate::updateOrCreate(
            ['code' => 'test_email'],
            [
                'name' => 'Test Email',
                'type' => 'general',
                'subject' => 'Test email from EMS Infra ERP',
                'body' => '<p>Hello {{user_name}},</p><p>This is a test email.</p>',
                'is_active' => true,
            ]
        );

        MailTemplate::updateOrCreate(
            ['code' => 'general_notification'],
            [
                'name' => 'General Notification',
                'type' => 'general',
                'subject' => 'Notification from EMS Infra ERP',
                'body' => '<p>{{message}}</p>',
                'is_active' => true,
            ]
        );
    }
}
