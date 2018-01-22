<?php
passthru(sprintf(
    'rm -rf "%s/var/cache"',
    __DIR__
));

require __DIR__.'/../vendor/autoload.php';
