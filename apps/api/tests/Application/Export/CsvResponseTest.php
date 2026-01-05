<?php

declare(strict_types=1);

namespace App\Tests\Application\Export;

use App\Controller\Api\BaseApiController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class CsvResponseTest extends TestCase
{
    public function testCsvResponseBuildsDownload(): void
    {
        $controller = new class extends BaseApiController {
            public function exposeCsv(array $headers, array $rows, string $filename): Response
            {
                return $this->csvResponse($headers, $rows, $filename);
            }
        };

        $response = $controller->exposeCsv(
            ['id', 'name'],
            [
                ['1', 'Anna'],
                ['2', 'Ben'],
            ],
            'sample.csv'
        );

        self::assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename="sample.csv"', $response->headers->get('Content-Disposition'));
        self::assertSame("id,name\n1,Anna\n2,Ben\n", $response->getContent());
    }
}
