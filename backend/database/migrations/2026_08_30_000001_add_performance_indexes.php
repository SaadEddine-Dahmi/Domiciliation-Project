<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->indexes() as $statement) {
            DB::statement($statement);
        }
    }

    public function down(): void
    {
        foreach ($this->indexNames() as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }

    private function indexes(): array
    {
        return [
            'CREATE INDEX IF NOT EXISTS entreprises_tenant_created_idx ON entreprises (domiciliataire_id, created_at)',
            'CREATE INDEX IF NOT EXISTS entreprises_tenant_status_idx ON entreprises (domiciliataire_id, statut)',
            'CREATE INDEX IF NOT EXISTS contrats_tenant_created_idx ON contrats (domiciliataire_id, created_at)',
            'CREATE INDEX IF NOT EXISTS contrats_tenant_status_archive_idx ON contrats (domiciliataire_id, statut, archived_at)',
            'CREATE INDEX IF NOT EXISTS contrats_company_status_archive_idx ON contrats (entreprise_id, statut, archived_at)',
            'CREATE INDEX IF NOT EXISTS contrats_status_date_fin_idx ON contrats (statut, date_fin)',
            'CREATE INDEX IF NOT EXISTS documents_company_created_idx ON documents (entreprise_id, created_at)',
            'CREATE INDEX IF NOT EXISTS documents_company_expiration_idx ON documents (entreprise_id, date_expiration)',
            'CREATE INDEX IF NOT EXISTS factures_tenant_created_idx ON factures (domiciliataire_id, created_at)',
            'CREATE INDEX IF NOT EXISTS factures_tenant_archive_idx ON factures (domiciliataire_id, archived_at)',
            'CREATE INDEX IF NOT EXISTS factures_tenant_status_idx ON factures (domiciliataire_id, statut)',
            'CREATE INDEX IF NOT EXISTS notifications_user_sender_created_idx ON notifications (user_id, from_user_id, created_at)',
            'CREATE INDEX IF NOT EXISTS notifications_user_read_sender_idx ON notifications (user_id, is_read, from_user_id)',
            'CREATE INDEX IF NOT EXISTS articles_tenant_created_idx ON articles (domiciliataire_id, created_at)',
            'CREATE INDEX IF NOT EXISTS templates_tenant_created_idx ON templates (domiciliataire_id, created_at)',
        ];
    }

    private function indexNames(): array
    {
        return [
            'entreprises_tenant_created_idx',
            'entreprises_tenant_status_idx',
            'contrats_tenant_created_idx',
            'contrats_tenant_status_archive_idx',
            'contrats_company_status_archive_idx',
            'contrats_status_date_fin_idx',
            'documents_company_created_idx',
            'documents_company_expiration_idx',
            'factures_tenant_created_idx',
            'factures_tenant_archive_idx',
            'factures_tenant_status_idx',
            'notifications_user_sender_created_idx',
            'notifications_user_read_sender_idx',
            'articles_tenant_created_idx',
            'templates_tenant_created_idx',
        ];
    }
};
