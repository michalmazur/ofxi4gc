<?php

declare(strict_types=1);

namespace Tests;

use DateTime;
use OFXI4GC\DatabaseHelper;
use OfxParser\Entities\Transaction;
use PDO;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class DatabaseHelperTest extends TestCase
{
    private DatabaseHelper $sut;

    protected function setUp(): void
    {
        copy(__DIR__ . '/db/example.sqlite3.gnucash', __DIR__ . '/db/example.sqlite3.gnucash.temp');
        $this->sut = new DatabaseHelper(__DIR__ . '/db/example.sqlite3.gnucash.temp');
    }

    protected function tearDown(): void
    {
        @unlink(__DIR__ . '/db/example.sqlite3.gnucash.temp');
    }

    /**
     * Verify that a transaction can be found by its ID and amount.
     */
    public function testTransactionExistsShouldFindTransactionNumber()
    {
        copy(__DIR__ . '/db/single-transaction.sqlite3.gnucash', __DIR__ . '/db/example.sqlite3.gnucash.temp');
        $this->sut = new DatabaseHelper(__DIR__ . '/db/example.sqlite3.gnucash.temp');

        $result = $this->sut->transactionExists('T1', 14.99);
        $this->assertTrue($result);
    }

    /**
     * Verify that a transaction cannot be found when it does not exist.
     */
    public function testTransactionExistsShouldNotFindTransactionNumber()
    {
        $result = $this->sut->transactionExists('foo', 50);
        $this->assertFalse($result);
    }

    /**
     * Verify that a transaction is properly recorded in the database.
     */
    public function testShouldRecordTransaction()
    {
        $transaction = new Transaction();
        $transaction->amount = 50;
        $transaction->name = 'test description';
        $transaction->uniqueId = 'foo';
        $transaction->date = new DateTime('2020-12-30 23:59:59');

        $transactionGuid = Uuid::uuid4();
        $this->sut->insertTransaction($transactionGuid, $transaction);
        $this->assertTrue(true);

        $db = new PDO('sqlite:' . __DIR__ . '/db/example.sqlite3.gnucash.temp');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $statement = $db->prepare('select * from transactions where num = :num');
        $statement->execute([':num' => 'foo']);
        $result = $statement->fetch();

        $this->assertSame('20201230235959', $result['post_date']);
        $this->assertSame('20201230235959', $result['enter_date']);
        $this->assertSame('Test Description', $result['description']); // title case
        $this->assertSame('f37b7a3767a44740a8efab386b88ed9c', $result['currency_guid']);
        $this->assertSame($transactionGuid->getHex()->toString(), $result['guid']);
    }

    /**
     * Verify that the correct currency GUID is returned.
     */
    public function testGetCurrencyGuid(): void
    {
        $this->assertSame('f37b7a3767a44740a8efab386b88ed9c', $this->sut->getCurrencyGuid('USD'));
    }

    /**
     * Verify that a split is properly recorded in the database.
     */
    public function testInsertSplit(): void
    {
        $transactionGuid = Uuid::uuid4();
        $accountGuid = Uuid::uuid4();
        $this->sut->insertSplit($transactionGuid, $accountGuid, 99.99);

        $db = new PDO('sqlite:' . __DIR__ . '/db/example.sqlite3.gnucash.temp');
        $statement = $db->prepare('select * from splits where tx_guid = :tx_guid');
        $statement->execute([':tx_guid' => $transactionGuid->getHex()->toString()]);
        $result = $statement->fetch();

        $this->assertSame($accountGuid->getHex()->toString(), $result['account_guid']);
        $this->assertSame('', $result['memo']);
        $this->assertSame('', $result['action']);
        $this->assertSame('n', $result['reconcile_state']);
        $this->assertSame(null, $result['reconcile_date']);
        $this->assertSame(9999, $result['value_num']);
        $this->assertSame(100, $result['value_denom']);
        $this->assertSame(9999, $result['quantity_num']);
        $this->assertSame(100, $result['quantity_denom']);
        $this->assertSame(null, $result['lot_guid']);
    }

    /**
     * Verify that posting date of a transaction is properly recorded in the database.
     */
    public function testInsertSlotDatePosted(): void
    {
        $transactionGuid = Uuid::uuid4();
        $this->sut->insertSlotDatePosted($transactionGuid, new DateTime('2020-12-30 23:59:59'));

        $db = new PDO('sqlite:' . __DIR__ . '/db/example.sqlite3.gnucash.temp');
        $statement = $db->prepare('select * from slots where obj_guid = :obj_guid');
        $statement->execute([':obj_guid' => $transactionGuid->getHex()->toString()]);
        $result = $statement->fetch();

        $this->assertSame('date-posted', $result['name']);
        $this->assertSame(10, $result['slot_type']);
        $this->assertSame(0, $result['int64_val']);
        $this->assertSame(null, $result['string_val']);
        $this->assertSame(0.0, $result['double_val']);
        $this->assertSame(null, $result['timespec_val']);
        $this->assertSame(null, $result['guid_val']);
        $this->assertSame(0, $result['numeric_val_num']);
        $this->assertSame(1, $result['numeric_val_denom']);
        $this->assertSame('20201230', $result['gdate_val']);
    }

    /**
     * Verify that the correct account GUID is returned.
     */
    public function testGetAccountGuid(): void
    {
        $result = $this->sut->getAccountGuid('Books', 'EXPENSE');
        $this->assertSame('71c27ee817b3459aa4aa1bfca5835349', $result->getHex()->toString());
    }

    /**
     * Verify that a complete transaction (consisting of a transaction,
     * two splits, and a posting date slot) is recorded in the database.
     */
    public function testSaveCompleteTransaction(): void
    {
        $transaction = new Transaction();
        $transaction->amount = 50;
        $transaction->name = 'test description';
        $transaction->uniqueId = 'foo';
        $transaction->date = new DateTime('2020-12-30 23:59:59');
        $creditAccountGuid = $this->sut->getAccountGuid('Credit Card', 'CREDIT');
        $debitAccountGuid = $this->sut->getAccountGuid('Books', 'EXPENSE');
        $this->assertTrue(
            $this->sut->saveCompleteTransaction($transaction, $creditAccountGuid, $debitAccountGuid)
        );

        $db = new PDO('sqlite:' . __DIR__ . '/db/example.sqlite3.gnucash.temp');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $transactionStatement = $db->prepare('select count(*) from transactions where num = :num');
        $transactionStatement->execute([':num' => 'foo']);
        $this->assertSame(1, $transactionStatement->fetchColumn());

        $splitStatement = $db->prepare(
            'select count(*) from splits s where tx_guid = (select guid from transactions where num = :num)'
        );
        $splitStatement->execute([':num' => $transaction->uniqueId]);
        $this->assertSame(2, $splitStatement->fetchColumn());

        $slotStatement =  $db->prepare(
            'select count(*) from slots s where obj_guid = (select guid from transactions where num = :num)'
        );
        $slotStatement->execute([':num' => $transaction->uniqueId]);
        $this->assertSame(1, $slotStatement->fetchColumn());
    }
}
