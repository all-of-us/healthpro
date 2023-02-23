<?php

namespace App\Service\Nph;

class NphProgramSummaryService
{
    public function getModules(): array
    {
        $moduleFiles = scandir(__DIR__ . '/../../Nph/Order/Samples/');
        $modules = [];
        foreach ($moduleFiles as $moduleFile) {
            if (preg_match('/^Module(\d+)\.json$/', $moduleFile, $matches)) {
                $modules[] = $matches[1];
            }
        }
        return $modules;
    }

    private function getModuleSummary($module): array
    {
        $moduleSummary = [];
        $moduleClass = 'App\Nph\Order\Modules\Module' . $module;
        $visits = $moduleClass::getVisitTypes();
        foreach (array_keys($visits) as $visit) {
            $module = new $moduleClass($visit);
            $moduleSummary[$visit] = $module->getTimePointSamples();
            foreach ($moduleSummary[$visit] as $timePoint => $timePointSamples) {
                foreach ($timePointSamples as $sampleCode => $sample) {
                    unset($moduleSummary[$visit][$timePoint][$sampleCode]);
                    if (!array_key_exists($module->getSampleType($sampleCode), $moduleSummary[$visit][$timePoint])) {
                        $moduleSummary[$visit][$timePoint][$module->getSampleType($sampleCode)] = [];
                    }
                    $moduleSummary[$visit][$timePoint][$module->getSampleType($sampleCode)][$sampleCode] = $sample;
                }
                $moduleSummary[$visit][$timePoint] = ['timePointInfo' => $moduleSummary[$visit][$timePoint], 'timePointDisplayName' => $module->getTimePoints()[$timePoint]];
            }
            $moduleSummary[$visit] = ['visitInfo' => $moduleSummary[$visit], 'visitDisplayName' => $visits[$visit]];
        }
        return $moduleSummary;
    }

    public function getProgramSummary(): array
    {
        $programSummary = [];
        $modules = $this->getModules();
        foreach ($modules as $module) {
            $moduleSummary = $this->getModuleSummary($module);
            $programSummary[$module] = $moduleSummary;
        }
        return $programSummary;
    }

    public function combineOrderSummaryWithProgramSummary($orderSummary, $programSummary): array
    {
        $combinedSummary = [];
        foreach ($programSummary as $module => $moduleSummary) {
            foreach ($moduleSummary as $visit => $visitSummary) {
                foreach ($visitSummary['visitInfo'] as $timePoint => $timePointSummary) {
                    foreach ($timePointSummary['timePointInfo'] as $sampleType => $sample) {
                        $numberSamples = 0;
                        $expectedSamples = 0;
                        foreach (array_keys($sample) as $sampleCode) {
                            if ($sampleCode === 'STOOL' || $sampleCode === 'NAIL') {
                                continue;
                            }
                            $expectedSamples++;
                            if (isset($orderSummary[$module][$visit][$timePoint][$sampleType][$sampleCode]['sampleId'])) {
                                $numberSamples++;
                            }
                            $combinedSummary[$module][$visit][$timePoint][$sampleType][$sampleCode] = $orderSummary[$module][$visit][$timePoint][$sampleType][$sampleCode] ?? [];
                        }
                        $combinedSummary[$module][$visit][$timePoint][$sampleType]['numberSamples'] = $numberSamples;
                        $combinedSummary[$module][$visit][$timePoint][$sampleType]['expectedSamples'] = $expectedSamples;
                    }
                    $combinedSummary[$module][$visit][$timePoint] = ['timePointInfo' => $combinedSummary[$module][$visit][$timePoint], 'timePointDisplayName' => $visitSummary['visitInfo'][$timePoint]['timePointDisplayName']];
                }
                $combinedSummary[$module][$visit] = ['visitInfo' => $combinedSummary[$module][$visit], 'visitDisplayName' => $visitSummary['visitDisplayName']];
            }
        }
        return $combinedSummary;
    }
}
