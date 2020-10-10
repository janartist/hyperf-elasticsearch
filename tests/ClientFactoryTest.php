<?php

declare(strict_types=1);

namespace JanartistTest\Elasticsearch;

use Chungou\Elasticsearch\Client;
use Hyperf\Utils\ApplicationContext;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ClientFactoryTest extends TestCase
{
    public function testClientBuilderFactoryCreate()
    {
        $clientFactory =  ApplicationContext::getContainer()->get(Client::class);

        $client = $clientFactory->create();

        $this->assertInstanceOf(\Elasticsearch\Client::class, $client);
    }
}
