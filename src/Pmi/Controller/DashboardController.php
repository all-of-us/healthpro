<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Pmi\Drc\RdrMetrics;

class DashboardController extends AbstractController
{
    protected static $name = 'dashboard';

    protected static $routes = [
        ['home', '/'],
        ['metrics2_load', '/metrics2_load'],
        ['metrics_load', '/metrics_load'],
        ['metrics_load_region', '/metrics_load_region'],
        ['metrics_load_lifecycle', '/metrics_load_lifecycle'],
    ];

    public function homeAction(Application $app, Request $request)
    {
        $color_profiles = ['Blackbody', 'Bluered', 'Blues', 'Custom', 'Earth', 'Electric', 'Greens', 'Hot', 'Jet', 'Picnic',
            'Portland', 'Rainbow', 'RdBu', 'Reds', 'Viridis', 'YlGnBu', 'YlOrRd'];

        // metrics attributes are hard-coded as we don't have human-readable names in the API yet
        $metrics_attributes = $this->getMetricsDisplayNames();

        $recruitment_centers = $this->getCentersList($app);

        return $app['twig']->render('dashboard/index.html.twig', [
            'color_profiles' => $color_profiles,
            'metrics_attributes' => $metrics_attributes,
            'recruitment_centers' => $recruitment_centers
        ]);
    }

    public function metrics2_loadAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('dashboard', $request->get('csrf_token')))) {
            return $app->abort(403);
        }

        // get request attributes
        $interval = $request->get('interval');
        $stratification = $request->get('stratification');
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
        $centers = explode(',', $request->get('centers'));
        $enrollment_statuses = explode(',', $request->get('enrollment_statuses'));

        // set up & sanitize variables
        $start_date = $this->sanitizeDate($start_date);

        $day_counts = $this->getMetrics2Object($app, $interval, $start_date, $end_date,
            $stratification, $centers, $enrollment_statuses);

//        return $app->json($day_counts);

        $display_values = array(
            'FULL_PARTICIPANT' => 'Full Participant',
            'MEMBER' => 'Member',
            'INTERESTED' => 'Registered'
        );

        $traces_obj = array();
        $trace_names = $day_counts[0]['metrics'];

        // if we got this far, we have data!
        // assemble data object in Plotly format
        foreach ($trace_names as $trace_name => $value) {
            $trace = array(
                'x' => [],
                'y' => [],
                'name' => $display_values[$trace_name],
                'type' => 'bar',
                'text' => [],
                'hoverinfo' => 'text+name'
            );
            $traces_obj[$trace_name] = $trace;
        }


        foreach ($day_counts as $day_count) {
            $date = $day_count['date'];

            $total = 0;

            foreach ($trace_names as $trace_name => $value) {
                $total += $value;
                array_push($traces_obj[$trace_name]['x'], $date);
                array_push($traces_obj[$trace_name]['y'], $value);
            }

            foreach ($trace_names as $trace_name => $value) {
                $text = $this->calculatePercentText($value, $total) . '<br />' . $date;
                array_push($traces_obj[$trace_name]['text'], $text);
            }
        }

        $data = [];
        foreach($display_values as $name => $display_name) {
            $trace = $traces_obj[$name];
            array_push($data, $trace);
        }

        // sort alphabetically by trace name, unless looking at enrollment status (then do reverse sort)
        if ($stratification == 'ENROLLMENT_STATUS') {
            usort($data, function ($a, $b) {
                if ($a['name'] == $b['name']) return 0;
                return ($a['name'] > $b['name']) ? 1 : -1;
            });
        } else {
            usort($data, function ($a, $b) {
                if ($a['name'] == $b['name']) return 0;
                return ($a['name'] > $b['name']) ? -1 : 1;
            });
        }

        // now apply colors since we're in order
        for ($i = 0; $i < count($data); $i++) {
            $data[$i]['marker'] = array(
                "color" => $this->getColorBrewerVal($i)
            );
        }

        return $app->json($data);
    }

    // loads data from metrics API (or cache) to display attributes over time
    public function metrics_loadAction(Application $app, Request $request)
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
                $raw_display = $app['db']->fetchAll("SELECT * FROM dashboard_display_values WHERE metric = ?", array($filter_by));
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
                            $hover_text[$entry][] = $this->calculatePercentText($facet_total, $participant_total) . '<br />' . $date;
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

            $trace = array(
                "x" => $dates,
                "y" => $values[$entry],
                "name" => $trace_name,
                "type" => "bar"
            );
            // add hover text if needed
            if ($filter_by != 'Participant') {
                $trace["text"] = $hover_text[$entry];
                $trace["hoverinfo"] = "text+name";
            }

            array_push($data, $trace);
        }

        // sort alphabetically by trace name, unless looking at registration status (then do reverse sort)
        if ($filter_by == 'Participant.enrollmentStatus') {
            usort($data, function ($a, $b) {
                if ($a['name'] == $b['name']) return 0;
                return ($a['name'] > $b['name']) ? 1 : -1;
            });
        } else {
            usort($data, function ($a, $b) {
                if ($a['name'] == $b['name']) return 0;
                return ($a['name'] > $b['name']) ? -1 : 1;
            });
        }

        // now apply colors since we're in order
        for ($i = 0; $i < count($data); $i++) {
            $data[$i]['marker'] = array(
                "color" => $this->getColorBrewerVal($i)
            );
        }
        // return json
        return $app->json($data);
    }

    // loads data from metrics API (or cache) to display attributes projected onto choropleth maps of USA
    public function metrics_load_regionAction(Application $app, Request $request)
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
                    array_push($hover_text, "<b>" . $pct_of_target . "</b>% (" . $count . ")<br><b>" . $state['state']
                        . "</b> (Target: " . number_format($state['recruitment_target']) . ")");
                }

                $map_data[] = array(
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
                    "colorbar" => array(
                        "title" => 'Percentage of target recruitment',
                        "titleside" => 'right'
                    )
                );
            } else if ($map_mode == 'FullParticipant.censusRegion') {
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
                        $states = $app['db']->fetchAll("SELECT * FROM state_census_regions WHERE census_region_id = ? ORDER BY state", [$region["id"]]);
                    } catch (\Exception $e) {
                        $states = [];
                    }
                    // grab name for table data
                    array_push($census_region_names, $region['label']);
                    $region_states = [];
                    foreach ($states as $state) {
                        array_push($region_states, $state["state"]);
                    }

                    // construct lookup key for census region count (combination of map mode and census region (upcased))
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
                        array_push($region_text, "<b>" . $pct_of_target . "%</b> (" . $count . ")<br><b>" .
                            $region["label"] . "</b> (Target: " . number_format($region['recruitment_target']) . ")");
                    }
                }

                $map_data[] = array(
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
                    "colorbar" => array(
                        "title" => 'Percentage of target recruitment',
                        "titleside" => 'right'
                    )
                );
            } else if ($map_mode == 'FullParticipant.hpoId') {
                $i = 0;

                $categorized_centers = $this->getCentersList($app);
                $recruitment_centers = array();
                foreach($categorized_centers as $categories) {
                    foreach($categories as $loc) {
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

                        $map_data[] = array(
                            'type' => 'scattergeo',
                            'locationmode' => 'USA-states',
                            'lat' => [$location['latitude']],
                            'lon' => [$location['longitude']],
                            'count' => $count,
                            'name' => $location['code'] . " (" . $location['category'] . ")",
                            'hoverinfo' => 'text',
                            'text' => [$label],
                            'marker' => array(
                                'size' => [$pct_of_target],
                                'color' => $this->getColorBrewerVal($i),
                                'line' => array(
                                    'color' => 'black',
                                    'width' => 1
                                )
                            )
                        );
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

    public function metrics_load_lifecycleAction(Application $app, Request $request)
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
        $metrics_keys = array(
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
        );

        $display_values = array(
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
        );

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
            array(
                "x" => $phases,
                "y" => $completed,
                "text" => $completed_text,
                "type" => 'bar',
                "hoverinfo" => 'text+name',
                "name" => 'Completed',
                "marker" => array(
                    "color" => $this->getColorBrewerVal(1)
                )
            ),
            array(
                "x" => $phases,
                "y" => $eligible,
                "text" => $eligible_text,
                "type" => 'bar',
                "hoverinfo" => 'text+name',
                "name" => 'Eligible, not completed',
                "marker" => array(
                    "color" => $this->getColorBrewerVal(0)
                )
            )
        ];
        // return json
        return $app->json($pipeline_data);

    }

    // PRIVATE METHODS

    // Main method for retrieving metrics from API
    // stores result in memcache with 1 hour expiration
    // each entry is comprised of a single day of all available data & facets
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
                } else {
                    $memcache->set($memcacheKey, $metrics, 0, 3600);
                }
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                return false;
            }
        }
        return $metrics;
    }

    // Main method for retrieving near-real-time metrics from API
    // stores result in memcache with 15-minute expiration
    private function getMetrics2Object(Application $app, $interval, $start_date, $end_date, $stratification, $centers,
                                       $enrollment_statuses)
    {
        $centers = implode(",", $centers);
        $enrollment_statuses = implode(",", $enrollment_statuses);
        $memcache = new \Memcache();
        $memcacheKey = 'metrics_api_2_' . $interval . '_' . $start_date . '_' . $end_date . '_' . $stratification . '_' . $centers . '_' . $enrollment_statuses;
        $metrics = $memcache->get($memcacheKey);
        if (!$metrics) {
            try {

                $metrics = array();

                $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper']);

                $date_range_bins = $this->getDateRangeBins($start_date, $end_date, $interval);

                syslog(LOG_INFO, 'date_range_bins');
                syslog(LOG_INFO, json_encode($date_range_bins));

                for ($i = 0; $i < count($date_range_bins); $i++) {
                    $bin = $date_range_bins[$i];
                    $this_start_date = $bin[0];
                    $this_end_date = $bin[1];

                    $metrics_segment = $metricsApi->metrics2($this_start_date, $this_end_date,
                        $stratification, $centers, $enrollment_statuses);


                    syslog(LOG_INFO, 'gettype(metrics_segment)');
                    syslog(LOG_INFO, gettype($metrics_segment));

                    syslog(LOG_INFO, 'metrics_segment');
                    syslog(LOG_INFO, json_encode($metrics_segment));
                    $metrics += $metrics_segment;
                }

                syslog(LOG_INFO, 'metrics');
                syslog(LOG_INFO, json_encode($metrics));

                syslog(LOG_INFO, 'count(metrics)');
                syslog(LOG_INFO, count($metrics));

                // first check if there are counts available for the given date
                if (count($metrics) == 0) {
                    return false;
                } else {
                    $memcache->set($memcacheKey, $metrics, 0, 900); // 900 s = 15 min
                }
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                return false;
            }
        }
        syslog(LOG_INFO, 'metrics');
        syslog(LOG_INFO, json_encode($metrics));
        return $metrics;
    }

    // stores and returns field definitions as controlled vocabulary
    // can return either values for specified field_key or all keys present
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
            } else if ($entry['name'] == $field_key) {
                $keys = $entry['values'];
            }
        }

        return $keys;
    }

    // stores display names for metrics attribute field names to be used in selection
    private function getMetricsDisplayNames()
    {
        $metrics_attributes = array(
            "Participant.enrollmentStatus" => "Enrollment Status",
            "Participant.genderIdentity" => "Gender Identity",
            "Participant.ageRange" => "Age Range",
            "Participant.race" => "Race",
            "Participant" => "Total Registered Participants"
        );
        return $metrics_attributes;
    }

    // helper to build up the list of centers for use in filters and maps
    private function getCentersList($app) {

        // get list of centers from field definitions
        try {
            $center_codes = $this->getMetricsFieldDefinitions($app, 'Participant.hpoId');
        } catch (\Exception $e) {
            $center_codes = [];
        }

        $all_centers = array();
        $recruitment_centers = array();
        $i = 5;

        // build up array of centers with categories, lat/long and provisional targets
        foreach($center_codes as $code) {
            try {
                $center = $app['db']->fetchAssoc("SELECT * FROM recruitment_center_codes WHERE code = ?", array($code));
            } catch (\Exception $e) {
                $center = ['code' => $code, 'label' => $code, 'latitude' => '33.0000', 'longitude' => '-71.' . $i . '000', 'category' => 'Unknown', 'recruitment_target' => 10000];
                $i++;
            }

            if (!$center) {
                // in case center isn't found in DB
                $center = ['code' => $code, 'label' => $code, 'latitude' => '33.0000', 'longitude' => '-71.' . $i . '000', 'category' => 'Unknown', 'recruitment_target' => 10000];
                $i++;
            }
            array_push($all_centers, $center);
        }

        // get all categories to sort by
        $categories = array();
        foreach($all_centers as $center) {
            array_push($categories, $center['category']);
        }

        // sort categories and add to recruitment centers array
        sort($categories, SORT_STRING);
        foreach($categories as $category) {
            $recruitment_centers[$category] = [];
        }

        // build up array in sort order
        foreach($all_centers as $center) {
            $cat = $center['category'];
            $recruitment_centers[$cat][] = $center;
        }

        return $recruitment_centers;
    }

    // helper function to check date range cutoffs
    private function checkDates($date, $start, $end, $controls)
    {
        return strtotime($date) >= strtotime($start) && strtotime($date) <= strtotime($end) && in_array($date, $controls);
    }

    // Sum Metrics API 2 counts by day to counts by other interval (e.g. week, month)
    private function rollupCountsToDateInterval($start_date, $end_date, $interval, $metrics)
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

    // Sum Metrics API 2 counts by day to counts by other interval (e.g. week, month)
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

    // Break up large date ranges segmented by maximum Metrics API 2 range
    private function getDateRangeBins($start_date, $end_date)
    {
        $date_range_bins = [];

        $start = strtotime($start_date);
        $end = strtotime($end_date);
        $num_days_in_range = $end - $start;

        // Metrics API 2 processes no more than 100 days of data per request
        $max_days_for_metrics_api_2 = 100 * (24*60*60);

        $num_bins = ceil($num_days_in_range / $max_days_for_metrics_api_2);

        if ($num_bins == 1) {
            array_push($date_range_bins, [$start_date, $end_date]);
            return $date_range_bins;
        }

        $this_date = $start;

        for ($i = 0; $i < $num_bins; $i++) {
            syslog(LOG_INFO, 'this_date');
            syslog(LOG_INFO, $this_date);
            $this_end_date = $this_date + $max_days_for_metrics_api_2;

            # Convert back to YYYY-MM-DD string format
            $this_date_str = date('Y-m-d', $this_date);
            $this_end_date_str = date('Y-m-d', $this_end_date);

            array_push($date_range_bins, [$this_date_str, $this_end_date_str]);
            $this_date += $max_days_for_metrics_api_2;
        }

        return $date_range_bins;
    }

    // helper function for calculating percentages of total for entries
    // returns formatted string for use in Plotly hover text
    private function calculatePercentText($value, $total)
    {
        if ($total == 0) {
            return "0 (0%)";
        } else {
            $percentage = $value / $total;
            return "<b>{$value}</b> (" . number_format($percentage * 100, 2) . '%' . ")";
        }
    }

    // helper function to return colorbrewer color values (since PHP can't have array constants
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

    // sanitize url date parameter to prevent SQL injection
    private function sanitizeDate($date_string)
    {
        return date('Y-m-d', strtotime($date_string));
    }
}
