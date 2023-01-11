<?php

declare(strict_types=1);

namespace OFXI4GC;

require 'vendor/autoload.php';

$pathToDb = '/app/database.gnucash';
$pathToOfx = '/app/input.ofx';
$debitAccountName = $argv[1];
$debitAccountType = $argv[2];
$creditAccountName = $argv[3];
$creditAccountType = $argv[4];

$runner = new Importer(
    $pathToDb,
    $pathToOfx,
    $debitAccountName,
    $debitAccountType,
    $creditAccountName,
    $creditAccountType
);
$runner->import();
