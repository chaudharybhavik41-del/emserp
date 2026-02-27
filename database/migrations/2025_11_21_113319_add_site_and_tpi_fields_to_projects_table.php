<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSiteAndTpiFieldsToProjectsTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'site_location')) {
                $table->string('site_location', 255)
                    ->nullable()
                    ->after('description');
            }

            if (!Schema::hasColumn('projects', 'site_location_url')) {
                $table->string('site_location_url', 500)
                    ->nullable()
                    ->after('site_location');
            }

            if (!Schema::hasColumn('projects', 'site_contact_name')) {
                $table->string('site_contact_name', 150)
                    ->nullable()
                    ->after('site_location_url');
            }

            if (!Schema::hasColumn('projects', 'site_contact_phone')) {
                $table->string('site_contact_phone', 50)
                    ->nullable()
                    ->after('site_contact_name');
            }

            if (!Schema::hasColumn('projects', 'site_contact_email')) {
                $table->string('site_contact_email', 150)
                    ->nullable()
                    ->after('site_contact_phone');
            }

            if (!Schema::hasColumn('projects', 'tpi_party_id')) {
                $table->foreignId('tpi_party_id')
                    ->nullable()
                    ->after('site_contact_email')
                    ->constrained('parties');
            }

            if (!Schema::hasColumn('projects', 'tpi_contact_name')) {
                $table->string('tpi_contact_name', 150)
                    ->nullable()
                    ->after('tpi_party_id');
            }

            if (!Schema::hasColumn('projects', 'tpi_contact_phone')) {
                $table->string('tpi_contact_phone', 50)
                    ->nullable()
                    ->after('tpi_contact_name');
            }

            if (!Schema::hasColumn('projects', 'tpi_contact_email')) {
                $table->string('tpi_contact_email', 150)
                    ->nullable()
                    ->after('tpi_contact_phone');
            }

            if (!Schema::hasColumn('projects', 'tpi_notes')) {
                $table->text('tpi_notes')
                    ->nullable()
                    ->after('tpi_contact_email');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'tpi_party_id')) {
                $table->dropForeign(['tpi_party_id']);
            }

            $cols = [
                'site_location',
                'site_location_url',
                'site_contact_name',
                'site_contact_phone',
                'site_contact_email',
                'tpi_party_id',
                'tpi_contact_name',
                'tpi_contact_phone',
                'tpi_contact_email',
                'tpi_notes',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('projects', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
