<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Invoice\Renderer;

use App\Entity\InvoiceDocument;
use App\Invoice\InvoiceItem;
use App\Invoice\InvoiceItemHydrator;
use App\Invoice\InvoiceModel;
use App\Invoice\InvoiceModelHydrator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class DebugRendererTest extends TestCase
{
    use RendererTestTrait;

    public function getTestModel()
    {
        yield [$this->getInvoiceModel(), '1,947.99', 5, 5, 1, 2, 2, true, [['entry.meta.foo-timesheet'], ['entry.meta.foo-timesheet2'], ['entry.meta.foo-timesheet'], ['entry.meta.foo-timesheet3']]];
        yield [$this->getInvoiceModelOneEntry(), '293.27', 1, 1, 0, 1, 0, false, []];
    }

    /**
     * @dataProvider getTestModel
     */
    public function testRender(InvoiceModel $model, $expectedRate, $expectedRows, $expectedDescriptions, $expectedUser1, $expectedUser2, $expectedUser3, $hasProject, $metaFields = [])
    {
        $itemHydrator = new class() implements InvoiceItemHydrator {
            public function setInvoiceModel(InvoiceModel $model)
            {
            }

            public function hydrate(InvoiceItem $item): array
            {
                return ['testFromItemHydrator' => 'foo'];
            }
        };
        $model->addItemHydrator($itemHydrator);

        $modelHydrator = new class() implements InvoiceModelHydrator {
            public function hydrate(InvoiceModel $model): array
            {
                return ['testFromModelHydrator' => 'foo'];
            }
        };
        $model->addModelHydrator($modelHydrator);

        $document = new InvoiceDocument(new \SplFileInfo(__DIR__ . '/DebugRenderer.php'));
        $sut = new DebugRenderer();
        /** @var Response $response */
        $response = $sut->render($document, $model);
        $data = json_decode($response->getContent(), true);

        $this->assertModelStructure($data['model'], $hasProject);
        $rows = $data['entries'];
        $this->assertEquals($expectedRows, count($rows));

        $i = 0;
        foreach ($rows as $row) {
            $meta = isset($metaFields[$i]) ? $metaFields[$i++] : [];
            $this->assertEntryStructure($row, $meta);
        }

        $begin = $model->getQuery()->getBegin();
        self::assertEquals($begin->format('m'), $data['model']['query.month_number']);
        self::assertEquals($begin->format('d'), $data['model']['query.day']);
        // TODO check values or formats?
    }

    protected function assertModelStructure(array $model, $hasProject = true)
    {
        $keys = [
            'invoice.due_date',
            'invoice.date',
            'invoice.number',
            'invoice.currency',
            'invoice.currency_symbol',
            'invoice.vat',
            'invoice.tax',
            'invoice.tax_nc',
            'invoice.tax_plain',
            'invoice.total_time',
            'invoice.duration_decimal',
            'invoice.total',
            'invoice.total_nc',
            'invoice.total_plain',
            'invoice.subtotal',
            'invoice.subtotal_nc',
            'invoice.subtotal_plain',
            'template.name',
            'template.company',
            'template.address',
            'template.title',
            'template.payment_terms',
            'template.due_days',
            'template.vat_id',
            'template.contact',
            'template.payment_details',
            'query.begin',
            'query.day',
            'query.end',
            'query.month',
            'query.month_number',
            'query.year',
            'customer.id',
            'customer.address',
            'customer.name',
            'customer.contact',
            'customer.company',
            'customer.vat',
            'customer.country',
            'customer.number',
            'customer.homepage',
            'customer.comment',
            'customer.meta.foo-customer',
            'activity.id',
            'activity.name',
            'activity.comment',
            'activity.meta.foo-activity',
            'user.alias',
            'user.email',
            'user.name',
            'user.title',
            'user.meta.hello',
            'user.meta.kitty',
            'testFromModelHydrator'
        ];

        if ($hasProject) {
            $keys = array_merge($keys, [
                'project.id',
                'project.name',
                'project.comment',
                'project.order_date',
                'project.order_number',
                'project.meta.foo-project',
                'project.start_date',
                'project.end_date',
                'project.budget_money',
                'project.budget_money_nc',
                'project.budget_money_plain',
                'project.budget_time',
                'project.budget_time_decimal',
                'project.budget_time_minutes',
            ]);
        }

        $givenKeys = array_keys($model);
        sort($keys);
        sort($givenKeys);

        $this->assertEquals($keys, $givenKeys);
    }

    protected function assertEntryStructure(array $model, array $metaFields)
    {
        $keys = [
            'entry.row',
            'entry.description',
            'entry.amount',
            'entry.rate',
            'entry.rate_nc',
            'entry.rate_plain',
            'entry.total',
            'entry.total_nc',
            'entry.total_plain',
            'entry.currency',
            'entry.duration',
            'entry.duration_decimal',
            'entry.duration_minutes',
            'entry.begin',
            'entry.begin_time',
            'entry.begin_timestamp',
            'entry.end',
            'entry.end_time',
            'entry.end_timestamp',
            'entry.date',
            'entry.user_id',
            'entry.user_name',
            'entry.user_alias',
            'entry.user_title',
            'entry.activity',
            'entry.activity_id',
            'entry.activity.meta.foo-activity',
            'entry.project',
            'entry.project_id',
            'entry.project.meta.foo-project',
            'entry.customer',
            'entry.customer_id',
            'entry.customer.meta.foo-customer',
            'entry.category',
            'entry.type',
            'testFromItemHydrator'
        ];

        $keys = array_merge($keys, $metaFields);

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $model);
        }

        $expectedKeys = array_merge([], $keys);
        sort($expectedKeys);
        $givenKeys = array_keys($model);
        sort($givenKeys);

        $this->assertEquals($expectedKeys, $givenKeys);
        $this->assertEquals(count($keys), count($givenKeys));
    }
}
