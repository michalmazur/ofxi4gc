<?php

declare(strict_types=1);

namespace Tests;

use OFXI4GC\Importer;
use PDO;
use PHPUnit\Framework\TestCase;

class ImporterTest extends TestCase
{
    protected function setUp(): void
    {
        copy(__DIR__ . '/db/example.sqlite3.gnucash', __DIR__ . '/db/example.sqlite3.gnucash.temp');
    }

    protected function tearDown(): void
    {
        @unlink(__DIR__ . '/db/example.sqlite3.gnucash.temp');
    }

    /**
     * Verify that an OFX file can be imported into the database.
     */
    public function testImport(): void
    {
        copy(__DIR__ . '/db/example.sqlite3.gnucash', __DIR__ . '/db/example.sqlite3.gnucash.temp');

        $sut = new Importer(
            __DIR__ . '/db/example.sqlite3.gnucash.temp',
            __DIR__ . '/ofx/example.ofx',
            'Miscellaneous',
            'EXPENSE',
            'Credit Card',
            'CREDIT'
        );
        $sut->import();

        $db = new PDO('sqlite:' . __DIR__ . '/db/example.sqlite3.gnucash.temp');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $statement = $db->prepare('select * from transactions where num = :num');
        $statement->execute([':num' => '2016022924445006060000395827477']);
        $this->assertNotFalse($statement->fetch());
    }
}
