<?php
namespace Pmi\Service;

class SiteSyncService
{
    private $rdrClient;
    private $sitesRepository;
    private $orgEndpoint = 'rdr/v1/Awardee';

    public function __construct($rdrClient, $sitesRepository)
    {
        $this->rdrClient = $rdrClient;
        $this->sitesRepository = $sitesRepository;
    }

    private function getAwardeeEntriesFromRdr()
    {
        $response = $this->rdrClient->request('GET', $this->orgEndpoint);
        $responseObject = json_decode($response->getBody()->getContents());
        if ($responseObject && !empty($responseObject->entry)) {
            return $responseObject->entry;
        } else {
            return [];
        }
    }

    private function getSitesFromDb()
    {
        $sites = $this->sitesRepository->fetchBy([]);
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
        $created = [];
        $modified = [];
        $existingSites = $this->getSitesFromDb();
        $deleted = array_keys($existingSites); // add everything to the deleted array, then remove as we find them
        $entries = $this->getAwardeeEntriesFromRdr();
        foreach ($entries as $entry) {
            $awardee = $entry->resource;
            if (!isset($awardee->organizations) || !is_array($awardee->organizations)) {
                break;
            }
            foreach ($awardee->organizations as $organization) {
                if (!isset($organization->sites) || !is_array($organization->sites)) {
                    break;
                }
                foreach ($organization->sites as $site) {
                    $existing = false;
                    $primaryId = null;
                    $siteData = [];
                    $siteId = self::getSiteSuffix($site->id);
                    if (array_key_exists($siteId, $existingSites)) {
                        $existing = $siteData = $existingSites[$siteId];
                        $primaryId = $siteData['id'];
                    }
                    $siteData['name'] = $site->displayName;
                    $siteData['google_group'] = $siteId; // backwards compatibility
                    $siteData['organization'] = $awardee->id; // backwards compatibility
                    $siteData['site_id'] = $siteId;
                    $siteData['organization_id'] = $organization->id;
                    $siteData['awardee_id'] = $awardee->id;
                    $siteData['mayolink_account'] = $site->mayolinkClientNumber;
                    $siteData['timezone'] = isset($site->timeZoneId) ? $site->timeZoneId : '';
                    $siteData['type'] = $awardee->type;
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
                                $this->sitesRepository->update($primaryId, $siteData);
                            }
                        }
                        unset($deleted[array_search($siteId, $deleted)]);
                    } else {
                        $created[] = $siteData;
                        if (!$preview) {
                            $this->sitesRepository->insert($siteData);
                        }
                    }
                }
            }
        }
        $deleted = array_values($deleted);
        if (!$preview) {
            foreach ($deleted as $siteId) {
                $this->sitesRepository->delete($existingSites[$siteId]['id']);
            }
        }

        return [
            'created' => $created,
            'modified' => $modified,
            'deleted' => array_values($deleted)
        ];
    }
}
