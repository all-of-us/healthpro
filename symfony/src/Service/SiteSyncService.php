<?php

namespace App\Service;

use App\Entity\Awardee;
use App\Entity\Organization;
use App\Entity\Site;
use Doctrine\ORM\EntityManagerInterface;
use App\Audit\Log;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SiteSyncService
{
    private $rdrApiService;
    private $em;
    private $env;
    private $loggerService;
    private $params;
    private $normalizer;
    private $orgEndpoint = 'rdr/v1/Awardee?_inactive=true';
    private $entries;

    public function __construct(
        RdrApiService $rdrApiService,
        EntityManagerInterface $em,
        EnvironmentService $env,
        LoggerService $loggerService,
        ParameterBagInterface $params,
        NormalizerInterface $normalizer
    ) {
        $this->rdrApiService = $rdrApiService;
        $this->em = $em;
        $this->env = $env;
        $this->loggerService = $loggerService;
        $this->params = $params;
        $this->normalizer = $normalizer;
    }

    private function getAwardeeEntriesFromRdr()
    {
        if (!is_null($this->entries)) {
            return $this->entries;
        }
        $response = $this->rdrApiService->get($this->orgEndpoint);
        $responseObject = json_decode($response->getBody()->getContents());
        if ($responseObject && !empty($responseObject->entry)) {
            $this->entries = $responseObject->entry;
            return $this->entries;
        }
        return [];
    }

    private function getSitesFromDb()
    {
        $sitesRepository = $this->em->getRepository(Site::class);
        $sites = $sitesRepository->findBy(['deleted' => 0]);
        $sitesById = [];
        foreach ($sites as $site) {
            $sitesById[$site->getGoogleGroup()] = $site;
        }
        return $sitesById;
    }

    private static function getSiteSuffix($site)
    {
        return str_replace(\App\Security\User::SITE_PREFIX, '', $site);
    }

    public function dryRun()
    {
        return $this->sync(true);
    }

    public function sync($preview = false)
    {
        $sitesCount = 0;
        $created = [];
        $modified = [];
        $existingSites = $this->getSitesFromDb();
        $deleted = array_keys($existingSites); // add everything to the deleted array, then remove as we find them
        $entries = $this->getAwardeeEntriesFromRdr();
        foreach ($entries as $entry) {
            $awardee = $entry->resource;
            if (!isset($awardee->organizations) || !is_array($awardee->organizations)) {
                continue;
            }
            foreach ($awardee->organizations as $organization) {
                if (!isset($organization->sites) || !is_array($organization->sites)) {
                    continue;
                }
                foreach ($organization->sites as $site) {
                    $sitesCount++;
                    $existingArray = false;
                    $primaryId = null;
                    $siteId = self::getSiteSuffix($site->id);
                    if (array_key_exists($siteId, $existingSites)) {
                        $existingArray = $this->normalizer->normalize($existingSites[$siteId]);
                        $siteData = $existingSites[$siteId];
                        $primaryId = $siteData->getId();
                    } else {
                        $siteData = new Site();
                    }
                    $siteData->setStatus(isset($site->enrollingStatus) && $site->enrollingStatus === 'ACTIVE' ? true : false);
                    $siteData->setName($site->displayName);
                    $siteData->setGoogleGroup($siteId); // backwards compatibility
                    $siteData->setOrganization($awardee->id); // backwards compatibility
                    $siteData->setSiteId($siteId);
                    $siteData->setOrganizationId($organization->id);
                    $siteData->setAwardeeId($awardee->id);
                    if ($this->env->isProd()) {
                        $siteData->setMayolinkAccount(isset($site->mayolinkClientNumber) ? $site->mayolinkClientNumber : null);
                    } elseif ($this->env->isStable()) {
                        if (strtolower($awardee->type) === 'dv') {
                            // For diversion pouch site set hpo mayo account number
                            if (isset($site->siteType) && $this->params->has('diversion_pouch_site') && $site->siteType === $this->params->get('diversion_pouch_site')) {
                                $checkMayoAccountType = 'dv';
                                $setMayoAccountType = 'hpo';
                            } else {
                                $checkMayoAccountType = 'hpo';
                                $setMayoAccountType = 'dv';
                            }
                        } else {
                            $checkMayoAccountType = 'dv';
                            $setMayoAccountType = 'hpo';
                        }
                        // Set to default hpo/dv account number if existing mayo account number is empty or equal to default dv/hpo account number
                        if (empty($existingArray) || (empty($existingArray['mayolinkAccount']) || ($existingArray['mayolinkAccount'] === $this->params->get('ml_account_' . $checkMayoAccountType)))) {
                            $siteData->setMayolinkAccount($this->params->get('ml_account_' . $setMayoAccountType));
                        }
                    }
                    $siteData->setTimezone(isset($site->timeZoneId) ? $site->timeZoneId : null);
                    $siteData->setType($awardee->type);
                    $siteData->setSiteType(isset($site->siteType) ? $site->siteType : null);
                    if ($this->env->isProd()) {
                        if (isset($site->adminEmails) && is_array($site->adminEmails)) {
                            $siteData->setEmail(join(', ', $site->adminEmails));
                        } else {
                            $siteData->setEmail(null);
                        }
                    }
                    if (empty($siteData->getWorkqueueDownload())) {
                        $siteData->setWorkqueueDownload('full_data'); // default value for workqueue downlaod
                    }
                    if ($existingArray) {
                        $siteDataArray = $this->normalizer->normalize($siteData);
                        if ($existingArray != $siteDataArray) {
                            $modified[] = [
                                'old' => $existingArray,
                                'new' => $siteDataArray
                            ];
                            if (!$preview) {
                                $this->em->flush();
                                $this->loggerService->log(Log::SITE_EDIT, [
                                    'id' => $primaryId,
                                    'old' => $existingArray,
                                    'new' => $siteDataArray
                                ]);
                            }
                        }
                        unset($deleted[array_search($siteId, $deleted)]);
                    } else {
                        $created[] = $this->normalizer->normalize($siteData);
                        if (!$preview) {
                            $this->em->persist($siteData);
                            $this->em->flush();
                            $this->loggerService->log(Log::SITE_ADD, $siteData->getId());
                        }
                    }
                }
            }
        }
        $deleted = array_values($deleted);
        if (!$preview) {
            if ($sitesCount === 0) {
                throw new \Exception('No sites found');
            }
            foreach ($deleted as $siteId) {
                $site = $existingSites[$siteId];
                $site->setDeleted(true);
                $this->em->persist($siteData);
                $this->em->flush();
                $this->loggerService->log(Log::SITE_DELETE, $existingSites[$siteId]->getId());
            }
        }

        return [
            'created' => $created,
            'modified' => $modified,
            'deleted' => array_values($deleted)
        ];
    }

    public function syncAwardees()
    {
        $entries = $this->getAwardeeEntriesFromRdr();
        $awardeesMap = [];
        foreach ($entries as $entry) {
            $awardee = $entry->resource;
            if (empty($awardee->id) || empty($awardee->displayName)) {
                continue;
            }
            if ($awardee->id === 'UNSET') {
                continue;
            }
            $awardeesMap[trim($awardee->id)] = $awardee->displayName;
        }
        if (empty($awardeesMap)) {
            throw new \Exception('No awardees found');
        }
        $awardeeRepository = $this->em->getRepository(Awardee::class);
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $awardeeRepository->deleteAwardees();
            foreach ($awardeesMap as $id => $name) {
                $awardee = new Awardee();
                $awardee->setId($id);
                $awardee->setName($name);
                $this->em->persist($awardee);
                $this->loggerService->log(Log::AWARDEE_ADD, $id);
            }
            $this->em->flush();
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollback();
            throw $e;
        }
    }

    public function syncOrganizations()
    {
        $entries = $this->getAwardeeEntriesFromRdr();
        $organizationsMap = [];
        foreach ($entries as $entry) {
            $awardee = $entry->resource;
            if (!isset($awardee->organizations) || !is_array($awardee->organizations)) {
                continue;
            }
            foreach ($awardee->organizations as $organization) {
                if (empty($organization->id) || empty($organization->displayName)) {
                    continue;
                }
                $organizationsMap[trim($organization->id)] = $organization->displayName;
            }
        }
        if (empty($organizationsMap)) {
            throw new \Exception('No organizations found');
        }
        $organizationRepository = $this->em->getRepository(Organization::class);
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $organizationRepository->deleteOrganizations();
            foreach ($organizationsMap as $id => $name) {
                $organization = new Organization();
                $organization->setId($id);
                $organization->setName($name);
                $this->em->persist($organization);
                $this->loggerService->log(Log::ORGANIZATION_ADD, $id);
            }
            $this->em->flush();
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollback();
            throw $e;
        }
    }
}
