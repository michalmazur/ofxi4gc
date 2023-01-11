<?php

declare(strict_types=1);

namespace OFXI4GC;

use DateTime;
use InvalidArgumentException;
use OfxParser\Entities\Transaction;
use PDO;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class DatabaseHelper
{
    private PDO $db;

    public function __construct(string $pathToDatabase)
    {
        $this->db = new PDO('sqlite:' . $pathToDatabase);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Get the GUID of the currency from the `commodities` table.
     */
    public function getCurrencyGuid(string $mnemonic): string
    {
        if (trim($mnemonic) === '') {
            throw new InvalidArgumentException();
        }

        $statement = $this->db->prepare('select guid from commodities where mnemonic = :mnemonic');
        $statement->execute([':mnemonic' => $mnemonic]);
        $result = $statement->fetchColumn();

        if ($result === false) {
            throw new InvalidArgumentException('Currency not found');
        }

        return $result;
    }

    /**
     * Get the GUID of the account from the `accounts` table.
     */
    public function getAccountGuid(string $accountName, string $accountType): UuidInterface
    {
        $statement = $this->db->prepare(
            'select guid from accounts where name = :name and account_type = :type and placeholder = 0'
        );
        $statement->execute([':name' => $accountName, ':type' => $accountType]);
        $result = $statement->fetchColumn();
        return Uuid::fromString($result);
    }

    /**
     * Check if a transaction exists based on its number (FITID) and amount (TRNAMT).
     */
    public function transactionExists(string $transactionNumber, float $amount): bool
    {
        $statement = $this->db->prepare(
            'select count(*) from transactions t
            left outer join splits s on s.tx_guid = t.guid 
            where num = :num and value_num = :amount'
        );
        $statement->execute([':num' => $transactionNumber, ':amount' => round($amount * 100)]);
        $result = $statement->fetchColumn();
        return $result !== 0;
    }

    /**
     * Insert a record into the `transactions` table.
     */
    public function insertTransaction(UuidInterface $transactionGuid, Transaction $transaction)
    {
        $statement = $this->db->prepare(
            'insert into transactions (guid, currency_guid, num, post_date, enter_date, description)
            values (:guid, :currency_guid, :num, :post_date, :enter_date, :description)'
        );
        $statement->execute([
            ':guid' => $transactionGuid->getHex()->toString(),
            ':currency_guid' => $this->getCurrencyGuid('USD'),
            ':num' => $transaction->uniqueId,
            ':post_date' => $transaction->date->format('YmdHis'),
            ':enter_date' => $transaction->date->format('YmdHis'),
            ':description' => ucwords(strtolower($transaction->name)),
        ]);
    }

    /**
     * Insert a record into the `splits` table.
     */
    public function insertSplit(UuidInterface $transactionGuid, UuidInterface $accountGuid, float $amount): void
    {
        $statement = $this->db->prepare(
            'insert into splits (guid, tx_guid, account_guid, memo, action, reconcile_state, reconcile_date, 
                value_num, value_denom, quantity_num, quantity_denom, lot_guid) 
            values (:guid, :tx_guid, :account_guid, :memo, :action, :reconcile_state, :reconcile_date,
                :value_num, :value_denom, :quantity_num, :quantity_denom, :lot_guid)'
        );

        $statement->execute(
            [
                ':guid' => Uuid::uuid4()->getHex()->toString(),
                ':tx_guid' => $transactionGuid->getHex()->toString(),
                ':account_guid' => $accountGuid->getHex()->toString(),
                ':memo' => '',
                ':action' => '',
                ':reconcile_state' => 'n',
                ':reconcile_date' => null,
                ':value_num' => round($amount * 100),
                ':value_denom' => 100,
                ':quantity_num' => round($amount * 100),
                ':quantity_denom' => 100,
                ':lot_guid' => null,
            ]
        );
    }

    /**
     * Insert the posting date of a transaction into the `slots` table.
     */
    public function insertSlotDatePosted(UuidInterface $transactionGuid, DateTime $dateTime): void
    {
        $statement = $this->db->prepare(
            'insert into slots (
                id, obj_guid, name, slot_type, int64_val, string_val, 
                double_val, timespec_val, guid_val, numeric_val_num, numeric_val_denom, gdate_val
            ) values (
                (select max(id) + 1 from slots), :obj_guid, :name, :slot_type, :int64_val, :string_val,
                :double_val, :timespec_val, :guid_val, :numeric_val_num, :numeric_val_denom, :gdate_val
            )'
        );

        $statement->execute(
            [
                ':obj_guid' => $transactionGuid->getHex()->toString(),
                ':name' => 'date-posted',
                ':slot_type' => 10,
                ':int64_val' => 0,
                ':string_val' => null,
                ':double_val' => 0,
                ':timespec_val' => null,
                ':guid_val' => null,
                ':numeric_val_num' => 0,
                ':numeric_val_denom' => 1,
                ':gdate_val' => $dateTime->format('Ymd'),
            ]
        );
    }


    /**
     * Record a transaction (complete with splits and posting date) in the database.
     */
    public function saveCompleteTransaction(
        Transaction $transaction,
        UuidInterface $creditAccountGuid,
        UuidInterface $debitAccountGuid
    ): bool {
        if ($this->transactionExists($transaction->uniqueId, $transaction->amount)) {
            return false;
        }

        $guid = Uuid::uuid4();
        $this->db->beginTransaction();
        $this->insertTransaction($guid, $transaction);
        $this->insertSplit($guid, $creditAccountGuid, $transaction->amount);
        $this->insertSplit($guid, $debitAccountGuid, -$transaction->amount);
        $this->insertSlotDatePosted($guid, $transaction->date);
        $this->db->commit();
        echo $transaction->date->format('Y-m-d') . ' ' . $transaction->name . "\n";
        return true;
    }
}
