<?php

namespace App\Service\Pediatrics;

use Exception;

class ZScoreService
{
    /**
     * @throws Exception
     */
    public function calculateZScore(float $X, float $L, float $M, float $S): float
    {
        if ($L != 0) {
            $numerator = pow($X / $M, $L) - 1;
            $denominator = $L * $S;
            if ($denominator != 0) {
                return $numerator / $denominator;
            } else {
                throw new Exception("Division by zero error");
            }
        } else {
            if ($S != 0) {
                return log($X / $M) / $S;
            } else {
                throw new Exception("Division by zero error");
            }
        }
    }
}
