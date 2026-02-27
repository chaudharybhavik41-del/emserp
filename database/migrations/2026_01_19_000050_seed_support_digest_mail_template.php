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

        $code = 'support_daily_digest';
        $now  = now();

        // Default subject/body
        $defaultSubject = 'Daily ERP Digest - {{date}}';
        $defaultBodyHtml = '{{digest_html}}';
        $defaultBodyText = "Daily ERP Digest - {{date}}\n\n(Please view this email in HTML mode.)";

        // Choose a best-effort mail profile for this template (so sending works out-of-the-box)
        $profileId = null;

        if (Schema::hasTable('mail_profiles')) {
            // 1) Default profile (prefer a profile that has smtp_host filled)
            if (Schema::hasColumn('mail_profiles', 'is_default') && Schema::hasColumn('mail_profiles', 'smtp_host')) {
                $profileId = DB::table('mail_profiles')
                    ->where('is_active', 1)
                    ->where('is_default', 1)
                    ->whereNotNull('smtp_host')
                    ->where('smtp_host', '!=', '')
                    ->orderBy('id')
                    ->value('id');
            }

            if (!$profileId && Schema::hasColumn('mail_profiles', 'is_default')) {
                $profileId = DB::table('mail_profiles')
                    ->where('is_active', 1)
                    ->where('is_default', 1)
                    ->orderBy('id')
                    ->value('id');
            }

            // 2) Dedicated profile code (optional)
            if (!$profileId && Schema::hasColumn('mail_profiles', 'code') && Schema::hasColumn('mail_profiles', 'smtp_host')) {
                $profileId = DB::table('mail_profiles')
                    ->where('is_active', 1)
                    ->where('code', 'supportDigest')
                    ->whereNotNull('smtp_host')
                    ->where('smtp_host', '!=', '')
                    ->orderBy('id')
                    ->value('id');
            }

            // 3) Any active profile with smtp_host
            if (!$profileId && Schema::hasColumn('mail_profiles', 'smtp_host')) {
                $profileId = DB::table('mail_profiles')
                    ->where('is_active', 1)
                    ->whereNotNull('smtp_host')
                    ->where('smtp_host', '!=', '')
                    ->orderBy('id')
                    ->value('id');
            }

            // 4) Any legacy host profile (fallback)
            if (!$profileId && Schema::hasColumn('mail_profiles', 'host')) {
                $profileId = DB::table('mail_profiles')
                    ->where('is_active', 1)
                    ->whereNotNull('host')
                    ->where('host', '!=', '')
                    ->orderBy('id')
                    ->value('id');
            }
        }

        // If template already exists, do a minimal safe update (do not override user customizations)
        $existing = DB::table('mail_templates')->where('code', $code)->first();

        if ($existing) {
            $updates = [];

            if (Schema::hasColumn('mail_templates', 'mail_profile_id') && empty($existing->mail_profile_id) && $profileId) {
                $updates['mail_profile_id'] = $profileId;
            }

            if (Schema::hasColumn('mail_templates', 'subject') && empty($existing->subject)) {
                $updates['subject'] = $defaultSubject;
            }

            if (Schema::hasColumn('mail_templates', 'body') && empty($existing->body)) {
                $updates['body'] = $defaultBodyHtml;
            }

            if (Schema::hasColumn('mail_templates', 'body_html') && empty($existing->body_html)) {
                $updates['body_html'] = $defaultBodyHtml;
            }

            if (Schema::hasColumn('mail_templates', 'body_text') && empty($existing->body_text)) {
                $updates['body_text'] = $defaultBodyText;
            }

            if (Schema::hasColumn('mail_templates', 'placeholders') && empty($existing->placeholders)) {
                $updates['placeholders'] = json_encode(['date', 'digest_html']);
            }

            if (!empty($updates)) {
                if (Schema::hasColumn('mail_templates', 'updated_at')) {
                    $updates['updated_at'] = $now;
                }
                DB::table('mail_templates')->where('id', $existing->id)->update($updates);
            }

            return;
        }

        $payload = [
            'code'      => $code,
            'is_active' => 1,
        ];

        if (Schema::hasColumn('mail_templates', 'name')) {
            $payload['name'] = 'Daily ERP Digest';
        }
        if (Schema::hasColumn('mail_templates', 'type')) {
            $payload['type'] = 'support';
        }
        if (Schema::hasColumn('mail_templates', 'subject')) {
            $payload['subject'] = $defaultSubject;
        }
        if (Schema::hasColumn('mail_templates', 'body')) {
            // Treat body as HTML
            $payload['body'] = $defaultBodyHtml;
        }
        if (Schema::hasColumn('mail_templates', 'body_html')) {
            $payload['body_html'] = $defaultBodyHtml;
        }
        if (Schema::hasColumn('mail_templates', 'body_text')) {
            $payload['body_text'] = $defaultBodyText;
        }
        if (Schema::hasColumn('mail_templates', 'placeholders')) {
            $payload['placeholders'] = json_encode(['date', 'digest_html']);
        }
        if (Schema::hasColumn('mail_templates', 'mail_profile_id')) {
            $payload['mail_profile_id'] = $profileId;
        }
        if (Schema::hasColumn('mail_templates', 'created_at')) {
            $payload['created_at'] = $now;
        }
        if (Schema::hasColumn('mail_templates', 'updated_at')) {
            $payload['updated_at'] = $now;
        }

        DB::table('mail_templates')->insert($payload);
    }

    public function down(): void
    {
        if (!Schema::hasTable('mail_templates')) {
            return;
        }

        DB::table('mail_templates')->where('code', 'support_daily_digest')->delete();
    }
};
