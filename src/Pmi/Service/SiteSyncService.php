<?php
namespace Pmi\Service;

use Pmi\Audit\Log;

class SiteSyncService
{
    private $app;
    private $rdrClient;
    private $em;
    private $orgEndpoint = 'rdr/v1/Awardee?_inactive=true';
    private $entries;

    public function __construct($app)
    {
        $this->app = $app;
        $this->rdrClient = $app['pmi.drc.rdrhelper']->getClient();
        $this->em = $app['em'];
    }

    private function getAwardeeEntriesFromRdr()
    {
        if (!is_null($this->entries)) {
            return $this->entries;
        }
        $response = $this->rdrClient->request('GET', $this->orgEndpoint);
        $responseObject = json_decode($response->getBody()->getContents());
        if ($responseObject && !empty($responseObject->entry)) {
            $this->entries = $responseObject->entry;
            return $this->entries;
        }
        return [];
    }

    private function getSitesFromDb()
    {
        $sitesRepository = $this->em->getRepository('sites');
        $sites = $sitesRepository->fetchBy(['deleted' => 0]);
        $sitesById = [];
        foreach ($sites as $site) {
            $sitesById[$site['google_group']] = $site;
        }
        return $sitesById;
    }

    private static function getSiteSuffix($site)
    {
        return str_replace(\Pmi\Security\User::SITE_PREFIX, '', $site);
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
        $sitesRepository = $this->em->getRepository('sites');
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
                    $existing = false;
                    $primaryId = null;
                    $siteData = [];
                    $siteId = self::getSiteSuffix($site->id);
                    if (array_key_exists($siteId, $existingSites)) {
                        $existing = $siteData = $existingSites[$siteId];
                        $primaryId = $siteData['id'];
                    }
                    $siteData['status'] = (isset($site->enrollingStatus) && $site->enrollingStatus === 'ACTIVE') ? 1 : 0;
                    $siteData['name'] = $site->displayName;
                    $siteData['google_group'] = $siteId; // backwards compatibility
                    $siteData['organization'] = $awardee->id; // backwards compatibility
                    $siteData['site_id'] = $siteId;
                    $siteData['organization_id'] = $organization->id;
                    $siteData['awardee_id'] = $awardee->id;
                    if ($this->app->isProd()) {
                        $siteData['mayolink_account'] = isset($site->mayolinkClientNumber) ? $site->mayolinkClientNumber : null;
                    } elseif ($this->app->isStable()) {
                        if (strtolower($awardee->type) === 'dv') {
                            // Set to default dv account number if existing mayo account number is empty or equal to default hpo account number
                            if (empty($existing['mayolink_account']) || ($existing['mayolink_account'] === $this->app->getConfig('ml_account_hpo'))) {
                                $siteData['mayolink_account'] = $this->app->getConfig('ml_account_dv');
                            }
                        } else {
                            // Set to default hpo account number if existing mayo account number is empty or equal to default dv account number
                            if (empty($existing['mayolink_account']) || ($existing['mayolink_account'] === $this->app->getConfig('ml_account_dv'))) {
                                $siteData['mayolink_account'] = $this->app->getConfig('ml_account_hpo');
                            }
                        }
                    }
                    $siteData['timezone'] = isset($site->timeZoneId) ? $site->timeZoneId : null;
                    $siteData['type'] = $awardee->type;
                    if ($this->app->isProd()) {
                        if (isset($site->adminEmails) && is_array($site->adminEmails)) {
                            $siteData['email'] = join(', ', $site->adminEmails);
                        } else {
                            $siteData['email'] = null;
                        }
                    }
                    if (empty($siteData['workqueue_download'])) {
                        $siteData['workqueue_download'] = 'full_data'; // default value for workqueue downlaod
                    }

                    if ($existing) {
                        $diff = array_diff_assoc($existing, $siteData);
                        if (count($diff) > 0) {
                            $modified[] = [
                                'old' => $existing,
                                'new' => $siteData
                            ];
                            if (!$preview) {
                                $sitesRepository->update($primaryId, $siteData);
                                $this->app->log(Log::SITE_EDIT, [
                                    'id' => $primaryId,
                                    'old' => $existing,
                                    'new' => $siteData
                                ]);
                            }
                        }
                        unset($deleted[array_search($siteId, $deleted)]);
                    } else {
                        $created[] = $siteData;
                        if (!$preview) {
                            $siteInsertId = $sitesRepository->insert($siteData);
                            $this->app->log(Log::SITE_ADD, $siteInsertId);
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
                $sitesRepository->update($existingSites[$siteId]['id'], ['deleted' => 1]);
                $this->app->log(Log::SITE_DELETE, $existingSites[$siteId]['id']);
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
            $awardeesMap[$awardee->id] = $awardee->displayName;
        }
        if (empty($awardeesMap)) {
            throw new \Exception('No awardees found');
        }
        $awardeesRepository = $this->em->getRepository('awardees');
        $awardeesRepository->wrapInTransaction(function() use ($awardeesRepository, $awardeesMap) {
            $awardeesRepository->truncate();
            foreach ($awardeesMap as $id => $name) {
                $awardeesRepository->insert([
                    'id' => $id,
                    'name' => $name
                ]);
                $this->app->log(Log::AWARDEE_ADD, $id);
            }
        });
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
                $organizationsMap[$organization->id] = $organization->displayName;
            }
        }
        if (empty($organizationsMap)) {
            throw new \Exception('No organizations found');
        }
        $organizationsRepository = $this->em->getRepository('organizations');
        $organizationsRepository->wrapInTransaction(function() use ($organizationsRepository, $organizationsMap) {
            $organizationsRepository->truncate();
            foreach ($organizationsMap as $id => $name) {
                $organizationsRepository->insert([
                    'id' => $id,
                    'name' => $name
                ]);
                $this->app->log(Log::ORGANIZATION_ADD, $id);
            }
        });
    }
}
