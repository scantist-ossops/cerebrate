<?php

declare(strict_types=1);

namespace App\Test\TestCase\Api\Users;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use App\Test\Fixture\AuthKeysFixture;
use App\Test\Fixture\OrganisationsFixture;
use App\Test\Fixture\TagsTagsFixture;
use App\Test\Helper\ApiTestTrait;

class TagOrganisationApiTest extends TestCase
{
    use IntegrationTestTrait;
    use ApiTestTrait;

    protected const ENDPOINT = '/api/v1/organisations/tag';

    protected $fixtures = [
        'app.TagsTags',
        'app.Organisations',
        'app.TagsTaggeds',
        'app.Individuals',
        'app.Roles',
        'app.Users',
        'app.AuthKeys'
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->initializeValidator(APP . '../webroot/docs/openapi.yaml');
    }

    public function testTagOrganisation(): void
    {
        $this->setAuthToken(AuthKeysFixture::ADMIN_API_KEY);

        $url = sprintf('%s/%d', self::ENDPOINT, OrganisationsFixture::ORGANISATION_A_ID);
        $this->post(
            $url,
            [
                'tag_list' => "[\"red\"]"
            ]
        );

        $this->assertResponseOk();
        $this->assertDbRecordExists(
            'TagsTagged',
            [
                'tag_id' => TagsTagsFixture::TAG_RED_ID,
                'fk_id' => OrganisationsFixture::ORGANISATION_A_ID,
                'fk_model' => 'Organisations'
            ]
        );
        //TODO: $this->assertRequestMatchesOpenApiSpec();
        $this->assertResponseMatchesOpenApiSpec($url, 'post');
    }

    public function testTagOrganisationNotAllowedAsRegularUser(): void
    {
        $this->setAuthToken(AuthKeysFixture::REGULAR_USER_API_KEY);

        $url = sprintf('%s/%d', self::ENDPOINT, OrganisationsFixture::ORGANISATION_A_ID);
        $this->post(
            $url,
            [
                'tag_list' => "[\"green\"]"
            ]
        );

        $this->assertResponseCode(405);
        $this->assertDbRecordNotExists(
            'TagsTagged',
            [
                'tag_id' => TagsTagsFixture::TAG_GREEN_ID,
                'fk_id' => OrganisationsFixture::ORGANISATION_A_ID,
                'fk_model' => 'Organisations'
            ]
        );
        //TODO: $this->assertRequestMatchesOpenApiSpec();
        $this->assertResponseMatchesOpenApiSpec($url, 'post');
    }
}
