<?php

declare(strict_types=1);

namespace OFXI4GC;

use OfxParser\Parser;

/**
 * Parses OFX file and saves transactions to database.
 */
class Importer
{
    private string $pathToFile;
    private string $pathToDb;
    private string $creditAccountName;
    private string $creditAccountType;
    private string $debitAccountName;
    private string $debitAccountType;

    public function __construct(
        string $pathToDb,
        string $pathToFile,
        string $debitAccountName,
        string $debitAccountType,
        string $creditAccountName,
        string $creditAccountType
    ) {
        $this->pathToFile = $pathToFile;
        $this->pathToDb = $pathToDb;
        $this->creditAccountName = $creditAccountName;
        $this->creditAccountType = $creditAccountType;
        $this->debitAccountName = $debitAccountName;
        $this->debitAccountType = $debitAccountType;
    }

    public function import(): void
    {
        $ofxParser = new Parser();
        $ofx = $ofxParser->loadFromFile($this->pathToFile);
        $bankAccount = $ofx->bankAccounts[0];
        $transactions = $bankAccount->statement->transactions;

        $dbWrapper = new DatabaseHelper($this->pathToDb);
        $creditAccountGuid = $dbWrapper->getAccountGuid(
            $this->creditAccountName,
            $this->creditAccountType
        );
        $debitAccountGuid = $dbWrapper->getAccountGuid(
            $this->debitAccountName,
            $this->debitAccountType
        );

        foreach ($transactions as $transaction) {
            $dbWrapper->saveCompleteTransaction($transaction, $creditAccountGuid, $debitAccountGuid);
        }
    }
}
