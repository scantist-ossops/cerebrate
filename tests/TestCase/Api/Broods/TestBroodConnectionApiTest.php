<?php

declare(strict_types=1);

namespace App\Test\TestCase\Api\Users;

use Cake\TestSuite\TestCase;
use App\Test\Fixture\AuthKeysFixture;
use App\Test\Fixture\BroodsFixture;
use App\Test\Helper\ApiTestTrait;
use App\Test\Helper\WireMockTestTrait;
use \WireMock\Client\WireMock;

class TestBroodConnectionApiTest extends TestCase
{
    use ApiTestTrait;
    use WireMockTestTrait;

    protected const ENDPOINT = '/api/v1/broods/testConnection';

    protected $fixtures = [
        'app.Organisations',
        'app.Individuals',
        'app.Roles',
        'app.Users',
        'app.AuthKeys',
        'app.Broods'
    ];

    public function testTestBroodConnection(): void
    {
        $this->setAuthToken(AuthKeysFixture::ADMIN_API_KEY);
        $this->initializeWireMock();
        $this->mockCerebrateStatusResponse();

        $url = sprintf('%s/%d', self::ENDPOINT, BroodsFixture::BROOD_WIREMOCK_ID);
        $this->get($url);

        $this->getWireMock()->verify(
            WireMock::getRequestedFor(WireMock::urlEqualTo('/instance/status.json'))
                ->withHeader('Content-Type', WireMock::equalTo('application/json'))
                ->withHeader('Authorization', WireMock::equalTo(BroodsFixture::BROOD_WIREMOCK_API_KEY))
        );

        $this->assertResponseOk();
        $this->assertResponseContains('"user": "wiremock"');
    }

    private function mockCerebrateStatusResponse(): \WireMock\Stubbing\StubMapping
    {
        return $this->getWireMock()->stubFor(
            WireMock::get(WireMock::urlEqualTo('/instance/status.json'))
                ->willReturn(WireMock::aResponse()
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody((string)json_encode([
                        "version" => "0.1",
                        "application" => "Cerebrate",
                        "user" => [
                            "id" => 1,
                            "username" => "wiremock",
                            "role" => [
                                "id" => 1
                            ]
                        ]
                    ])))
        );
    }
}
