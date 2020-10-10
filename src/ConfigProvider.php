<?php

declare(strict_types=1);

namespace Janartist\Elasticsearch;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for elasticsearch.',
                    'source' => __DIR__ . '/../publish/elasticsearch.php',
                    'destination' => BASE_PATH . '/config/autoload/elasticsearch.php',
                ]
            ]
        ];
    }
}
