<?php

namespace App\Tests\Service;

use App\Helper\NphParticipant;
use App\Service\Nph\NphParticipantSummaryService;
use App\Service\RdrApiService;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class NphParticipantSummaryServiceTest extends ServiceTestCase
{
    public function testGetParticipantById()
    {
        $mockParamsService = new ParameterBag([
            'rdr_disable_cache' => true,
            'cache_time' => 300,
            'ds_clean_up_limit' => 100,
        ]);
        $mockRdrApiService = $this->createMock(RdrApiService::class);
        $data = $this->getMockRdrResponseData();
        $mockRdrApiService->method('GQLPost')->willReturn($this->getGuzzleResponse($data));
        $nphParticipantService = new NphParticipantSummaryService($mockRdrApiService, $mockParamsService);

        // Test the getParticipantById method with a valid participant ID
        $result = $nphParticipantService->getParticipantById('123456');
        $this->assertInstanceOf(NphParticipant::class, $result);
        $this->assertEquals('123456', $result->participantNphId);
        $this->assertEquals('John', $result->firstName);

        // Test the getParticipantById method with an invalid participant ID
        $result = $nphParticipantService->getParticipantById('!@#$%^&*()');
        $this->assertFalse($result);
    }

    public function testGetAllParticipantDetailsById()
    {
        $mockRdrApiService = $this->createMock(RdrApiService::class);
        $data = $this->getMockRdrResponseData();
        $mockRdrApiService->method('GQLPost')->willReturn($this->getGuzzleResponse($data));
        $nphParticipantService = new NphParticipantSummaryService(
            $mockRdrApiService,
            static::getContainer()->get(ParameterBagInterface::class)
        );
        $result = $nphParticipantService->getAllParticipantDetailsById('123456');
        $this->assertEquals('123456', $result['participantNphId']);
        $this->assertEquals('John', $result['firstName']);
    }

    public function testSearch()
    {
        $mockRdrApiService = $this->createMock(RdrApiService::class);
        $data = $this->getMockRdrResponseData();
        $mockRdrApiService->method('GQLPost')->willReturn($this->getGuzzleResponse($data));
        $nphParticipantService = new NphParticipantSummaryService(
            $mockRdrApiService,
            static::getContainer()->get(ParameterBagInterface::class)
        );
        $params = [
            'lastName' => 'John',
            'firstName' => 'Doe',
            'dob' => "01/01/1990",
        ];
        $results = $nphParticipantService->search($params);
        foreach ($results as $result) {
            $this->assertInstanceOf(NphParticipant::class, $result);
        }
    }

    private function getGuzzleResponse($data): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], $data);
    }

    private function getMockRdrResponseData(): string
    {
        return '{"participant":{"edges":[{"node":{"DOB":"1990-01-01","biobankId":"T10000000011","firstName":"John","lastName":"Doe","nphPairedSite":"nph-site-test","participantNphId":"123456"}}],"resultCount":1,"totalCount":5}}';
    }
}
