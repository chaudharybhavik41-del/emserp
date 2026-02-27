<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mail_templates')) {
            return;
        }

        $now = now();

        $payload = [
            'code'       => 'purchase_rfq_send',
            'is_active'  => 1,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('mail_templates', 'name')) {
            $payload['name'] = 'Purchase RFQ Email';
        }
        if (Schema::hasColumn('mail_templates', 'subject')) {
            $payload['subject'] = 'RFQ {{rfq_code}} - Quotation Request';
        }
        if (Schema::hasColumn('mail_templates', 'body_html')) {
            $payload['body_html'] = '<p>Dear {{vendor_name}},</p>
<p>Please share your best quotation for the attached RFQ <strong>{{rfq_code}}</strong>.</p>
<ul>
  <li>RFQ Date: {{rfq_date}}</li>
  <li>Due Date: {{due_date}}</li>
  <li>Project: {{project}}</li>
  <li>Department: {{department}}</li>
  <li>Items: {{items_count}} ({{items_short}})</li>
</ul>
<p>Regards,<br>{{company_name}}</p>';
        }
        if (Schema::hasColumn('mail_templates', 'body_text')) {
            $payload['body_text'] = "Dear {{vendor_name}},\n\nPlease share your best quotation for the attached RFQ {{rfq_code}}.\nRFQ Date: {{rfq_date}}\nDue Date: {{due_date}}\nProject: {{project}}\nDepartment: {{department}}\nItems: {{items_count}} ({{items_short}})\n\nRegards,\n{{company_name}}";
        }
        if (Schema::hasColumn('mail_templates', 'placeholders')) {
            $payload['placeholders'] = json_encode([
                'vendor_name', 'rfq_code', 'rfq_date', 'due_date', 'project', 'department', 'items_count', 'items_short', 'company_name',
            ]);
        }
        if (Schema::hasColumn('mail_templates', 'created_at')) {
            $payload['created_at'] = $now;
        }

        $exists = DB::table('mail_templates')->where('code', 'purchase_rfq_send')->exists();

        if ($exists) {
            DB::table('mail_templates')->where('code', 'purchase_rfq_send')->update($payload);
        } else {
            DB::table('mail_templates')->insert($payload);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('mail_templates')) {
            return;
        }

        DB::table('mail_templates')->where('code', 'purchase_rfq_send')->delete();
    }
};
