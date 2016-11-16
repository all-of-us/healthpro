<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Pmi\Drc\RdrMetrics;

class DashboardController extends AbstractController
{
    protected static $name = 'dashboard';

    const PARTICIPANT_GOAL = 1000000;

    protected static $routes = [
        ['home', '/'],
        ['demo', '/demo'],
        ['metrics_load', '/metrics_load'],
        ['metrics_load_region', '/metrics_load_region'],
        ['demo_load_data', '/demo_load_data'],
        ['demo_load_map_data', '/demo_load_map_data'],
        ['demo_load_lifecycle_data', '/demo_load_lifecycle_data'],
        ['demo_total_progress', '/demo_total_progress']
    ];

    public function homeAction(Application $app, Request $request)
    {
        $color_profiles = ['Blackbody', 'Bluered', 'Blues', 'Custom', 'Earth', 'Electric', 'Greens', 'Hot', 'Jet', 'Picnic',
            'Portland', 'Rainbow', 'RdBu', 'Reds', 'Viridis', 'YlGnBu', 'YlOrRd'];
        $metrics_attributes = $this->getMetricsKeyVals('');
        return $app['twig']->render('dashboard/index.html.twig', [
            'color_profiles' => $color_profiles,
            'metrics_attributes' => $metrics_attributes
        ]);
    }

    public function metrics_loadAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('dashboard', $request->get('csrf_token')))) {
            return $app->abort(500);
        }

        // get request attributes
        $filter_by = $request->get('metrics_attribute');
        //$bucket_by = $request->get('bucket_by');
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');

        // retrieve metrics from cache, or request new if expired
        $results = $this->getMetricsObject($app, "NONE");

        // if start date isn't supplied, grab first date from metrics response object
        if (empty($start_date)) {
            $start_date = $results[0]->date;
        }

        $data = [];

        $dates = [];
        $entries = [];
        $values = [];
        $totals = [];
        $hover_text = [];

        // grab all entries and dates to use for plotly
        foreach($results as $row) {
            $date = explode('T', $row->date)[0];
            if ($this->checkDates($date, $start_date, $end_date)) {
                array_push($dates, $date);
                foreach($row->entries as $entry) {
                    if (strpos($entry->name, '.') !== false) {
                        $parts = explode('.', $entry->name);
                        $filter_key = $parts[0] . "." . $parts[1];
                        $entry_name = $parts[2];
                    } else {
                        $filter_key = $entry->name;
                        $entry_name = 'Total Participants';
                    }

                    if ($filter_key === $filter_by) {
                        if (!in_array($entry_name, $entries)) {
                            array_push($entries, $entry_name);
                        }
                    }

                    // if not performing total participant count, grab total to use for
                    // percentage text
                    if ($filter_by != 'Participant' && $filter_key == 'Participant') {
                        array_push($totals, $entry->value);
                    }
                }
            }

        }
        // set counter to keep track of how many rows have been processed (for totals array)
        $i = 0;

        // iterate again to grab values now that we have all possible entries
        foreach($results as $row) {
            $date = explode('T', $row->date)[0];
            if ($this->checkDates($date, $start_date, $end_date)) {
                foreach ($entries as $entry) {
                    if ($filter_by == 'Participant') {
                        $lookup = $filter_by;
                    } else {
                        $lookup = $filter_by . "." . $entry;
                    }
                    $row_entries = $row->entries;
                    $match = $this->searchEntries($row_entries, 'name', $lookup);
                    if (empty($match)) {
                        $val = 0;
                    } else {
                        $val = $match[0];
                    }

                    $values[$entry][] = $val;

                    if ($filter_by != 'Participant') {
                       $hover_text[$entry][] = $this->calculatePercentText($val, $totals[$i]);
                    }
                }
                $i++;
            }
        }

        rsort($entries);

        // assemble data object in Plotly format
        $i = 0;
        foreach($entries as $entry) {
            $trace = array(
                "x" => $dates,
                "y" => $values[$entry],
                "name" => $entry,
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

    public function metrics_load_regionAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('dashboard', $request->get('csrf_token')))) {
            return $app->abort(500);
        }
        
        $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper']);
        // load attribute to query
        $metrics_attribute = $request->get('metrics_attribute');
        $color_profile = $request->get('color_profile');

        if ($color_profile == 'Custom') {
            $color_profile = [
                [0, 'rgb(247,252,245)'], [0.125, 'rgb(229,245,224)'],[0.25, 'rgb(199,233,192)'],
                [0.375, 'rgb(161,217,155)'],[0.5, 'rgb(116,196,118)'], [0.625, 'rgb(65,171,93)'],
                [0.75, 'rgb(35,139,69)'],[0.875, 'rgb(0,109,44)'],[1, 'rgb(0,68,27)']
            ];
        };

        $result = $metricsApi->metrics($metrics_attribute, "MONTH", "2016-10-01", "2017-10-01")->bucket;

        $all_states = $app['db']->fetchAll("SELECT distinct(state) FROM state_zip_ranges ORDER BY STATE");
        $state_registrations = [];

        foreach($all_states as $row) {
            $state = $row['state'];
            $state_registrations[$state] = 0;
        }

        foreach($result as $row){
            if (property_exists($row, 'entries')) {
                $row_entries = $row->entries;
                foreach($row_entries as $ent) {
                    $zip = (int)$ent->name;
                    $state = $app['db']->fetchColumn("SELECT state FROM state_zip_ranges WHERE zip_min <= ?
                                                          AND zip_max >= ?", [$zip, $zip]);
                    if ($state != "0") {
                        $state_registrations[$state] += $ent->value;
                    }
                }
            }
        }
        $names = [];
        $registrations = [];

        foreach($state_registrations as $state => $count) {
            array_push($names, $state);
            array_push($registrations, $count);
        }

        $data[] = array(
            'type' => 'choropleth',
            'locationmode' => 'USA-states',
            'locations' => $names,
            'z' => $registrations,
            'text' => $names,
            "colorscale" => $color_profile
        );

        return $app->json($data);
    }

    // DEMO ACTIONS, NOT FOR DEPLOYMENT

    public function demoAction(Application $app, Request $request)
    {
        $total = $app['db']->fetchColumn("SELECT count(*) from dashboard_participants");
        $percentage = number_format(($total / DashboardController::PARTICIPANT_GOAL * 100), 2);

        // load recruitement centers for filtering
        $all_centers = $app['db']->fetchAll("SELECT * FROM recruitment_centers");
        $recruitment_centers = array();
        foreach($all_centers as $center) {
            $category = $center['category'];
            if (!array_key_exists($category, $recruitment_centers)) {
                $recruitment_centers[$category] = [$center];
            } else {
                $recruitment_centers[$category][] = $center;
            }
        }

        $today = date('Y-m-d');

        // array of Plotly color profiles for dropdown
        $color_profiles = ['Blackbody', 'Bluered', 'Blues', 'Custom', 'Earth', 'Electric', 'Greens', 'Hot', 'Jet', 'Picnic',
                           'Portland', 'Rainbow', 'RdBu', 'Reds', 'Viridis', 'YlGnBu', 'YlOrRd'];
        return $app['twig']->render('dashboard/demo.html.twig', [
            'total_participants' => $total,
            'recruitment_centers' => $recruitment_centers,
            'color_profiles' => $color_profiles,
            'today' => $today,
            'percentage' => $percentage
        ]);
    }

    public function demo_load_dataAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('demo', $request->get('csrf_token')))) {
            return $app->abort(500);
        }
        
        // determine search attribute
        $search_attr = $request->get('attribute');
        $raw_filters = explode(',', $request->get('centers'));
        $center_filters = [];
        foreach($raw_filters as $center) {
            array_push($center_filters, (int) $center);
        }
        // determine db column to query
        switch ($search_attr) {
            case 'participant_tiers':
                $db_col = 'participant_tier';
                break;
            case 'races':
                $db_col = 'race';
                break;
            case 'ethnicities':
                $db_col = 'ethnicity';
                break;
            case 'gender_identities':
                $db_col = 'gender_identity';
                break;
            case 'age_groups':
                $db_col = 'age';
                break;
            default:
                $db_col = 'participant_tier';
                break;
        }

        // retrieve controlled vocabulary from db to perform queries on
        $search_vals = $app['db']->fetchAll("SELECT * FROM $search_attr");

        // get date interval breakdown and end date from request parameters
        $interval = $request->get('interval');
        $end_date = $request->get('end_date');
        $start_date = $request->get('start_date');

        // if no start date is supplied, check oldest registration in database
        if (empty($start_date)) {
            $start_date = $app['db']->fetchColumn("SELECT min(enrollment_date) FROM dashboard_participants");
        }

        // assemble array of dates to key graph off of using helper function
        $dates = $this->getDashboardDates($start_date, $end_date, $interval);

        // iterate through search key/value pairs to load results from DB
        $i = 0;
        foreach($search_vals as $entry){
            if ($search_attr == 'age_groups') {
                $vars = [$entry['age_min'], $entry['age_max'], $center_filters];
                $and_clause = "AND $db_col >= ? AND $db_col <= ? AND recruitment_center IN (?)";
                $var_types = [\PDO::PARAM_INT, \PDO::PARAM_INT, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY];
            } else {
                $vars = [$entry['id'], $center_filters];
                $and_clause = "AND $db_col = ? AND recruitment_center IN (?)";
                $var_types = [\PDO::PARAM_STR, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY];
            }
            $counts = [];
            $hover_text = [];
            foreach($dates as $date) {
                $count = $app['db']->fetchAll("SELECT count(*) as COUNT FROM dashboard_participants
                                                  WHERE enrollment_date <= '$date' $and_clause", $vars, $var_types);
                $total = $app['db']->fetchAll("SELECT count(*) as COUNT FROM dashboard_participants
                                                  WHERE enrollment_date <= '$date'", $vars, $var_types);
                array_push($counts, $this->getCount($count, "COUNT"));
                array_push($hover_text, $this->calculatePercentText($this->getCount($count, "COUNT"), $this->getCount($total, "COUNT")));
            };
            $data[] = array(
                "x" => $dates,
                "y" => $counts,
                "text" => $hover_text,
                "hoverinfo" => "text+name",
                "type" => 'bar',
                "name" => $entry['label'],
                "marker" => array(
                    "color" => $this->getColorBrewerVal($i)
                )
            );
            $i++;
        };

        // render JSON data for Plotly
        return $app->json($data);
    }

    public function demo_load_map_dataAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('demo', $request->get('csrf_token')))) {
            return $app->abort(500);
        }
        
        // request parameters
        $map_mode = $request->get('map_mode');
        $end_date = $request->get('end_date');
        $start_date = $request->get('start_date');
        $color_profile = $request->get('color_profile');

        if ($color_profile == 'Custom') {
            $color_profile = [
                [0, 'rgb(247,252,245)'], [0.125, 'rgb(229,245,224)'],[0.25, 'rgb(199,233,192)'],
                [0.375, 'rgb(161,217,155)'],[0.5, 'rgb(116,196,118)'], [0.625, 'rgb(65,171,93)'],
                [0.75, 'rgb(35,139,69)'],[0.875, 'rgb(0,109,44)'],[1, 'rgb(0,68,27)']
            ];
        };

        // if no start date is supplied, check oldest registration in database
        if (empty($start_date)) {
            $start_date = $app['db']->fetchColumn("SELECT min(enrollment_date) FROM dashboard_participants");
        }

        if ($map_mode == 'states') {
            $states = $app['db']->fetchAll("SELECT * FROM state_census_regions");

            $state_registrations = [];
            $state_names = [];

            // grab state names from states array
            foreach($states as $row) {
                array_push($state_names, $row["state"]);
            }
            foreach($state_names as $state) {
                $count = $app['db']->fetchColumn("SELECT count(*) FROM dashboard_participants
                                                  WHERE enrollment_date >= ? AND enrollment_date <= ? 
                                                  AND state = ?", [$start_date, $end_date, $state]);
                array_push($state_registrations, $count);
            }

            $map_data[] = array(
                'type' => 'choropleth',
                'locationmode' => 'USA-states',
                'locations' => $state_names,
                'z' => $state_registrations,
                'text' => $state_names,
                "colorscale" => $color_profile
            );

        } elseif ($map_mode == 'census_regions') {
            $states_by_region = [];
            $registrations_by_state = [];
            $region_text = [];
            $census_regions = $app['db']->fetchAll("SELECT * FROM census_regions");

            foreach($census_regions as $region) {
                $states = $app['db']->fetchAll("SELECT * FROM state_census_regions WHERE census_region_id = ? ORDER BY state", [$region["id"]]);
                $region_states = [];
                foreach($states as $state) {
                    array_push($region_states, $state["state"]);
                }
                $rows = $app['db']->fetchAll("SELECT * FROM dashboard_participants
                                              WHERE enrollment_date >= '$start_date' AND enrollment_date <= '$end_date' 
                                              AND state IN (?)", [$region_states], [\Doctrine\DBAL\Connection::PARAM_STR_ARRAY]);
                foreach($region_states as $state) {
                    array_push($states_by_region, $state);
                    array_push($registrations_by_state, count($rows));
                    array_push($region_text, $region["label"]);
                }
            }

            $map_data[] = array(
                'type' => 'choropleth',
                'locationmode' => 'USA-states',
                'locations' => $states_by_region,
                'z' => $registrations_by_state,
                'text' => $region_text,
                "colorscale" => $color_profile
            );

        } elseif ($map_mode == 'recruitment_centers') {
            $i = 0;
            $recruitment_centers = $app['db']->fetchAll("SELECT * FROM recruitment_centers");
            foreach($recruitment_centers as $location) {
                $count = $app['db']->fetchColumn("SELECT count(*) FROM dashboard_participants 
                                                  WHERE enrollment_date >= ? AND enrollment_date <= ? 
                                                  AND recruitment_center = ?", [$start_date, $end_date, $location["id"]]);
                if ($location["category"] == 'Misc') {
                    $label = "{$location["label"]}: <b>{$count}</b>";
                } else {
                    $label = "{$location["label"]} ({$location['category']}): <b>{$count}</b>";
                }

                $map_data[] = array(
                    'type' => 'scattergeo',
                    'locationmode' => 'USA-states',
                    'lat' => [$location['latitude']],
                    'lon' => [$location['longitude']],
                    'hoverinfo' => 'text',
                    'text' => [$label],
                    'marker' => array(
                        'size' => [$count],
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

        // render JSON for Plotly
        return $app->json($map_data);
    }

    public function demo_load_lifecycle_dataAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('demo', $request->get('csrf_token')))) {
            return $app->abort(500);
        }
        
        // request parameters
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
        $raw_filters = explode(',', $request->get('centers'));
        $center_filters = [];
        foreach($raw_filters as $center) {
            array_push($center_filters, (int) $center);
        }

        // load lifecycle phases and participant tiers for querying
        $lifecyle_phases = $app['db']->fetchAll("SELECT * FROM lifecycle_phases");

        // if no start date is supplied, check oldest registration in database
        if (empty($start_date)) {
            $start_date = $app['db']->fetchColumn("SELECT min(enrollment_date) FROM dashboard_participants");
        }

        $phases = [];
        $counts = [];
        $eligible = [];
        $completed_text = [];
        $eligible_text = [];
        // get participant counts by tier & lifecycle phase
        foreach($lifecyle_phases as $phase) {
            $completed_raw = $app['db']->fetchAll("SELECT count(*) as COUNT FROM dashboard_participants WHERE enrollment_date <= ? 
                                              AND enrollment_date >= ? AND lifecycle_phase >= ? AND recruitment_center in (?)",
                                            [$end_date, $start_date, $phase['id'], $center_filters], [null, null, \PDO::PARAM_INT, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
            $eligible_raw = $app['db']->fetchAll("SELECT count(*) as COUNT FROM dashboard_participants WHERE enrollment_date <= ? 
                                              AND enrollment_date >= ? AND lifecycle_phase >= ? AND recruitment_center in (?)",
                                            [$end_date, $start_date, $phase['id'] - 1, $center_filters], [null, null, \PDO::PARAM_INT, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
            $completed_count = $this->getCount($completed_raw, "COUNT");
            $eligible_count = $this->getCount($eligible_raw, "COUNT");

            $not_completed = $eligible_count - $completed_count;
            array_push($phases, $phase['label']);

            if ($phase['id'] == 7) {
                array_push($counts, $eligible_count);
                array_push($eligible, 0);
                array_push($completed_text, $this->calculatePercentText($eligible_count, $eligible_count));
                array_push($eligible_text, $this->calculatePercentText(0, $eligible_count));
            } else {
                array_push($counts, $completed_count);
                array_push($eligible, $not_completed);
                array_push($completed_text, $this->calculatePercentText($completed_count, $eligible_count));
                array_push($eligible_text, $this->calculatePercentText($not_completed, $eligible_count));
            }
        };

        $data = [array(
            "x" => $phases,
            "y" => $counts,
            "text" => $completed_text,
            "type" => 'bar',
            "hoverinfo" => 'text+name',
            "name" => 'Completed',
            "marker" => array(
                "color" => $this->getColorBrewerVal(1)
            )
        ), array(
            "x" => $phases,
            "y" => $eligible,
            "text" => $eligible_text,
            "type" => 'bar',
            "hoverinfo" => 'text+name',
            "name" => 'Eligible, Not Completed',
            "marker" => array(
                "color" => $this->getColorBrewerVal(0)
            )
        )];

        // render JSON data for Plotly
        return $app->json($data);
    }

    // Main method for retrieving metrics from API
    // stores result in memcache as it only updates daily
    // supports storing multiple entries for faceting
    private function getMetricsObject(Application $app, $facet) {
        $memcache = new \Memcache();
        $memcacheKey = 'metrics_api_facet_' . $facet;
        $metrics = $memcache->get($memcacheKey);
        if (!$metrics) {
            try {
                $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper']);
                $metrics = $metricsApi->metrics([$facet])->bucket;
                // set expiration to four hours
                $memcache->set($memcacheKey, $metrics, 0, 14400);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                return false;
            }
        }
        return $metrics;
    }

    // helper function to check date range cutoffs
    private function checkDates($date, $start, $end) {
        return strtotime($date) >= strtotime($start) && strtotime($date) <= strtotime($end);
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
            return "{$value} (".number_format( $percentage * 100, 2 ) . '%'.")";
        }

    }

    // helper function to return colorbrewer color values (since PHP can't have array constants
    private function getColorBrewerVal($index) {
        // colorbrewer 12-element qualitative colors
        $colors = ['rgb(166,206,227)','rgb(31,120,180)','rgb(178,223,138)','rgb(51,160,44)','rgb(251,154,153)','rgb(227,26,28)',
            'rgb(253,191,111)','rgb(255,127,0)','rgb(202,178,214)','rgb(106,61,154)','rgb(255,255,153)','rgb(177,89,40)'];
        return $colors[$index];
    }

    // helper function to return count values from fetchAll (due to DBAL in query type issues)
    private function getCount($result, $key) {
        return (int) $result[0][$key];
    }

    // helper to return either the keys or values for metrics
    // keys are names returned by metrics API, values are for display
    private function getMetricsKeyVals($kind) {
        $metrics = array(
            "Participant" => "Total Participants",
            "Participant.membership_tier" => "Membership Tier",
            "Participant.gender_identity" => "Gender Identity",
            "Participant.age_range" => "Age Range"
        );

        if ($kind == 'keys') {
            $return_val = array_keys($metrics);
        } elseif ($kind == 'values') {
            $return_val = array_values($metrics);
        } else {
            $return_val = $metrics;
        }
        return $return_val;
    }

    // function to filter metrics API response entries based on requested metric
    private function searchEntries($array, $key, $value) {
        $results = array();
        if (is_array($array)) {
            foreach ($array as $subarray) {
                if ($subarray->$key == $value) {
                    $results[] = $subarray->value;
                }
            }
        }
        return $results;
    }
}
