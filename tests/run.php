<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/unit/helpers_test.php';
require __DIR__ . '/contract/application_contract_test.php';

$runner = new TestRunner();
registerHelperTests($runner);
registerApplicationContractTests($runner);
exit($runner->finish());

