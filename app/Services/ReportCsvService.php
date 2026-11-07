<?php

namespace App\Services;

use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportCsvService
{
    public function __construct(private readonly ReportQueryService $query) {}

    /** @param array<string, mixed> $filters */
    public function download(User $user, string $kind, array $filters): StreamedResponse
    {
        $query = $this->query->filtered($user, $kind, $filters);
        $date = $this->query->dateColumn($kind, $filters);
        $columns = $this->columns($kind);
        $name = match ($kind) {
            'reservations' => 'reservas', 'incidents' => 'ocorrencias', 'maintenances' => 'manutencoes'
        };

        return response()->streamDownload(function () use ($query, $kind, $date, $columns): void {
            $output = fopen('php://output', 'wb');
            try {
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, array_values($columns), ';', '"', '', "\r\n");
                foreach ($this->query->exportRows($query, $kind, $date) as $row) {
                    $cells = [];
                    foreach ($columns as $field => $label) {
                        $value = $row[$field];
                        if ($field === 'cost' && $value !== null) {
                            $value = str_replace('.', ',', $value);
                        }
                        $cells[] = $this->safeCell($value);
                    }
                    fputcsv($output, $cells, ';', '"', '', "\r\n");
                }
            } finally {
                fclose($output);
            }
        }, 'relatorio-'.$name.'-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, string> */
    private function columns(string $kind): array
    {
        return match ($kind) {
            'reservations' => ['id' => 'ID', 'area' => 'Área comum', 'date' => 'Data', 'start' => 'Horário inicial', 'end' => 'Horário final', 'status' => 'Status', 'resident' => 'Morador', 'unit' => 'Unidade'],
            'incidents' => ['id' => 'ID', 'title' => 'Título', 'type' => 'Tipo', 'category' => 'Categoria', 'priority' => 'Prioridade', 'status' => 'Status', 'resident' => 'Morador', 'unit' => 'Unidade', 'opened_at' => 'Data de abertura'],
            'maintenances' => ['id' => 'ID', 'description' => 'Descrição', 'unit' => 'Unidade', 'origin' => 'Origem', 'incident' => 'Ocorrência vinculada', 'admin' => 'Administrador responsável', 'provider' => 'Prestador', 'status' => 'Status', 'created_at' => 'Data de abertura', 'scheduled_at' => 'Agendamento', 'executed_at' => 'Execução', 'cost' => 'Custo', 'area' => 'Área comum'],
        };
    }

    private function safeCell(int|string|null $value): string
    {
        $value = (string) $value;

        return preg_match('/^(?:[\p{Z}\p{C}]*[=+\-@]|[\p{C}])/u', $value) === 1 ? "'".$value : $value;
    }
}
