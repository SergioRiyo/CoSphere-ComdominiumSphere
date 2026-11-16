<?php

namespace App\Services;

use App\Models\FinancialCharge;
use Symfony\Component\HttpFoundation\Response;

class SimulatedBoletoService
{
    public function download(FinancialCharge $charge): Response
    {
        abort_unless($charge->status === 'open', 404);
        $charge->load('unit');
        $amount = number_format($charge->amount_cents / 100, 2, ',', '.');
        $lines = [
            ['CoSphere - DOCUMENTO FINANCEIRO SIMULADO', 18],
            ['BOLETO SIMULADO - NÃO VÁLIDO PARA PAGAMENTO', 14],
            ['Este documento não permite efetuar pagamento.', 11],
            ['Cobrança: #'.$charge->id, 12],
            ['Unidade: '.$charge->unit->number.($charge->unit->block ? ' - Bloco '.$charge->unit->block : ''), 12],
        ];
        foreach (mb_str_split('Descrição: '.$charge->description, 65) as $line) {
            $lines[] = [$line, 12];
        }
        $lines[] = ['Valor: R$ '.$amount, 12];
        $lines[] = ['Vencimento: '.$charge->due_date->format('d/m/Y'), 12];
        $lines[] = ['Emitido em: '.now()->format('d/m/Y H:i'), 10];
        $content = '';
        $y = 780;
        foreach ($lines as [$text, $size]) {
            $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', (string) $text) ?: '';
            $encoded = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $encoded);
            $content .= 'BT /F1 '.$size.' Tf 45 '.$y.' Td ('.$encoded.") Tj ET\n";
            $y -= $size === 18 ? 40 : 32;
        }
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".count($offsets)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size '.count($offsets)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="boleto-simulado-'.$charge->id.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
