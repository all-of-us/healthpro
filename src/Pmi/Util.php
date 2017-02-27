<?php
namespace Pmi;

use Ramsey\Uuid\Uuid;

class Util
{
    public static function generateUuid()
    {
        return (string)Uuid::uuid4();
    }

    public static function generateShortUuid($length = 16, $upper = true)
    {
        $id = self::generateUuid();
        $id = self::shortenUuid($id, $length);
        if ($upper) {
            $id = strtoupper($id);
        }
        return $id;
    }

    public static function shortenUuid($uuid, $length = 16)
    {
        $id = preg_replace('/[^a-f0-9]/i', '', $uuid);
        $id = substr($id, 0, $length);
        return $id;
    }

    public static function versionIsAtLeast($version, $minVersion)
    {
        $min = explode('.', $minVersion);
        $current = explode('.', $version);
        foreach ($current as $k => $val) {
            if (isset($min[$k])) {
                $compare = $min[$k];
            } else {
                $compare = 0;
            }
            if ($val < $compare) {
                return false;
            }
            if ($val > $compare) {
                return true;
            }
        }
        return true;
    }
}
