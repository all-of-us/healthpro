<?php

namespace App\WorkQueue\ColumnDefs\NPH;

use App\Service\SiteService;
use App\WorkQueue\ColumnDefs\DefaultColumn;

class SiteName extends DefaultColumn
{
    private SiteService $siteService;

    public function setSiteService(SiteService $siteService): void
    {
        $this->siteService = $siteService;
    }

    public function getColumnDisplay($data, $dataRow): string
    {
        return $this->siteService->getSiteDisplayName(str_replace('nph-site-', '', $data));
    }
}
