<?php

namespace App\WorkQueue\DataSources;

use App\Service\RdrApiService;
use App\Service\SiteService;
use App\WorkQueue\ColumnCollection;

class NphDataSource implements WorkqueueDatasource
{
    private RdrApiService|null $rdrApi = null;
    private string|null $currentSite = null;
    private bool $hasMoreResults = false;

    public function __construct(RdrApiService $api, SiteService $siteService)
    {
        $this->rdrApi = $api;
        $this->currentSite = $siteService->getSiteId();
    }

    public function getWorkqueueData(int $offset, int $limit, ColumnCollection $columnCollection): array
    {
        $response = $this->rdrApi->GQLPost('rdr/v1/nph_participant', $this->getSearchQuery($offset, $limit, $columnCollection));
        $result = json_decode($response->getBody()->getContents(), true);
        if ($result['participant']['totalCount'] > $offset + $limit) {
            $this->hasMoreResults = true;
        } else {
            $this->hasMoreResults = false;
        }
        return $result;
    }

    //Todo: Remove before production merge.
    public function rawQuery($query)
    {
        $response = $this->rdrApi->GQLPost('rdr/v1/nph_participant', $query);
        $result = json_decode($response->getBody()->getContents(), true);
        return $result;
    }

    public function hasMoreResults(): bool
    {
        return $this->hasMoreResults;
    }

    private function getFilterString(ColumnCollection $columnCollection): string
    {
        $filterString = '';
        foreach ($columnCollection as $column) {
            if ($column->isFilterable() && $column->getFilterData() !== '') {
                $filterString .= $column->getDataField() . ': "' . $column->getFilterData() . '", ';
            }
        }
        return $filterString;
    }

    private function getOrderString($columnCollection): string
    {
        $orderString = '';
        $columnOrder = [];
        foreach ($columnCollection as $column) {
            if ($column->isSortable() && $column->getSortDirection() !== '') {
                $columnOrder[$column->getSortOrder()] = ['field' => $column->getSortField(), 'direction' => $column->getSortDirection()];
            }
        }
        ksort($columnOrder);
        foreach ($columnOrder as $order) {
            $orderString .= $order['field'] . ', ';
        }
        $orderString = trim($orderString, ', ');
        return "sortBy: \"$orderString\", ";
    }

    private function getSearchQuery(int $offset, int $limit, ColumnCollection $columnCollection): string
    {
        $site = $this->currentSite;
        $filterString = $this->getFilterString($columnCollection);
        $orderString = $this->getOrderString($columnCollection);
        $query = "
        query {
        participant ($filterString $orderString limit: ${limit}, offSet: ${offset}, nphPairedSite: \"nph-site-${site}\") {
                    totalCount
                    resultCount
                    edges {
                        node {
                            aouAianStatus
                            aouBasicsStatus {
                                time,
                                value
                            }
                            aouDeactivationStatus {
                                time,
                                value
                            }
                            aouDeceasedStatus {
                                time,
                                value
                            }
                            aouEnrollmentStatus {
                                time,
                                value
                            }
                            aouLifestyleStatus {
                                time,
                                value
                            }
                            aouOverallHealthStatus {
                                time
                                value
                            }
                            aouSDOHStatus {
                                time
                                value
                            }
                            aouWithdrawalStatus {
                                time
                                value
                            }
                            biobankId
                            nphDateOfBirth
                            email
                            firstName
                            lastName
                            middleName
                            nphDeactivationStatus {
                                time
                                value
                            }
                            nphEnrollmentStatus {
                                time
                                value
                            }
                            nphPairedAwardee
                            nphPairedOrg
                            nphPairedSite
                            nphWithdrawalStatus {
                                time
                                value
                            }
                            participantNphId
                            phoneNumber
                            siteId
                            zipCode
                            nphModule1ConsentStatus {
                                time
                                value
                                optIn
                            }
                            nphModule2ConsentStatus {
                                time
                                value
                                optIn
                            }
                            nphModule3ConsentStatus {
                                time
                                value
                                optIn
                            }
                            nphModule2DietStatus {
                                dietName
                                dietStatus {
                                    time
                                    status
                                    current
                                }
                            }
                            nphModule3DietStatus {
                                dietName
                                dietStatus {
                                    time
                                    status
                                    current
                                }
                            }
                        }
                    }
                }
            }
            ";
        return $query;
    }
}
