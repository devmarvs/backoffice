<?php

declare(strict_types=1);

namespace App\Application\Billing;

use Dompdf\Dompdf;

final class InvoicePdfRenderer
{
    public function render(array $invoice, array $client, array $lines): string
    {
        $rows = '';
        foreach ($lines as $line) {
            $quantity = number_format((float) $line['quantity'], 2, '.', '');
            $unitPrice = number_format(((int) $line['unit_price_cents']) / 100, 2, '.', '');
            $rows .= sprintf(
                '<tr><td>%s</td><td style="text-align:right;">%s</td><td style="text-align:right;">%s</td></tr>',
                htmlspecialchars((string) $line['description'], ENT_QUOTES),
                $quantity,
                $unitPrice
            );
        }

        $amount = number_format(((int) $invoice['amount_cents']) / 100, 2, '.', '');

        $html = sprintf(
            '<html><head><style>
                body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #222; }
                h1 { font-size: 18px; margin-bottom: 8px; }
                table { width: 100%%; border-collapse: collapse; margin-top: 16px; }
                th, td { padding: 8px 4px; border-bottom: 1px solid #ddd; }
                th { text-align: left; }
                .total { text-align: right; font-weight: bold; }
                .meta { margin-top: 8px; color: #555; }
            </style></head>
            <body>
                <h1>Invoice Draft #%s</h1>
                <div class="meta">Client: %s</div>
                <div class="meta">Status: %s</div>
                <table>
                    <thead>
                        <tr><th>Description</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Unit</th></tr>
                    </thead>
                    <tbody>%s</tbody>
                </table>
                <p class="total">Total: %s %s</p>
            </body></html>',
            htmlspecialchars((string) $invoice['id'], ENT_QUOTES),
            htmlspecialchars((string) ($client['name'] ?? 'Unknown'), ENT_QUOTES),
            htmlspecialchars((string) $invoice['status'], ENT_QUOTES),
            $rows,
            htmlspecialchars((string) $invoice['currency'], ENT_QUOTES),
            $amount
        );

        $dompdf = new Dompdf(['defaultFont' => 'Helvetica']);
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }
}
