<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $rows = DB::table('service_providers')->orderBy('id')->lockForUpdate()->get(['id', 'cpf_cnpj']);
            $seen = [];
            foreach ($rows as $row) {
                $document = $row->cpf_cnpj === null ? null : preg_replace('/[.\\/\\-\\s]/', '', $row->cpf_cnpj);
                $document = $document === '' ? null : $document;
                if ($document !== null && (! preg_match('/^(?:[0-9]{11}|[0-9]{14})$/D', $document) || isset($seen[$document]))) {
                    throw new RuntimeException('Reconcile invalid or duplicate provider documents before normalization.');
                }
                if ($document !== null) {
                    $seen[$document] = true;
                }
            }
            foreach ($rows as $row) {
                $document = $row->cpf_cnpj === null ? null : preg_replace('/[.\\/\\-\\s]/', '', $row->cpf_cnpj);
                DB::table('service_providers')->where('id', $row->id)->update(['cpf_cnpj' => $document === '' ? null : $document]);
            }
        });
    }

    /** Canonical documents are retained on rollback; no identity data is discarded. */
    public function down(): void {}
};
