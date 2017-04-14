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

        $center_codes = $this->getMetricsFieldDefinitions($app, 'Participant.hpoId');

        // remove UNSET value as we don't care about this for the filters
        if(($key = array_search('UNSET', $center_codes)) !== false) {
            unset($center_codes[$key]);
        }

        $all_centers = array();
        $recruitment_centers = array();
        $i = 5;

        // build up array of centers with categories, lat/long and provisional targets
        foreach($center_codes as $code) {
            $center = $app['db']->fetchAssoc("SELECT * FROM recruitment_center_codes WHERE code = ?", array($code));
            if (!$center) {
                // in case center isn't found in DB
                $center = ['code' => $code, 'label' => $code, 'latitude' => '33.0000', 'longitude' => '-71.' . $i . '000', 'category' => 'Unknown', 'recruitment_target' => 10000];
                $i++;
            }
            array_push($all_centers, $center);
        }

        foreach($all_centers as $center) {
            $category = $center['category'];
            if (!array_key_exists($category, $recruitment_centers)) {
                $recruitment_centers[$category] = [$center];
            } else {
                $recruitment_centers[$category][] = $center;
            }
        }
        return $app['twig']->render('dashboard/index.html.twig', [
            'color_profiles' => $color_profiles,
            'metrics_attributes' => $metrics_attributes,
            'recruitment_centers' => $recruitment_centers
        ]);
    }

    // loads data from metrics API (or cache) to display attributes over time
    public function metrics_loadAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('dashboard', $request->get('csrf_token')))) {
            return $app->abort(500);
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
        }

        // if we have no entries to iterate over, halt execution and return empty data
        // will trigger UI error message
        if (empty($entries)) {
            return $app->json($data);
        }

        // iterate through all requested dates to retrieve data from cache
        foreach($control_dates as $date) {
            if ($this->checkDates($date, $start_date, $end_date, $control_dates)) {
                // grab date for x axis
                array_push($dates, $date);

                $metrics = $this->getMetricsObject($app, $date);

                // special case for registration status as this is a composite metric
                // made up of total registrations and Participant.consentForStudyEnrollment
                if ($filter_by == 'Participant.registrationStatus') {
                    // iterate through list of control values to get counts
                    $consent_total = 0;
                    $full_participant_total = 0;
                    $participant_total = 0;
                    // construct lookup key
                    $consent_lookup = "Participant.consentForStudyEnrollment.SUBMITTED" ;
                    $full_participant_lookup = "Participant.fullParticipant.SUBMITTED" ;

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
                            // set all 3 values discretely, checking to make sure they exist first
                            if (!empty($requested_center) && array_key_exists($consent_lookup, $requested_center)) {
                                $consent_total += $requested_center[$consent_lookup];
                            }

                            if (!empty($requested_center) && array_key_exists($full_participant_lookup, $requested_center)) {
                                $full_participant_total += $requested_center[$full_participant_lookup];
                            }

                            if (!empty($requested_center) && array_key_exists('Participant', $requested_center)) {
                                $participant_total += $requested_center['Participant'];
                            }
                        }
                        // set consented && full participant values
                        $values['Consented'][] = $consent_total;
                        $values['Full Participants'][] = $full_participant_total;

                        $registered_count = $participant_total - $consent_total - $full_participant_total;
                        $values['Registered'][] = $registered_count;

                        // add to error_check
                        $error_check += $registered_count;

                        // generate hover text if not doing total participant count
                        $hover_text['Consented'][] = $this->calculatePercentText($consent_total, $participant_total) . '<br />' . $date;
                        $hover_text['Full Participants'][] = $this->calculatePercentText($full_participant_total, $participant_total) . '<br />' . $date;
                        $hover_text['Registered'][] = $this->calculatePercentText($registered_count, $participant_total) . '<br />' . $date;

                    } else {
                        // error retrieving metrics data from cache & API, so record error for this date
                        // acts as fallback in case only some data is missing in query range
                        $values['Consented'][] = 0;
                        $values['Registered'][] = 0;
                        $values['Full Participants'][] = 0;
                        $hover_text['Consented'][] = 'Error retrieving data for ' . $date;
                        $hover_text['Registered'][] = 'Error retrieving data for ' . $date;
                        $hover_text['Full Participants'][] = 'Error retrieving data for ' . $date;
                    }
                } else {
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
        }

        // look to see if any values were recorded at all
        // will return empty data array to trigger error message in UI
        if ($error_check == 0) {
            return $app->json($data);
        }

        // reverse sort so that they appear in ascending alphabetical order in the legend
        // ignore registrationStatus composite metric as we want to maintain order
        if ($filter_by != 'Participant.registrationStatus') {
            rsort($entries);
        }

        // if we got this far, we have data!
        // assemble data object in Plotly format
        $i = 0;
        foreach($entries as $entry) {
            if ($filter_by == 'Participant') {
                $trace_name = 'Total Participants';
            } else {
                $trace_name = $entry;
            }
            $trace = array(
                "x" => $dates,
                "y" => $values[$entry],
                "name" => $trace_name,
                "type" => "bar",
                "marker" => array(
                    "color" => $this->getColorBrewerVal($i)
                )
            );
            // add hover text if needed
            if ($filter_by != 'Participant') {
                $trace["text"] = $hover_text[$entry];
                $trace["hoverinfo"] = "text+name";
            }

            array_push($data, $trace);
            $i++;
        }

        // return json
        return $app->json($data);
    }

    // loads data from metrics API (or cache) to display attributes projected onto choropleth maps of USA
    public function metrics_load_regionAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('dashboard', $request->get('csrf_token')))) {
            return $app->abort(500);
        }

        // get request attributes
        $map_mode = $request->get('map_mode');
        $end_date = $this->sanitizeDate($request->get('end_date'));
        $color_profile = $request->get('color_profile');
        $centers = explode(',', $request->get('centers'));

        // load custom green color profile for default
        if ($color_profile == 'Custom') {
            $color_profile = [
                [0, 'rgb(247,252,245)'], [0.125, 'rgb(229,245,224)'],[0.25, 'rgb(199,233,192)'],
                [0.375, 'rgb(161,217,155)'],[0.5, 'rgb(116,196,118)'], [0.625, 'rgb(65,171,93)'],
                [0.75, 'rgb(35,139,69)'],[0.875, 'rgb(0,109,44)'],[1, 'rgb(0,68,27)']
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
                $hover_text = [];
                $state_names = [];

                // grab state names from db to get targets info as well
                try {
                    $all_states = $app['db']->fetchAll("SELECT * FROM state_census_regions");
                } catch (\Exception $e) {
                    $all_states = [];
                }

                // now iterate through states
                foreach($all_states as $state) {
                    array_push($state_names, $state['state']);

                    $state_lookup = $map_mode . ".PIIState_" . $state['state'];
                    $count = 0;

                    // iterate through each center to accumulate a running total to store
                    foreach($centers as $center) {
                        $requested_center = [];
                        if ($center == 'ALL') {
                            $requested_center = $metrics[0]['entries'];
                        } else {
                            foreach($metrics as $metric) {
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
                    if ($pct_of_target > $max_val ) {
                        $max_val = $pct_of_target;
                    }

                    array_push($state_registrations, $pct_of_target);
                    array_push($hover_text, "<b>" . $pct_of_target . "</b>% (" . $count . ")<br><b>" . $state['state']
                        . "</b> (Target: " . number_format($state['recruitment_target']) . ")");
                }

                $map_data[] = array(
                    'type' => 'choropleth',
                    'locationmode' => 'USA-states',
                    'locations' => $state_names,
                    'z' => $state_registrations,
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
                try {
                    $census_regions = $app['db']->fetchAll("SELECT * FROM census_regions");
                } catch (\Exception $e) {
                    $census_regions = [];
                }

                foreach($census_regions as $region) {
                    try {
                        $states = $app['db']->fetchAll("SELECT * FROM state_census_regions WHERE census_region_id = ? ORDER BY state", [$region["id"]]);
                    } catch (\Exception $e) {
                        $states = [];
                    }
                    $region_states = [];
                    foreach($states as $state) {
                        array_push($region_states, $state["state"]);
                    }

                    // construct lookup key for census region count (combination of map mode and census region (upcased))
                    $census_lookup = $map_mode . "." . strtoupper($region['label']);
                    $count = 0;

                    // iterate through each center to accumulate a running total to store
                    foreach($centers as $center) {
                        $requested_center = [];
                        if ($center == 'ALL') {
                            $requested_center = $metrics[0]['entries'];
                        } else {
                            foreach($metrics as $metric) {
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

                    // check if max_val needs to be set
                    if ($pct_of_target > $max_val ) {
                        $max_val = $pct_of_target;
                    }

                    foreach($region_states as $state) {
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
                try {
                    $recruitment_centers = $app['db']->fetchAll("SELECT * FROM recruitment_center_codes");
                } catch (\Exception $e) {
                    $recruitment_centers = [];
                }
                $metrics = $this->getMetricsObject($app, $end_date);
                foreach($recruitment_centers as $location) {

                    // check if center is requested first before adding to map_data
                    if ($centers == ['ALL'] || in_array($location['code'], $centers) ) {
                        $hpo_lookup = $map_mode . "." . $location['code'];
                        $count = 0;

                        // find appropriate entry
                        foreach($metrics as $metric) {
                            if (!empty($metric['facets']['hpoId']) && $metric['facets']['hpoId'] == $location['code']) {
                                $requested_center = $metric['entries'];
                            }
                        }

                        if (!empty($requested_center) && array_key_exists($hpo_lookup, $requested_center)) {
                            $count += $requested_center[$hpo_lookup];
                        }

                        $pct_of_target = round($count / $location['recruitment_target'] * 100, 2);

                        // check if max_val needs to be set
                        if ($pct_of_target > $max_val ) {
                            $max_val = $pct_of_target;
                        }

                        $label = "{$location["label"]} ({$location['category']}): <br><b>" . $pct_of_target  .
                            "%</b> (" . $count . ", Target: " . $location['recruitment_target'] . ")";

                        $map_data[] = array(
                            'type' => 'scattergeo',
                            'locationmode' => 'USA-states',
                            'lat' => [$location['latitude']],
                            'lon' => [$location['longitude']],
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
                foreach($map_data as $index => $map_datum) {
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
            return $app->abort(500);
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
            'Participant.questionnaireOnLifestyle',
            'Participant.questionnaireOnOverallHealth',
            'Participant.questionnaireOnTheBasics',
            'Participant.numCompletedBaselinePPIModules',
            'Participant.physicalMeasurements',
            'Participant.samplesToIsolateDNA',
            'Participant.enrollmentStatus'
        );

        $display_values = array(
            'Registered',
            'Consent: Enrollment',
            'Consent: Complete',
            'PPI Module: Lifestyle',
            'PPI Module: Overall Health',
            'PPI Module: The Basics',
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

        foreach($metrics_keys as $index => $val) {
            if ($val == 'Participant') {
                $eligible[] = 0;
            } elseif ($val == 'Participant.consentForStudyEnrollment') {
                $eligible[] = $completed[0] - $completed[$index];
            } else {
                $eligible[] = $completed_consent - $completed[$index] < 0 ? 0 : $completed_consent - $completed[$index];
            }
        }

        // assemble hover text for completed & eligible traces
        foreach($completed as $index => $count) {
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
                    "name" => 'Eligible, incomplete',
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
    // stores result in memcache with 4 hour expiration
    // each entry is comprised of a single day of all available data & facets
    private function getMetricsObject(Application $app, $date) {
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
                    $memcache->set($memcacheKey, $metrics, 0, 86400);
                }
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                return false;
            }
        }
        return $metrics;
    }

    // stores and returns field definitions as controlled vocabulary
    // can return either values for specified field_key or all keys present
    private function getMetricsFieldDefinitions(Application $app, $field_key) {
        $memcache = new \Memcache();
        $memcacheKey = 'metrics_api_field_definitions';
        $definitions = $memcache->get($memcacheKey);
        if (!$definitions) {
            try {
                $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper']);
                $definitions = $metricsApi->metricsFields();
                // set expiration to four hours
                $memcache->set($memcacheKey, $definitions, 0, 86400);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                return false;
            }
        }
        $keys = [];
        foreach($definitions as $entry) {
            if (empty($field_key)) {
                array_push($keys, $entry['name']);
            } else if ($entry['name'] == $field_key) {
                $keys = $entry['values'];
            }
        }

        return $keys;
    }

    // stores display names for metrics attribute field names to be used in selection
    private function getMetricsDisplayNames() {
        $metrics_attributes = array(
            "Participant" => "Total Participants",
            "Participant.enrollmentStatus" => "Enrollment Status",
            "Participant.genderIdentity" => "Gender Identity",
            "Participant.ageRange" => "Age Range",
            "Participant.race" => "Race"
        );
        return $metrics_attributes;
    }

    // helper function to check date range cutoffs
    private function checkDates($date, $start, $end, $controls) {
        return strtotime($date) >= strtotime($start) && strtotime($date) <= strtotime($end) && in_array($date, $controls);
    }

    // helper function to return array of dates segmented by interval
    private function getDashboardDates($start_date, $end_date, $interval) {
        $dates = [$end_date];
        $i = 0;
        while (strtotime($dates[$i]) >= strtotime($start_date)){
            $d = strtotime("-1 $interval", strtotime($dates[$i]));
            array_push($dates, date('Y-m-d', $d));
            $i++;
        }
        return $dates;
    }

    // helper function for calculating percentages of total for entries
    // returns formatted string for use in Plotly hover text
    private function calculatePercentText($value, $total) {
        if ($total == 0) {
            return "0 (0%)";
        } else {
            $percentage = $value / $total;
            return "<b>{$value}</b> (".number_format( $percentage * 100, 2 ) . '%'.")";
        }

    }

    // helper function to return colorbrewer color values (since PHP can't have array constants
    private function getColorBrewerVal($index) {
        // colorbrewer 20-element qualitative colors
        $colors = ['rgb(166,206,227)','rgb(31,120,180)','rgb(178,223,138)','rgb(51,160,44)','rgb(251,154,153)',
            'rgb(227,26,28)', 'rgb(253,191,111)','rgb(255,127,0)','rgb(202,178,214)','rgb(106,61,154)',
            'rgb(255,255,153)','rgb(177,89,40)','rgb(247,129,191)', 'rgb(153,153,153)', 'rgb(102,194,165)',
            'rgb(229,196,148)', 'rgb(85,85,85)', 'rgb(251,128,114)', 'rgb(128,177,211)', 'rgb(188,128,189)'
        ];
        return $colors[$index];
    }

    // sanitize url date parameter to prevent SQL injection
    private function sanitizeDate($date_string) {
        return date('Y-m-d', strtotime($date_string));
    }
}
