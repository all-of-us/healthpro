<?php

namespace App\Nph\Order;

class Nomenclature
{
    public static array $moduleVisitMapper = [
        '1_LMT' => 'LMT (Visit 2)',
        '1_LMTStool' => 'LMT (Visit 2) | Stool',
        '2_Period1Diet' => 'Diet (Visit 1)',
        '2_Period1DSMT' => 'DSMT (Visit 2)',
        '2_Period1DSMTStool' => 'DSMT (Visit 2) | Stool',
        '2_Period2Diet' => 'Diet (Visit 3)',
        '2_Period2DSMT' => 'DSMT (Visit 4)',
        '2_Period2DSMTStool' => 'DSMT (Visit 4) | Stool',
        '2_Period3Diet' => 'Diet (Visit 5)',
        '2_Period3DSMT' => 'DSMT (Visit 6)',
        '2_Period3DSMTStool' => 'DSMT (Visit 6) | Stool',
        '3_Period1Diet' => 'Diet (Visit 1)',
        '3_Period1DLW' => 'DLW Visit',
        '3_Period1DSMT' => 'DSMT (DP 1 Day 13)',
        '3_Period1DSMTStool' => 'DSMT (DP 1 Day 13) | Stool',
        '3_Period1LMT' => 'LMT (DP 1 Day 14)',
        '3_Period2Diet' => 'Diet (Visit 3)',
        '3_Period2DLW' => 'DLW Visit',
        '3_Period2DSMT' => 'DSMT (DP 2 Day 13)',
        '3_Period2DSMTStool' => 'DSMT (DP 2 Day 13) | Stool',
        '3_Period2LMT' => 'LMT (DP 2 Day 14)',
        '3_Period3Diet' => 'Diet (Visit 5)',
        '3_Period3DLW' => 'DLW Visit',
        '3_Period3DSMT' => 'DSMT (DP 3 Day 13)',
        '3_Period3DSMTStool' => 'DSMT (DP 3 Day 13) | Stool',
        '3_Period3LMT' => 'LMT (DP 3 Day 14)',
    ];

    public static array $modulePeriodVisitMapper = [
        '1_LMT' => 'LMT (Visit 2)',
        '2_Period1Diet' => 'Diet Period 1 - Diet (Visit 1)',
        '2_Period1DSMT' => 'Diet Period 1 - DSMT (Visit 2)',
        '2_Period2Diet' => 'Diet Period 2 - Diet (Visit 3)',
        '2_Period2DSMT' => 'Diet Period 2 - DSMT (Visit 4)',
        '2_Period3Diet' => 'Diet Period 3 - Diet (Visit 5)',
        '2_Period3DSMT' => 'Diet Period 3 - DSMT (Visit 6)',
        '3_Period1Diet' => 'Diet Period 1 - Diet (Visit 1)',
        '3_Period1DLW' => 'Diet Period 1 - DLW Visit',
        '3_Period1DSMT' => 'Diet Period 1 - DSMT (DP 1 Day 13)',
        '3_Period1LMT' => 'Diet Period 1 - LMT (DP 1 Day 14)',
        '3_Period2Diet' => 'Diet Period 2 - Diet (Visit 3)',
        '3_Period2DLW' => 'Diet Period 2 - DLW Visit',
        '3_Period2DSMT' => 'Diet Period 2 - DSMT (DP 2 Day 13)',
        '3_Period2LMT' => 'Diet Period 2 - LMT (DP 2 Day 14)',
        '3_Period3Diet' => 'Diet Period 3 - Diet (Visit 5)',
        '3_Period3DLW' => 'Diet Period 3 - DLW Visit',
        '3_Period3DSMT' => 'Diet Period 3 - DSMT (DP 3 Day 13)',
        '3_Period3LMT' => 'Diet Period 3 - LMT (DP 3 Day 14)',
    ];
}
