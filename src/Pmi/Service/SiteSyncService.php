<?php
namespace Pmi\Service;

class SiteSyncService
{
    protected $rdrClient;
    protected $sitesRepository;
    protected $orgEndpoint = 'rdr/v1/Awardee';

    public function __construct($rdrClient, $sitesRepository)
    {
        $this->rdrClient = $rdrClient;
        $this->sitesRepository = $sitesRepository;
    }

    protected function getAwardeeEntries()
    {
        $response = $this->rdrClient->request('GET', $this->orgEndpoint);
        $responseObject = json_decode($response->getBody()->getContents());
        if ($responseObject && !empty($responseObject->entry)) {
            return $responseObject->entry;
        } else {
            return [];
        }
    }

    public function sync()
    {
        $entries = $this->getAwardeeEntries();
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
                    $googleGroup = str_replace(\Pmi\Security\User::SITE_PREFIX, '', $site->id);
                    $existing = $this->sitesRepository->fetchOneBy([
                        'google_group' => $googleGroup
                    ]);
                    if ($existing) {
                        $siteData = $existing;
                    } else {
                        $siteData = [];
                    }
                    $siteData['name'] = $site->displayName;
                    $siteData['google_group'] = $googleGroup; // backwards compatibility
                    $siteData['organization'] = $awardee->id; // backwards compatibility
                    $siteData['site_id'] = $googleGroup;
                    $siteData['organization_id'] = $organization->id;
                    $siteData['awardee_id'] = $awardee->id;
                    $siteData['mayolink_account'] = $site->mayolinkClientNumber;
                    $siteData['timezone'] = $site->timeZoneId;
                    $siteData['type'] = $awardee->type;

                    if (empty($siteData['workqueue_download'])) {
                        $siteData['workqueue_download'] = 'full_data';
                    }
                    if ($existing) {
                        $this->sitesRepository->update($existing['id'], $siteData);
                    } else {
                        $this->sitesRepository->insert($siteData);
                    }
                }
            }
        }
    }
}
