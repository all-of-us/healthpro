<?php

namespace App\Tests\Service;

use App\Helper\PpscParticipant;
use App\HttpClient;
use App\Service\EnvironmentService;
use App\Service\Ppsc\PpscApiService;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class PpscApiServiceTest extends ServiceTestCase
{
    public function testGetAccessToken(): void
    {
        $mockParamsService = $this->getPpscParams();
        $mockEnvService = $this->createMock(EnvironmentService::class);
        $mockLoggerService = $this->createMock(LoggerInterface::class);
        $mockClient = $this->createMock(HttpClient::class);
        $mockEnvService->method('getPpscEnv')->willReturn('qa');
        $data = $this->getMockPpscAccessTokenData();
        $mockClient->method('request')->willReturn($this->getGuzzleResponse($data));
        $ppscApiService = new PpscApiService($mockParamsService, $this->requestStack, $mockEnvService, $mockLoggerService);
        $ppscApiService->client = $mockClient;
        $result = $ppscApiService->getAccessToken();
        $this->assertEquals('123456789', $result);
    }

    public function testGetRequestDetailsById(): void
    {
        $mockParamsService = $this->getPpscParams();
        $mockEnvService = $this->createMock(EnvironmentService::class);
        $mockLoggerService = $this->createMock(LoggerInterface::class);
        $mockClient = $this->createMock(HttpClient::class);
        $mockEnvService->method('getPpscEnv')->willReturn('qa');
        $data = $this->getMockPpscRequestIdData();
        $mockClient->method('request')->willReturn($this->getGuzzleResponse($data));
        $ppscApiService = $this->getMockBuilder(PpscApiService::class)
            ->setConstructorArgs([$mockParamsService, $this->requestStack, $mockEnvService, $mockLoggerService])
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
        $mockParamsService = $this->getPpscParams();
        $mockEnvService = $this->createMock(EnvironmentService::class);
        $mockLoggerService = $this->createMock(LoggerInterface::class);
        $mockClient = $this->createMock(HttpClient::class);
        $mockEnvService->method('getPpscEnv')->willReturn('qa');
        $data = $this->getMockPpscParticipantData();
        $mockClient->method('request')->willReturn($this->getGuzzleResponse($data));
        $ppscApiService = $this->getMockBuilder(PpscApiService::class)
            ->setConstructorArgs([$mockParamsService, $this->requestStack, $mockEnvService, $mockLoggerService])
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
        $mockParamsService = $this->getPpscParams();
        $mockEnvService = $this->createMock(EnvironmentService::class);
        $mockLoggerService = $this->createMock(LoggerInterface::class);
        $mockClient = $this->createMock(HttpClient::class);
        $mockEnvService->method('getPpscEnv')->willReturn('qa');
        $data = $this->getMockPostData();
        $mockClient->method('request')->willReturn($this->getGuzzleResponse($data));
        $ppscApiService = $this->getMockBuilder(PpscApiService::class)
            ->setConstructorArgs([$mockParamsService, $this->requestStack, $mockEnvService, $mockLoggerService])
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

    private function getPpscParams(): ParameterBagInterface
    {
        return new ParameterBag([
            'ppsc_disable_cache' => true,
            'ds_clean_up_limit' => 100,
            'qa_ppsc_endpoint' => 'https://ppsc.example/',
            'qa_ppsc_token_url' => 'https://ppsc.example/oauth/token',
            'qa_ppsc_client_id' => 'client-id',
            'qa_ppsc_client_secret' => 'client-secret',
            'qa_ppsc_grant_type' => 'client_credentials',
            'qa_ppsc_scope' => 'scope',
        ]);
    }
}
