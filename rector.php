<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_84,
       // \Rector\Set\ValueObject\SetList::CODE_QUALITY
    ]);
    //$rectorConfig->rules([\Rector\Transform\Rector\ClassMethod\ReturnTypeWillChangeRector::class]);
    $rectorConfig->paths(array_map(static fn(string $path): string => __DIR__ . '/' . $path, ['admin', 'app', 'storage', '.']));
    $rectorConfig->skip([__DIR__ . '/vendor']);
};