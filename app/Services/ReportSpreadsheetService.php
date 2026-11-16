<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use XMLWriter;
use ZipArchive;

class ReportSpreadsheetService
{
    public function __construct(
        private readonly ReportQueryService $query,
        private readonly ReportService $accesses,
        private readonly ReportCsvService $csv,
    ) {}

    /** @param array<string, mixed> $filters */
    public function download(User $user, string $kind, array $filters): StreamedResponse
    {
        abort_unless($user->exists && $user->role === UserRole::Admin && $user->is_active && $user->hasVerifiedEmail(), 403);
        $titles = ['accesses' => 'Acessos', 'reservations' => 'Reservas', 'incidents' => 'Ocorrências', 'maintenances' => 'Manutenções'];
        $title = $titles[$kind];
        $columns = $this->csv->columns($kind);
        $options = $kind === 'accesses'
            ? ['access_situations' => [
                ['value' => 'pending', 'label' => 'Aguardando (legado)'],
                ['value' => 'validated', 'label' => 'Validado sem entrada (legado)'],
                ['value' => 'present', 'label' => 'Presente'],
                ['value' => 'finished', 'label' => 'Finalizado'],
                ['value' => 'denied', 'label' => 'Entrada negada'],
            ]]
            : $this->query->options($user, $kind);
        if ($kind === 'accesses') {
            $rows = fn (): iterable => $this->accesses->exportAccesses($filters);
        } else {
            $query = $this->query->filtered($user, $kind, $filters);
            $date = $this->query->dateColumn($kind, $filters);
            $rows = fn (): iterable => $this->query->exportRows($query, $kind, $date);
        }
        if ($kind === 'maintenances' && (isset($filters['date_from']) || isset($filters['date_to']))) {
            $filters['date_reference'] ??= 'opening';
        }
        $filterText = $this->filterText($filters, $options);
        $issued = now();

        return response()->streamDownload(function () use ($title, $columns, $rows, $filterText, $issued): void {
            $sheetPath = tempnam(sys_get_temp_dir(), 'cosheet');
            $zipPath = tempnam(sys_get_temp_dir(), 'coxslx');
            if ($sheetPath === false || $zipPath === false) {
                throw new RuntimeException('Não foi possível criar a planilha.');
            }
            try {
                $this->writeSheet($sheetPath, $title, $columns, $rows, $filterText, $issued->format('d/m/Y H:i:s'));
                $zip = new ZipArchive;
                if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException('Não foi possível criar a planilha.');
                }
                foreach ($this->packageFiles() as $path => $content) {
                    $zip->addFromString($path, $content);
                }
                $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
                if (! $zip->close()) {
                    throw new RuntimeException('Não foi possível finalizar a planilha.');
                }
                readfile($zipPath);
            } finally {
                @unlink($sheetPath);
                @unlink($zipPath);
            }
        }, 'relatorio-'.Str::slug($title).'-'.$issued->format('Y-m-d-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @param array<string, mixed> $filters
     * @param  array<string, mixed>  $options
     */
    private function filterText(array $filters, array $options): string
    {
        $fields = [
            'access_date_from' => ['Data inicial', null], 'access_date_to' => ['Data final', null],
            'access_situation' => ['Situação', 'access_situations'],
            'date_from' => ['Data inicial', null], 'date_to' => ['Data final', null],
            'common_area_id' => ['Área comum', 'areas'], 'status' => ['Status', 'statuses'],
            'unit_id' => ['Unidade', 'units'], 'resident_id' => ['Morador', 'residents'],
            'type' => ['Tipo', 'types'], 'category' => ['Categoria', 'categories'],
            'priority' => ['Prioridade', 'priorities'], 'date_reference' => ['Referência do período', 'references'],
            'service_provider_id' => ['Prestador', 'providers'], 'origin' => ['Origem', 'origins'],
        ];
        $parts = [];
        foreach ($fields as $key => [$label, $source]) {
            $value = $filters[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            if ($source !== null) {
                foreach ($options[$source] ?? [] as $option) {
                    if ((string) $option['value'] === (string) $value) {
                        $value = $option['label'];
                        break;
                    }
                }
            } elseif (str_starts_with((string) $value, '20') && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value)) {
                $value = implode('/', array_reverse(explode('-', $value)));
            }
            $parts[] = $label.': '.$value;
        }

        return $parts === [] ? 'Nenhum filtro aplicado' : implode(' | ', $parts);
    }

    /** @param array<string, string> $columns
     * @param  Closure(): iterable<array<string, int|string|null>>  $rows
     */
    private function writeSheet(string $path, string $title, array $columns, Closure $rows, string $filters, string $issued): void
    {
        $xml = new XMLWriter;
        $xml->openUri($path);
        $xml->startDocument('1.0', 'UTF-8', 'yes');
        $xml->startElement('worksheet');
        $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $xml->writeRaw('<sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>');
        foreach (array_values($columns) as $index => $column) {
            $width = in_array($column, ['Descrição', 'Título', 'Ocorrência vinculada'], true) ? 38 : 23;
            $xml->writeRaw('<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>');
        }
        $xml->writeRaw('</cols><sheetData>');
        $this->writeRow($xml, 1, ['Relatório de '.$title], 1);
        $this->writeRow($xml, 2, ['Emitido em: '.$issued.' ('.config('app.timezone').')'], 2);
        $this->writeRow($xml, 3, ['Filtros: '.$filters], 2);
        $this->writeRow($xml, 5, array_values($columns), 3);
        $line = 6;
        foreach ($rows() as $row) {
            $cells = [];
            foreach ($columns as $field => $label) {
                $cells[] = (string) ($row[$field] ?? '');
            }
            $numericColumns = array_keys(array_values($columns), 'Custo', true);
            $this->writeRow($xml, $line++, $cells, 0, $numericColumns);
        }
        $xml->writeRaw('</sheetData><autoFilter ref="A5:'.$this->columnName(count($columns)).max(5, $line - 1).'"/><mergeCells count="3">');
        foreach ([1, 2, 3] as $number) {
            $xml->writeRaw('<mergeCell ref="A'.$number.':'.$this->columnName(count($columns)).$number.'"/>');
        }
        $xml->writeRaw('</mergeCells>');
        $xml->endElement();
        $xml->endDocument();
        $xml->flush();
    }

    /** @param list<string> $cells
     * @param  list<int>  $numericColumns
     */
    private function writeRow(XMLWriter $xml, int $number, array $cells, int $style, array $numericColumns = []): void
    {
        $xml->startElement('row');
        $xml->writeAttribute('r', (string) $number);
        if ($number === 3) {
            $xml->writeAttribute('ht', '32');
            $xml->writeAttribute('customHeight', '1');
        }
        foreach ($cells as $index => $value) {
            $xml->startElement('c');
            $xml->writeAttribute('r', $this->columnName($index + 1).$number);
            if (in_array($index, $numericColumns, true) && $value !== '' && is_numeric($value)) {
                $xml->writeAttribute('t', 'n');
                $xml->writeElement('v', $value);
                $xml->endElement();

                continue;
            }
            $xml->writeAttribute('t', 'inlineStr');
            $xml->writeAttribute('s', (string) $style);
            $xml->startElement('is');
            $xml->startElement('t');
            $xml->writeAttribute('xml:space', 'preserve');
            $xml->text(preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '');
            $xml->endElement();
            $xml->endElement();
            $xml->endElement();
        }
        $xml->endElement();
    }

    private function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + $index % 26).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    /** @return array<string, string> */
    private function packageFiles(): array
    {
        return [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Relatório" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="16"/><color rgb="FF0D2D5C"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF123768"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
        ];
    }
}
