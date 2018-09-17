<?php

namespace Baril\Smoothie\Tests;

use Baril\Smoothie\Tests\Models\Article;
use Baril\Smoothie\Carbon;

class CarbonTest extends TestCase
{
    public function test_fuzzy_date()
    {
        $fuzzyMonth = Carbon::create(2010, 0, 0);
        $fuzzyDay = Carbon::create(2010, 10, 0);
        $notFuzzy = Carbon::create(2010, 10, 3);

        $this->assertTrue($fuzzyMonth->isFuzzy());
        $this->assertTrue($fuzzyDay->isFuzzy());
        $this->assertFalse($notFuzzy->isFuzzy());

        $this->assertEquals(0, $fuzzyMonth->month);
        $this->assertEquals(0, $fuzzyMonth->day);
        $this->assertEquals(0, $fuzzyDay->day);

        $this->assertEquals('2010', $fuzzyMonth->format('d/m/Y', 'm/Y', 'Y'));
        $this->assertEquals('10/2010', $fuzzyDay->format('d/m/Y', 'm/Y', 'Y'));
        $this->assertEquals('03/10/2010', $notFuzzy->format('d/m/Y', 'm/Y', 'Y'));

        $fuzzyMonth->month = 12;
        $this->assertTrue($fuzzyMonth->isFuzzy());
        $this->assertEquals(12, $fuzzyMonth->month);

        $fuzzyMonth->day = 5;
        $this->assertFalse($fuzzyMonth->isFuzzy());
        $this->assertEquals(5, $fuzzyMonth->day);
    }

    /**
     * @dataProvider fuzzyDateFromSqlProvider
     */
    public function test_fuzzy_date_from_sql($value, $output)
    {
        $date = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        $this->assertEquals($output, $date->format('d/m/Y', 'm/Y', 'Y'));
        $this->assertEquals($output, $date->formatLocalized('%d/%m/%Y', '%m/%Y', '%Y'));
    }

    public function fuzzyDateFromSqlProvider()
    {
        return [
            'Ymd' => ['2010-10-01', '01/10/2010'],
            'Ym0' => ['2010-10-00', '10/2010'],
            'Y00' => ['2010-00-00', '2010'],
        ];
    }

    public function test_date_in_3_fields()
    {
        $article = factory(Article::class)->create();

        $article->publication_date = '2010-01-00';
        $this->assertEquals(2010, $article->publication_date_year);
        $this->assertEquals(1, $article->publication_date_month);
        $this->assertEquals(null, $article->publication_date_day);

        $article->save();
        $this->assertEquals('2010-01', $article->fresh()->publication_date->format('Y-m-d', 'Y-m', 'Y'));
    }
}
