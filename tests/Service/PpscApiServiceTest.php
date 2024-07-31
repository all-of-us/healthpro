<?php

namespace App\Tests\Service;

use App\Helper\PpscParticipant;
use App\HttpClient;
use App\Service\EnvironmentService;
use App\Service\Ppsc\PpscApiService;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class PpscApiServiceTest extends ServiceTestCase
{
    public function testGetAccessToken(): void
    {
        $mockParamsService = $this->createMock(ParameterBagInterface::class);
        $mockSessionService = $this->createMock(SessionInterface::class);
        $mockEnvService = $this->createMock(EnvironmentService::class);
        $mockClient = $this->createMock(HttpClient::class);
        $data = $this->getMockPpscAccessTokenData();
        $mockClient->method('request')->willReturn($this->getGuzzleResponse($data));
        $ppscApiService = new PpscApiService($mockParamsService, $mockSessionService, $mockEnvService);
        $ppscApiService->client = $mockClient;
        $result = $ppscApiService->getAccessToken();
        $this->assertEquals('123456789', $result);
    }

    public function testGetRequestDetailsById(): void
    {
        $mockParamsService = $this->createMock(ParameterBagInterface::class);
        $mockSessionService = $this->createMock(SessionInterface::class);
        $mockEnvService = $this->createMock(EnvironmentService::class);
        $mockClient = $this->createMock(HttpClient::class);
        $data = $this->getMockPpscRequestIdData();
        $mockClient->method('request')->willReturn($this->getGuzzleResponse($data));
        $ppscApiService = $this->getMockBuilder(PpscApiService::class)
            ->setConstructorArgs([$mockParamsService, $mockSessionService, $mockEnvService])
            ->onlyMethods(['getAccessToken'])
            ->getMock();
        $ppscApiService->client = $mockClient;
        $ppscApiService->method('getAccessToken')->willReturn('test_access_token');
        $result = $ppscApiService->getRequestDetailsById('123456789');
        $this->assertEquals('P000000123', $result->participantId);
        $this->assertEquals('test', $result->siteId);
    }

    public function testGetParticipantById(): void
    {
        $mockParamsService = $this->createMock(ParameterBagInterface::class);
        $mockSessionService = $this->createMock(SessionInterface::class);
        $mockEnvService = $this->createMock(EnvironmentService::class);
        $mockClient = $this->createMock(HttpClient::class);
        $data = $this->getMockPpscParticipantData();
        $mockClient->method('request')->willReturn($this->getGuzzleResponse($data));
        $ppscApiService = $this->getMockBuilder(PpscApiService::class)
            ->setConstructorArgs([$mockParamsService, $mockSessionService, $mockEnvService])
            ->onlyMethods(['getAccessToken'])
            ->getMock();
        $ppscApiService->client = $mockClient;
        $ppscApiService->method('getAccessToken')->willReturn('test_access_token');
        $result = $ppscApiService->getParticipantById('P000000123', '1');
        $this->assertInstanceOf(PpscParticipant::class, $result);
        $this->assertEquals('P000000123', $result->id);
        $this->assertEquals('John', $result->firstName);
    }

    public function testPost(): void
    {
        $mockParamsService = $this->createMock(ParameterBagInterface::class);
        $mockSessionService = $this->createMock(SessionInterface::class);
        $mockEnvService = $this->createMock(EnvironmentService::class);
        $mockClient = $this->createMock(HttpClient::class);
        $data = $this->getMockPostData();
        $mockClient->method('request')->willReturn($this->getGuzzleResponse($data));
        $ppscApiService = $this->getMockBuilder(PpscApiService::class)
            ->setConstructorArgs([$mockParamsService, $mockSessionService, $mockEnvService])
            ->onlyMethods(['getAccessToken'])
            ->getMock();
        $ppscApiService->client = $mockClient;
        $ppscApiService->method('getAccessToken')->willReturn('test_access_token');
        $result = $ppscApiService->post('/physical_measurements', new \stdClass());
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    private function getGuzzleResponse($data): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], $data);
    }

    private function getMockPpscAccessTokenData(): string
    {
        return '{"access_token": "123456789"}';
    }

    private function getMockPpscRequestIdData(): string
    {
        return '{"siteId": "test", "participantId": "P000000123"}';
    }

    private function getMockPpscParticipantData(): string
    {
        return '{"ageRange": null, "race": null, "sex": null, "deceasedStatus": null, "biospecimenSourceSite": null, "site": null, "dob": null, "organization": null, "isPediatric": null, "genderIdentity": null, "middleName": null, "lastName": "Qualtrics", "firstName": "John", "biobankId": "T000000156", "participantId": "P000000123"}';
    }

    private function getMockPostData(): string
    {
        return '[{"status": "success", "code": 200, "sf_measurments_id": "a3kBZ0000006dgaYAA"}]';
    }
}
