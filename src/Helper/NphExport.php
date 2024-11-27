<?php

namespace App\Helper;

use App\Entity\NphSample;
use App\Entity\User;
use App\Repository\NphOrderRepository;

class NphExport
{
    public const EXPORT_UNFINALIZED_TYPE = 'UnfinalizedSamples';
    public const EXPORT_UNLOCKED_TYPE = 'UnlockedSamples';
    public const EXPORT_RECENT_MODIFIED_TYPE = 'RecentlyModifiedSamples';

    public static array $reviewExportHeaders = [
        'Site',
        'Biobank ID',
        'Module',
        'Visit',
        'Timepoint',
        'Order ID',
        'Samples',
        'Sample ID',
        'Created',
        'Collected',
        'Aliquoted and Finalized'
    ];

    public static function getReviewExportSamples(?string $exportType, NphOrderRepository $repository, ?string $userTimeZone)
    {
        return match ($exportType) {
            NphExport::EXPORT_UNFINALIZED_TYPE => $repository->getUnfinalizedBiobankSamples(),
            NphExport::EXPORT_UNLOCKED_TYPE => $repository->getUnlockedBiobankSamples(),
            NphExport::EXPORT_RECENT_MODIFIED_TYPE => $repository->getRecentlyModifiedBiobankSamples($userTimeZone),
            default => $repository->getTodaysBiobankOrders($userTimeZone),
        };
    }

    public static function getReviewExportHeaders(string $exportType): array
    {
        $exportHeaders = self::$reviewExportHeaders;
        if ($exportType === self::EXPORT_RECENT_MODIFIED_TYPE) {
            $exportHeaders[] = 'Modified';
        }
        $exportHeaders[] = 'Status';
        return $exportHeaders;
    }

    public static function displayDateAndTimezone(?\DateTime $time, ?string $timezoneId, ?int $userTimeZoneId): string
    {
        if (!$time) {
            return '';
        }
        $timeZoneId = $timezoneId ?? $userTimeZoneId;
        if ($timeZoneId !== null) {
            $timeZone = User::$timezones[$timeZoneId] ?? 'UTC'; // Default to 'UTC' if not found
            $time->setTimezone(new \DateTimeZone($timeZone));
        }
        return $time->format('n/j/Y g:ia T');
    }

    public static function displayBiobankSampleStatus(array $nphSample): string
    {
        $type = $nphSample['modifyType'];
        $status = '';
        if ($type === NphSample::CANCEL) {
            $status = 'Cancelled';
        } elseif ($type === NphSample::UNLOCK) {
            $status = 'Unlocked';
        } elseif ($type === NphSample::EDITED) {
            $status = 'Edited & Finalized';
        } elseif ($nphSample['finalizedTs']) {
            $status = $nphSample['biobankFinalized'] ? 'Biobank Finalized' : 'Finalized';
        } elseif ($nphSample['collectedTs']) {
            $status = 'Collected';
        } elseif ($nphSample['createdTs']) {
            $status = 'Created';
        }
        if ($nphSample['DowntimeGenerated']) {
            return "{$status} (Downtime)";
        }
        return $status;
    }
}
