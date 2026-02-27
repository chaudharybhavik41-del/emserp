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
                'name'         => 'Test Email',
                'subject'      => 'Test email from EMS Infra ERP',
                'body_html'    => '<p>Hello {{user_name}},</p><p>This is a test email.</p>',
                'body_text'    => 'Hello {{user_name}}, This is a test email.',
                'placeholders' => ['user_name'],
                'is_active'    => true,
            ]
        );

        MailTemplate::updateOrCreate(
            ['code' => 'general_notification'],
            [
                'name'         => 'General Notification',
                'subject'      => 'Notification from EMS Infra ERP',
                'body_html'    => '<p>{{message}}</p>',
                'body_text'    => '{{message}}',
                'placeholders' => ['message'],
                'is_active'    => true,
            ]
        );
    }
}
