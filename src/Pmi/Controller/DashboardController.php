<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Pmi\Drc\RdrMetrics;

/**
 * Dashboard Controller
 */
class DashboardController extends AbstractController
{
    /**
     * @var string
     */
    protected static $name = 'dashboard';

    /**
     * @var array
     */
    protected static $routes = [
        ['home', '/'],
        ['metricsV2Load', '/metrics2_load'],
        ['metricsLoad', '/metrics_load'],
        ['metricsLoadRegion', '/metrics_load_region'],
        ['metricsLoadLifecycle', '/metrics_load_lifecycle'],
    ];

    /**
     * Home Action
     *
     * @param Application $app
     *
     * @return Response
     */
    public function homeAction(Application $app)
    {
        $color_profiles = [
            'Blackbody', 'Bluered', 'Blues', 'Custom', 'Earth', 'Electric', 'Greens',
            'Hot', 'Jet', 'Picnic', 'Portland', 'Rainbow', 'RdBu', 'Reds', 'Viridis',
            'YlGnBu', 'YlOrRd'
        ];

        // metrics attributes are hard-coded as we don't have human-readable names in the API yet
        $metrics_attributes = $this->getMetricsDisplayNames();

        $recruitment_centers = $this->getCentersList($app);

        return $app['twig']->render(
            'dashboard/index.html.twig',
            [
                'color_profiles' => $color_profiles,
                'metrics_attributes' => $metrics_attributes,
                'recruitment_centers' => $recruitment_centers
            ]
        );
    }

    /**
     * Metrics 2 Load Action
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return Response
     */
    public function metricsV2LoadAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('dashboard', $request->get('csrf_token')))) {
            return $app->abort(403);
        }

        // get request attributes
        $interval = $request->get('interval');
        $stratification = $request->get('stratification');
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
        $centers = $request->get('centers');
        $enrollment_statuses = $request->get('enrollment_statuses');
        $history = $request->get('history', false);

        // set up & sanitize variables
        $start_date = $this->sanitizeDate($start_date);
        $end_date = $this->sanitizeDate($end_date);

        $day_counts = $this->getMetrics2Object(
            $app,
            $interval,
            $start_date,
            $end_date,
            $stratification,
            $centers,
            $enrollment_statuses,
            [
                'history' => $history
            ]
        );

        if (!$day_counts) {
            return $app->abort(500, 'No data returned.');
        }

        // Roll up the extra HPO dimension by date
        $day_counts = $this->combineHPOsByDate($day_counts);

        switch ($stratification) {
            case 'ENROLLMENT_STATUS':
                $display_values = [
                    'core' => 'Core Participant',
                    'registered' => 'Member',
                    'consented' => 'Consented'
                ];
                break;
            case 'GENDER_IDENTITY':
                $display_values = [
                    'Man' => 'Man',
                    'Non-Binary' => 'Non-Binary',
                    'Other/Additional Options' => 'Other/Additional Options',
                    'PMI_Skip' => 'PMI_Skip',
                    'Transgender' => 'Transgender',
                    'UNMAPPED' => 'UNMAPPED',
                    'UNSET' => 'UNSET',
                    'Woman' => 'Woman',
                ];
                break;
            case 'AGE_RANGE':
                $display_values = [
                    '0-17' => '0-17',
                    '18-25' => '18-25',
                    '26-35' => '26-35',
                    '36-45' => '36-45',
                    '46-55' => '46-55',
                    '56-65' => '56-65',
                    '66-75' => '66-75',
                    '76-85' => '76-85',
                    '86-' => '86 and above',
                    'UNSET' => 'UNSET'
                ];
                break;
            case 'RACE':
                $diaplay_values = [

                ];
                break;
            case 'TOTAL':
                $display_values = [
                    'TOTAL' => 'Total Participants'
                ];
                break;
            default:
                $display_values = [];
        }


        $traces_obj = [];
        $interval_counts = [];
        $trace_names = $day_counts[0]['metrics'];

        // if we got this far, we have data!
        // assemble data object in Plotly format
        foreach ($trace_names as $trace_name => $value) {
            $trace = [
                'x' => [],
                'y' => [],
                'name' => $display_values[$trace_name],
                'type' => 'bar',
                'text' => [],
                'hoverinfo' => 'text+name'
            ];
            $traces_obj[$trace_name] = $trace;
        }

        $control_dates = array_reverse($this->getDashboardDates($start_date, $end_date, $interval));

        if ($interval == 'DAY') {
            $interval_counts = $day_counts;
        } else {
            foreach ($control_dates as $control_date) {
                foreach ($day_counts as $day_count) {
                    $date = $day_count['date'];
                    if ($control_date == $date) {
                        array_push($interval_counts, $day_count);
                    }
                }
            }
        }

        foreach ($interval_counts as $interval_count) {
            $date = $interval_count['date'];
            $traces = $interval_count['metrics'];

            $total = 0;

            foreach ($traces as $trace_name => $value) {
                $total += $value;
                array_push($traces_obj[$trace_name]['x'], $date);
                array_push($traces_obj[$trace_name]['y'], $value);
            }

            foreach ($traces as $trace_name => $value) {
                $text = $this->calculatePercentText($value, $total) . '<br />' . $date;
                array_push($traces_obj[$trace_name]['text'], $text);
            }
        }

        $data = [];
        foreach (array_keys($display_values) as $name) {
            $trace = $traces_obj[$name];
            array_push($data, $trace);
        }

        // sort alphabetically by trace name, unless looking at enrollment status (then do reverse sort)
        if ($stratification == 'ENROLLMENT_STATUS') {
            usort(
                $data,
                function ($a, $b) {
                    if ($a['name'] == $b['name']) {
                        return 0;
                    }
                    return ($a['name'] > $b['name']) ? 1 : -1;
                }
            );
        } else {
            usort(
                $data,
                function ($a, $b) {
                    if ($a['name'] == $b['name']) {
                        return 0;
                    }
                    return ($a['name'] > $b['name']) ? -1 : 1;
                }
            );
        }

        // now apply colors since we're in order
        for ($i = 0; $i < count($data); $i++) {
            $data[$i]['marker'] = [
                "color" => $this->getColorBrewerVal($i)
            ];
        }

        return $app->json($data);
    }

    /**
     * Metrics Load
     *
     * Loads data from metrics API (or cache) to display attributes over time
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return Response
     */
    public function metricsLoadAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('dashboard', $request->get('csrf_token')))) {
            return $app->abort(403);
        }

        // get request attributes
        $filter_by = $request->get('metrics_attribute');
        $interval = $request->get('interval');
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
        $centers = explode(',', $request->get('centers'));

        // set up & sanitize variables
        $start_date = $this->sanitizeDate($start_date);
        $control_dates = array_reverse($this->getDashboardDates($start_date, $end_date, $interval));
        $data = [];
        $dates = [];
        $values = [];
        $hover_text = [];

        // variable used to validate that at least some data was found
        $error_check = 0;

        // retrieve controlled vocab for entries from metrics cache
        if ($filter_by == 'Participant') {
            $entries = [$filter_by];
        } else {
            $entries = $this->getMetricsFieldDefinitions($app, $filter_by);
            try {
                // check database to see of there are available display values for the requested metric
                $raw_display = $app['db']->fetchAll(
                    'SELECT * FROM dashboard_display_values WHERE metric = ?',
                    [$filter_by]
                );
                $display_values = [];
                foreach ($raw_display as $row) {
                    $display_values[$row['code']] = $row['display_value'];
                }
            } catch (\Exception $e) {
                $display_values = [];
            }
        }

        // if we have no entries to iterate over, halt execution and return empty data
        // will trigger UI error message
        if (empty($entries)) {
            return $app->json($data);
        }

        // iterate through all requested dates to retrieve data from cache
        foreach ($control_dates as $date) {
            if ($this->checkDates($date, $start_date, $end_date, $control_dates)) {
                // grab date for x axis
                array_push($dates, $date);

                $metrics = $this->getMetricsObject($app, $date);

                // iterate through list of control values to get counts
                foreach ($entries as $entry) {
                    $facet_total = 0;
                    $participant_total = 0;
                    // construct lookup key
                    if ($filter_by == 'Participant') {
                        $lookup = $filter_by;
                    } else {
                        $lookup = $filter_by . "." . $entry;
                    }
                    // make sure metrics data exists first, if metrics cache or API fail return value will be false
                    if (!empty($metrics)) {
                        // iterate through each center to accumulate a running total to store
                        foreach ($centers as $center) {
                            $requested_center = [];
                            if ($center == 'ALL') {
                                // first entry is always non-faceted (total counts)
                                $requested_center = $metrics[0]['entries'];
                            } else {
                                foreach ($metrics as $metric) {
                                    if (!empty($metric['facets']['hpoId']) && $metric['facets']['hpoId'] == $center) {
                                        $requested_center = $metric['entries'];
                                    }
                                }
                            }
                            if (!empty($requested_center) && array_key_exists($lookup, $requested_center)) {
                                $facet_total += $requested_center[$lookup];
                                $participant_total += $requested_center['Participant'];
                            }
                        }
                        $values[$entry][] = $facet_total;

                        // add to error_check
                        $error_check += $facet_total;

                        // generate hover text if not doing total participant count
                        if ($filter_by != 'Participant') {
                            $hover_text[$entry][] = $this->calculatePercentText($facet_total, $participant_total)
                                . '<br />' . $date;
                        }
                    } else {
                        // error retrieving metrics data from cache & API, so record error for this date
                        // acts as fallback in case only some data is missing in query range
                        $values[$entry][] = 0;
                        $hover_text[$entry][] = 'Error retrieving data for ' . $date;
                    }
                }
            }
        }

        // look to see if any values were recorded at all
        // will return empty data array to trigger error message in UI
        if ($error_check == 0) {
            return $app->json($data);
        }

        // if we got this far, we have data!
        // assemble data object in Plotly format
        foreach ($entries as $entry) {
            if ($filter_by == 'Participant') {
                $trace_name = 'Total Participants';
            } else {
                if (!empty($display_values) && array_key_exists($entry, $display_values)) {
                    $trace_name = $display_values[$entry];
                } else {
                    $trace_name = $entry;
                }
            }

            $trace = [
                "x" => $dates,
                "y" => $values[$entry],
                "name" => $trace_name,
                "type" => "bar"
            ];
            // add hover text if needed
            if ($filter_by != 'Participant') {
                $trace["text"] = $hover_text[$entry];
                $trace["hoverinfo"] = "text+name";
            }

            array_push($data, $trace);
        }

        // sort alphabetically by trace name, unless looking at registration status (then do reverse sort)
        if ($filter_by == 'Participant.enrollmentStatus') {
            usort(
                $data,
                function ($a, $b) {
                    if ($a['name'] == $b['name']) {
                        return 0;
                    }
                    return ($a['name'] > $b['name']) ? 1 : -1;
                }
            );
        } else {
            usort(
                $data,
                function ($a, $b) {
                    if ($a['name'] == $b['name']) {
                        return 0;
                    }
                    return ($a['name'] > $b['name']) ? -1 : 1;
                }
            );
        }

        // now apply colors since we're in order
        for ($i = 0; $i < count($data); $i++) {
            $data[$i]['marker'] = [
                "color" => $this->getColorBrewerVal($i)
            ];
        }
        // return json
        return $app->json($data);
    }

    /**
     * Metrics Load Region
     *
     * Loads data from metrics API (or cache) to display attributes projected onto choropleth maps of USA
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return Response
     */
    public function metricsLoadRegionAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('dashboard', $request->get('csrf_token')))) {
            return $app->abort(403);
        }

        // get request attributes
        $map_mode = $request->get('map_mode');
        $end_date = $this->sanitizeDate($request->get('end_date'));
        $color_profile = $request->get('color_profile');
        $centers = explode(',', $request->get('centers'));

        // load custom green color profile for default
        if ($color_profile == 'Custom') {
            $color_profile = [
                [0, 'rgb(247,252,245)'], [0.125, 'rgb(229,245,224)'], [0.25, 'rgb(199,233,192)'],
                [0.375, 'rgb(161,217,155)'], [0.5, 'rgb(116,196,118)'], [0.625, 'rgb(65,171,93)'],
                [0.75, 'rgb(35,139,69)'], [0.875, 'rgb(0,109,44)'], [1, 'rgb(0,68,27)']
            ];
        };

        // retrieve metrics from cache, or request new if expired
        $metrics = $this->getMetricsObject($app, $end_date);
        $map_data = [];

        // keep track of highest value so that we can normalize the color output accordingly
        // max_val will top out at 100
        $max_val = 0;

        // make sure metrics data exists first, if metrics cache or API fail return value will be false
        if (!empty($metrics)) {
            if ($map_mode == 'FullParticipant.state') {
                $state_registrations = [];
                $total_counts = [];
                $hover_text = [];
                $state_names = [];

                // grab state names from db to get targets info as well
                try {
                    $all_states = $app['db']->fetchAll("SELECT * FROM state_census_regions");
                } catch (\Exception $e) {
                    $all_states = [];
                }

                // now iterate through states
                foreach ($all_states as $state) {
                    array_push($state_names, $state['state']);

                    $state_lookup = $map_mode . ".PIIState_" . $state['state'];
                    $count = 0;

                    // iterate through each center to accumulate a running total to store
                    foreach ($centers as $center) {
                        $requested_center = [];
                        if ($center == 'ALL') {
                            $requested_center = $metrics[0]['entries'];
                        } else {
                            foreach ($metrics as $metric) {
                                if (!empty($metric['facets']['hpoId']) && $metric['facets']['hpoId'] == $center) {
                                    $requested_center = $metric['entries'];
                                }
                            }
                        }
                        if (!empty($requested_center) && array_key_exists($state_lookup, $requested_center)) {
                            $count += $requested_center[$state_lookup];
                        }
                    }

                    $pct_of_target = round($count / $state['recruitment_target'] * 100, 2);

                    // check if max_val needs to be set
                    if ($pct_of_target > $max_val) {
                        $max_val = $pct_of_target;
                    }

                    // keep track of raw number for table data
                    array_push($total_counts, $count);

                    array_push($state_registrations, $pct_of_target);
                    array_push(
                        $hover_text,
                        "<b>" . $pct_of_target . "</b>% (" . $count . ")<br><b>" . $state['state'] . "</b> (Target: "
                            . number_format($state['recruitment_target']) . ")"
                    );
                }

                $map_data[] = [
                    'type' => 'choropleth',
                    'locationmode' => 'USA-states',
                    'locations' => $state_names,
                    'z' => $state_registrations,
                    'counts' => $total_counts,
                    'text' => $hover_text,
                    "colorscale" => $color_profile,
                    "zmin" => 0,
                    // set floor on max accordingly
                    "zmax" => $max_val > 100 ? 100 : $max_val,
                    "hoverinfo" => 'text',
                    "colorbar" => [
                        "title" => 'Percentage of target recruitment',
                        "titleside" => 'right'
                    ]
                ];
            } elseif ($map_mode == 'FullParticipant.censusRegion') {
                $states_by_region = [];
                $registrations_by_state = [];
                $region_text = [];
                $total_counts = [];
                $census_region_names = [];
                try {
                    $census_regions = $app['db']->fetchAll("SELECT * FROM census_regions");
                } catch (\Exception $e) {
                    $census_regions = [];
                }

                foreach ($census_regions as $region) {
                    try {
                        $states = $app['db']->fetchAll(
                            'SELECT * FROM state_census_regions WHERE census_region_id = ? ORDER BY state',
                            [$region['id']]
                        );
                    } catch (\Exception $e) {
                        $states = [];
                    }
                    // grab name for table data
                    array_push($census_region_names, $region['label']);
                    $region_states = [];
                    foreach ($states as $state) {
                        array_push($region_states, $state["state"]);
                    }

                    // build lookup key for census region count (combination of map mode and census region (upcased))
                    $census_lookup = $map_mode . "." . strtoupper($region['label']);
                    $count = 0;

                    // iterate through each center to accumulate a running total to store
                    foreach ($centers as $center) {
                        $requested_center = [];
                        if ($center == 'ALL') {
                            $requested_center = $metrics[0]['entries'];
                        } else {
                            foreach ($metrics as $metric) {
                                if (!empty($metric['facets']['hpoId']) && $metric['facets']['hpoId'] == $center) {
                                    $requested_center = $metric['entries'];
                                }
                            }
                        }
                        if (!empty($requested_center) && array_key_exists($census_lookup, $requested_center)) {
                            $count += $requested_center[$census_lookup];
                        }
                    }
                    $pct_of_target = round($count / $region['recruitment_target'] * 100, 2);

                    // grab count for table data
                    array_push($total_counts, $count);

                    // check if max_val needs to be set
                    if ($pct_of_target > $max_val) {
                        $max_val = $pct_of_target;
                    }

                    foreach ($region_states as $state) {
                        array_push($states_by_region, $state);
                        array_push($registrations_by_state, $pct_of_target);
                        array_push(
                            $region_text,
                            "<b>" . $pct_of_target . "%</b> (" . $count . ")<br><b>" . $region["label"]
                                . "</b> (Target: " . number_format($region['recruitment_target']) . ")"
                        );
                    }
                }

                $map_data[] = [
                    'type' => 'choropleth',
                    'locationmode' => 'USA-states',
                    'locations' => $states_by_region,
                    'z' => $registrations_by_state,
                    'text' => $region_text,
                    'counts' => $total_counts,
                    'regions' => $census_region_names,
                    "colorscale" => $color_profile,
                    "zmin" => 0,
                    // set floor on max accordingly
                    "zmax" => $max_val > 100 ? 100 : $max_val,
                    "hoverinfo" => "text",
                    "colorbar" => [
                        "title" => 'Percentage of target recruitment',
                        "titleside" => 'right'
                    ]
                ];
            } elseif ($map_mode == 'FullParticipant.hpoId') {
                $i = 0;

                $categorized_centers = $this->getCentersList($app);
                $recruitment_centers = [];
                foreach ($categorized_centers as $categories) {
                    foreach ($categories as $loc) {
                        array_push($recruitment_centers, $loc);
                    }
                }

                $metrics = $this->getMetricsObject($app, $end_date);
                foreach ($recruitment_centers as $location) {
                    // check if center is requested first before adding to map_data
                    if ($centers == ['ALL'] || in_array($location['code'], $centers)) {
                        $hpo_lookup = $map_mode . "." . $location['code'];
                        $count = 0;
                        // find appropriate entry
                        foreach ($metrics as $metric) {
                            if (!empty($metric['facets']['hpoId']) && $metric['facets']['hpoId'] == $location['code']) {
                                $requested_center = $metric['entries'];
                            }
                        }

                        if (!empty($requested_center) && array_key_exists($hpo_lookup, $requested_center)) {
                            $count += $requested_center[$hpo_lookup];
                        }

                        $pct_of_target = round($count / $location['recruitment_target'] * 100, 2);

                        // check if max_val needs to be set
                        if ($pct_of_target > $max_val) {
                            $max_val = $pct_of_target;
                        }

                        $label = "{$location["label"]} ({$location['category']}): <br><b>" . $pct_of_target .
                            "%</b> (" . $count . ", Target: " . $location['recruitment_target'] . ")";

                        $map_data[] = [
                            'type' => 'scattergeo',
                            'locationmode' => 'USA-states',
                            'lat' => [$location['latitude']],
                            'lon' => [$location['longitude']],
                            'count' => $count,
                            'name' => $location['code'] . " (" . $location['category'] . ")",
                            'hoverinfo' => 'text',
                            'text' => [$label],
                            'marker' => [
                                'size' => [$pct_of_target],
                                'color' => $this->getColorBrewerVal($i),
                                'line' => [
                                    'color' => 'black',
                                    'width' => 1
                                ]
                            ]
                        ];
                        $i++;
                    }
                }

                // normalize data based on maximum value, check for div / 0 error
                $map_coefficient = 100.0 / ($max_val == 0 ? 1 : $max_val);

                // reiterate through entries to recalculate bubble size based on new coefficient
                foreach ($map_data as $index => $map_datum) {
                    $new_pct = $map_datum['marker']['size'][0] * $map_coefficient;
                    $map_data[$index]['marker']['size'] = [$new_pct];
                }
            }
        }

        // return json
        return $app->json($map_data);
    }

    /**
     * Metrics Load Lifecycle
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return Response
     */
    public function metricsLoadLifecycleAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('dashboard', $request->get('csrf_token')))) {
            return $app->abort(403);
        }

        // get request attributes
        $end_date = $this->sanitizeDate($request->get('end_date'));
        $centers = explode(',', $request->get('centers'));

        // get metrics data
        $metrics = $this->getMetricsObject($app, $end_date);
        $phases = [];
        $completed = [];
        $eligible = [];
        $completed_text = [];
        $eligible_text = [];

        // iterate through list of control values to get counts

        // hard coded-list of metrics keys to look for as we only care about certain counts
        // ordered
        $metrics_keys = [
            'Participant',
            'Participant.consentForStudyEnrollment',
            'Participant.consentForStudyEnrollmentAndEHR',
            'Participant.questionnaireOnTheBasics',
            'Participant.questionnaireOnOverallHealth',
            'Participant.questionnaireOnLifestyle',
            'Participant.numCompletedBaselinePPIModules',
            'Participant.physicalMeasurements',
            'Participant.samplesToIsolateDNA',
            'Participant.enrollmentStatus'
        ];

        $display_values = [
            'Registered',
            'Consent: Enrollment',
            'Consent: Complete',
            'PPI Module: The Basics',
            'PPI Module: Overall Health',
            'PPI Module: Lifestyle',
            'Baseline PPI Modules Complete',
            'Physical Measurements',
            'Samples Received',
            'Full Participant'
        ];

        foreach ($metrics_keys as $index => $metric_val) {
            if ($metric_val == 'Participant') {
                $lookup = $metric_val;
            } elseif ($metric_val == 'Participant.samplesToIsolateDNA') {
                $lookup = $metric_val . '.RECEIVED';
            } elseif ($metric_val == 'Participant.enrollmentStatus') {
                $lookup = $metric_val . '.FULL_PARTICIPANT';
            } elseif ($metric_val == 'Participant.physicalMeasurements') {
                $lookup = $metric_val . '.COMPLETED';
            } elseif ($metric_val == 'Participant.numCompletedBaselinePPIModules') {
                $lookup = $metric_val . '.3';
            } else {
                $lookup = $metric_val . '.SUBMITTED';
            }

            // make sure metrics data exists first, if metrics cache or API fail return value will be false
            if (!empty($metrics)) {
                $facet_total = 0;
                // iterate through each center to accumulate a running total to store
                foreach ($centers as $center) {
                    $requested_center = [];
                    if ($center == 'ALL') {
                        // first entry is always non-faceted (total counts)
                        $requested_center = $metrics[0]['entries'];
                    } else {
                        foreach ($metrics as $metric) {
                            if (!empty($metric['facets']['hpoId']) && $metric['facets']['hpoId'] == $center) {
                                $requested_center = $metric['entries'];
                            }
                        }
                    }
                    if (!empty($requested_center) && array_key_exists($lookup, $requested_center)) {
                        $facet_total += $requested_center[$lookup];
                    }
                }
                $completed[] = $facet_total;
                $phases[] = $display_values[$index];
            } else {
                // error retrieving metrics data from cache & API, so record error for this date
                // acts as fallback in case only some data is missing in query range
                $completed[] = 0;
            }
        }
        // now that we have counts (in order), go back through to determine what the elibible numbers are
        // this is based off of external logic, so cannot be done while assembling counts

        $completed_consent = $completed[1];

        foreach ($metrics_keys as $index => $val) {
            if ($val == 'Participant') {
                $eligible[] = 0;
            } elseif ($val == 'Participant.consentForStudyEnrollment') {
                $eligible[] = $completed[0] - $completed[$index];
            } else {
                $eligible[] = $completed_consent - $completed[$index] < 0 ? 0 : $completed_consent - $completed[$index];
            }
        }

        // assemble hover text for completed & eligible traces
        foreach ($completed as $index => $count) {
            $total = $count + $eligible[$index];
            $completed_text[] = $this->calculatePercentText($count, $total);
            $eligible_text[] = $this->calculatePercentText($eligible[$index], $total);
        }


        // assemble data
        $pipeline_data = [
            [
                "x" => $phases,
                "y" => $completed,
                "text" => $completed_text,
                "type" => 'bar',
                "hoverinfo" => 'text+name',
                "name" => 'Completed',
                "marker" => [
                    "color" => $this->getColorBrewerVal(1)
                ]
            ],
            [
                "x" => $phases,
                "y" => $eligible,
                "text" => $eligible_text,
                "type" => 'bar',
                "hoverinfo" => 'text+name',
                "name" => 'Eligible, not completed',
                "marker" => [
                    "color" => $this->getColorBrewerVal(0)
                ]
            ]
        ];
        // return json
        return $app->json($pipeline_data);
    }

    /* Private Methods */

    /**
     * Get Metrics Object
     *
     * Main method for retrieving metrics from API; Stores result in memcache with 1 hour expiration;
     * Each entry is comprised of a single day of all available data & facets
     *
     * @param Application $app
     * @param string      $date
     *
     * @return object
     */
    private function getMetricsObject(Application $app, $date)
    {
        $memcache = new \Memcache();
        $memcacheKey = 'metrics_api_' . $date;
        $metrics = $memcache->get($memcacheKey);
        if (!$metrics) {
            try {
                $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper']);
                $metrics = $metricsApi->metrics($date, $date);
                // first check if there are metrics available for the given date
                if (!$metrics) {
                    return false;
                }
                $memcache->set($memcacheKey, $metrics, 0, 3600);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                return false;
            }
        }
        return $metrics;
    }

    /**
     * Metrics to Object
     *
     * Main method for retrieving near-real-time metrics from API;
     * Stores result in memcache with 15-minute expiration
     *
     * @param Application $app
     * @param string      $interval
     * @param string      $start_date
     * @param string      $end_date
     * @param string      $stratification
     * @param string      $centers
     * @param string      $enrollment_statuses
     *
     * @return array
     */
    private function getMetrics2Object(
        Application $app,
        $interval,
        $start_date,
        $end_date,
        $stratification,
        $centers,
        $enrollment_statuses,
        $params = []
    ) {
        $memcache = new \Memcache();
        $memcacheKey = 'metrics_api_2_' . md5(json_encode([
            $start_date,
            $end_date,
            $stratification,
            $centers,
            $enrollment_statuses,
            $params
        ]));
        $metrics = $memcache->get($memcacheKey);
        if (1 || !$metrics) {
            try {
                $metrics = [];
                $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper']);

                $metrics = $metricsApi->metrics2(
                    $start_date,
                    $end_date,
                    $stratification,
                    $centers,
                    $enrollment_statuses,
                    $params
                );

                // first check if there are counts available for the given date
                if (count($metrics) == 0) {
                    return false;
                }
                $memcache->set($memcacheKey, $metrics, 0, 900); // 900 s = 15 min
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                return false;
            }
        }
        return $metrics;
    }

    /**
     * Get Metrics Field Definitions
     *
     * Stores and returns field definitions as controlled vocabulary.
     * Can return either values for specified field_key or all keys present
     *
     * @param Application $app
     * @param string      $field_key
     *
     * @return array
     */
    private function getMetricsFieldDefinitions(Application $app, $field_key)
    {
        $memcache = new \Memcache();
        $memcacheKey = 'metrics_api_field_definitions';
        $definitions = $memcache->get($memcacheKey);
        if (!$definitions) {
            try {
                $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper']);
                $definitions = $metricsApi->metricsFields();
                // set expiration to one hour
                $memcache->set($memcacheKey, $definitions, 0, 3600);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                return false;
            }
        }
        $keys = [];
        foreach ($definitions as $entry) {
            if (empty($field_key)) {
                array_push($keys, $entry['name']);
            } elseif ($entry['name'] == $field_key) {
                $keys = $entry['values'];
            }
        }
        return $keys;
    }

    /**
     * Get Metrics Display Names
     *
     * Stores display names for metrics attribute field names to be used in selection
     *
     * @return array
     */
    private function getMetricsDisplayNames()
    {
        $metrics_attributes = [
            "Participant.enrollmentStatus" => "Enrollment Status",
            "Participant.genderIdentity" => "Gender Identity",
            "Participant.ageRange" => "Age Range",
            "Participant.race" => "Race",
            "Participant" => "Total Registered Participants"
        ];
        return $metrics_attributes;
    }

    /**
     * Get Centers List
     *
     * Helper to build up the list of centers for use in filters and maps
     *
     * @param Application $app
     *
     * @return array
     */
    private function getCentersList(Application $app)
    {
        // get list of centers from field definitions
        try {
            $center_codes = $this->getMetricsFieldDefinitions($app, 'Participant.hpoId');
        } catch (\Exception $e) {
            $center_codes = [];
        }

        $all_centers = [];
        $recruitment_centers = [];
        $i = 5;

        // build up array of centers with categories, lat/long and provisional targets
        foreach ($center_codes as $code) {
            try {
                $center = $app['db']->fetchAssoc("SELECT * FROM recruitment_center_codes WHERE code = ?", [$code]);
            } catch (\Exception $e) {
                $center = [
                    'code' => $code, 'label' => $code, 'latitude' => '33.0000',
                    'longitude' => '-71.' . $i . '000', 'category' => 'Unknown',
                    'recruitment_target' => 10000
                ];
                $i++;
            }

            if (!$center) {
                // in case center isn't found in DB
                $center = [
                    'code' => $code, 'label' => $code, 'latitude' => '33.0000',
                    'longitude' => '-71.' . $i . '000', 'category' => 'Unknown',
                    'recruitment_target' => 10000
                ];
                $i++;
            }
            array_push($all_centers, $center);
        }

        // get all categories to sort by
        $categories = [];
        foreach ($all_centers as $center) {
            array_push($categories, $center['category']);
        }

        // sort categories and add to recruitment centers array
        sort($categories, SORT_STRING);
        foreach ($categories as $category) {
            $recruitment_centers[$category] = [];
        }

        // build up array in sort order
        foreach ($all_centers as $center) {
            $cat = $center['category'];
            $recruitment_centers[$cat][] = $center;
        }

        return $recruitment_centers;
    }

    /**
     * Check Dates
     *
     * Helper function to check date range cutoffs
     *
     * @param string $date
     * @param string $start
     * @param string $end
     * @param array  $controls
     *
     * @return boolean
     */
    private function checkDates($date, $start, $end, $controls)
    {
        return strtotime($date) >= strtotime($start)
            && strtotime($date) <= strtotime($end)
            && in_array($date, $controls);
    }

    /**
     * Get Dashboard Dates
     *
     * Helper function to return array of dates segmented by interval
     *
     * @param string $start_date
     * @param string $end_date
     * @param string $interval
     *
     * @return array
     */
    private function getDashboardDates($start_date, $end_date, $interval)
    {
        $dates = [$end_date];
        $i = 0;
        while (strtotime($dates[$i]) >= strtotime($start_date)) {
            $d = strtotime("-1 $interval", strtotime($dates[$i]));
            array_push($dates, date('Y-m-d', $d));
            $i++;
        }
        return $dates;
    }

    /**
     * Calculate Percent Text
     *
     * Helper function for calculating percentages of total for entries;
     * Returns formatted string for use in Plotly hover text
     *
     * @param int $value
     * @param int $total
     *
     * @return string
     */
    private function calculatePercentText($value, $total)
    {
        if ($total == 0) {
            return "0 (0%)";
        } else {
            $percentage = $value / $total;
            return "<b>{$value}</b> (" . number_format($percentage * 100, 2) . '%' . ")";
        }
    }

    /**
     * Get ColorBrewer Value
     *
     * Helper function to return ColorBrewer color values.
     *
     * @param int $index
     *
     * @return string
     */
    private function getColorBrewerVal($index)
    {
        // colorbrewer 20-element qualitative colors
        $colors = ['rgb(166,206,227)', 'rgb(31,120,180)', 'rgb(178,223,138)', 'rgb(51,160,44)', 'rgb(251,154,153)',
            'rgb(227,26,28)', 'rgb(253,191,111)', 'rgb(255,127,0)', 'rgb(202,178,214)', 'rgb(106,61,154)',
            'rgb(255,255,153)', 'rgb(177,89,40)', 'rgb(247,129,191)', 'rgb(153,153,153)', 'rgb(102,194,165)',
            'rgb(229,196,148)', 'rgb(85,85,85)', 'rgb(251,128,114)', 'rgb(128,177,211)', 'rgb(188,128,189)'
        ];
        if ($index >= count($colors)) {
            $adj_index = $index - count($colors);
            return $colors[$adj_index];
        } else {
            return $colors[$index];
        }
    }

    /**
     * Sanitize Date
     *
     * Sanitize url date parameter to prevent SQL injection
     *
     * @param string $date_string
     *
     * @return string
     */
    private function sanitizeDate($date_string)
    {
        return date('Y-m-d', strtotime($date_string));
    }

    /**
     * Combine HPOs by Date
     *
     * Metrics2 data comes back as an ordered array of dates and HPOs. This
     * method rolls them up by date.
     *
     * @param array $day_count Source data from API response
     * @return array
     */
    private function combineHPOsByDate($day_count = [])
    {
        $output = [];
        foreach ($day_count as $row) {
            // Create the bucket to store the metric if it doesn't exist
            if (!isset($output[$row['date']])) {
                $output[$row['date']] = [
                    'date' => $row['date'],
                    'metrics' => []
                ];
            }
            // Loop through the metrics and sum them by date
            foreach ($row['metrics'] as $k => $v) {
                if (!isset($output[$row['date']]['metrics'][$k])) {
                    $output[$row['date']]['metrics'][$k] = $v;
                } else {
                    $output[$row['date']]['metrics'][$k] += $v;
                }
            }
        }
        return array_values($output);
    }
}
