<?php declare(strict_types=1);

use Danger\Config;
use Danger\Rule\CommitRegex;

return (new Config())
    ->useRule(new CommitRegex('^(feat|fix|chore|docs|style|refactor|perf|test)(\(.+\))?: .+^', 'Commit message does not follow conventional commit format. Please take a look to the following [specification](https://www.conventionalcommits.org/en/v1.0.0/#specification)'))
;