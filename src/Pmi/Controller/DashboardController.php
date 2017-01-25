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
        // metrics attributes are hard-coded as we don't have human-readable names in the API yet
        $metrics_attributes = $this->getMetricsDisplayNames();

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
        $interval = $request->get('interval');
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
        $facet = $request->get('facet');

        // set facet to NONE if not provided (shows total counts across all HPOs
        if (empty($facet)) {
            $facet = 'NONE';
        }

        // set up & sanitize variables
        $start_date = $this->sanitizeDate($start_date);
        $control_dates = array_reverse($this->getDashboardDates($start_date, $end_date, $interval));
        $data = [];
        $dates = [];
        $values = [];
        $hover_text = [];

        // retrieve controlled vocab for entries from metrics cache
        if ($filter_by != 'Participant') {
            $entries = $this->getMetricsFieldDefinitions($app, $filter_by);
        } else {
            $entries = [$filter_by];
        }

        // iterate again to grab values now that we have all possible entries
        foreach($control_dates as $date) {
            if ($this->checkDates($date, $start_date, $end_date, $control_dates)) {
                // grab date for x axis
                array_push($dates, $date);
                $metrics = $this->getMetricsObject($app, $facet, $date);
                // iterate through list of control values to get counts
                foreach ($entries as $entry) {
                    // construct lookup key
                    if ($filter_by == 'Participant') {
                        $lookup = $filter_by;
                    } else {
                        $lookup = $filter_by . "." . $entry;
                    }
                    // zero value are not stored in API, so check if key exists first
                    if (array_key_exists($lookup, $metrics)) {
                        $val = $metrics[$lookup];
                    } else {
                        $val = 0;
                    }

                    $values[$entry][] = $val;

                    // generate hover text if not doing total participant count
                    if ($filter_by != 'Participant') {
                        // grab total (since searchEntries returns array and there is only one val
                        // it's safe to always grab the first value
                        $total = $metrics['Participant'];
                        $hover_text[$entry][] = $this->calculatePercentText($val, $total) . '<br />' . $date;
                    }
                }

            }
        }
        // reverse sort so that they appear in ascending alphabetical order in the legend
        rsort($entries);

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

    public function metrics_load_regionAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('dashboard', $request->get('csrf_token')))) {
            return $app->abort(500);
        }

        // get request attributes
        $map_mode = $request->get('map_mode');
        $end_date = $this->sanitizeDate($request->get('end_date'));
        $color_profile = $request->get('color_profile');

        if ($color_profile == 'Custom') {
            $color_profile = [
                [0, 'rgb(247,252,245)'], [0.125, 'rgb(229,245,224)'],[0.25, 'rgb(199,233,192)'],
                [0.375, 'rgb(161,217,155)'],[0.5, 'rgb(116,196,118)'], [0.625, 'rgb(65,171,93)'],
                [0.75, 'rgb(35,139,69)'],[0.875, 'rgb(0,109,44)'],[1, 'rgb(0,68,27)']
            ];
        };

        // retrieve metrics from cache, or request new if expired
        $results = $this->getMetricsObject($app);

        // find closest date to requested cutoff and retrieve metrics
        // iterate in reverse order as last objects are most recent
        // break once value has been set
        $lookup_date = $end_date . "T00:00:00";
        $i = count($results);
        $raw_results = [];
        while ($i) {
            $i--;
            if ($results[$i]->date == $lookup_date) {
                $raw_results = $results[$i]->entries;
                break;
            } elseif (strtotime($results[$i]->date) < strtotime($lookup_date)) {
                // if date did not exists, grab current row as it will be closest to actual cutoff
                $raw_results = $results[$i]->entries;
                break;
            }
        }

        $map_data = [];

        if (!empty($results)) {
            if ($map_mode == 'Participant.state') {
                $state_registrations = [];
                // grab state names from field definitions cache
                $state_names = $this->getMetricsFieldDefinitions($app, 'Participant.state');

                foreach($state_names as $state) {
                    $state_lookup = $map_mode . "." . $state;
                    $count = 0;

                    // if data is present, set, otherwise leave default 0 count
                    $match = $this->searchEntries($raw_results, 'name', $state_lookup);
                    if (!empty($match)) {
                        $count = $match[0];
                    }

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
            } else if ($map_mode == 'Participant.censusRegion') {
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

                    // construct lookup key for census region count (combination of map mode and census region (upcased))
                    $census_lookup = $map_mode . "." . strtoupper($region['label']);
                    $count = 0;

                    // if data is present, set, otherwise leave default 0 count
                    $match = $this->searchEntries($raw_results, 'name', $census_lookup);
                    if (!empty($match)) {
                        $count = $match[0];
                    }

                    foreach($region_states as $state) {
                        array_push($states_by_region, $state);
                        array_push($registrations_by_state, $count);
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
            }
        }

        // return json
        return $app->json($map_data);
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
        // use sanitize function to prevent SQL injections on date vals
        $interval = $request->get('interval');
        $end_date = $this->sanitizeDate($request->get('end_date'));
        $start_date = $request->get('start_date');

        // if no start date is supplied, check oldest registration in database
        if (empty($start_date)) {
            $start_date = $app['db']->fetchColumn("SELECT min(enrollment_date) FROM dashboard_participants");
        }

        // once start date is set, sanitize
        $start_date = $this->sanitizeDate($start_date);

        // assemble array of dates to key graph off of using helper function
        $dates = $this->getDashboardDates($start_date, $end_date, $interval);

        // iterate through search key/value pairs to load results from DB
        $i = 0;
        foreach($search_vals as $entry){
            $counts = [];
            $hover_text = [];
            foreach($dates as $date) {
                if ($search_attr == 'age_groups') {
                    $count = $app['db']->fetchAll("SELECT count(*) as COUNT FROM dashboard_participants
                                                  WHERE enrollment_date <= ? and age >= ? and age <= ? AND recruitment_center IN (?)",
                                                  [$date, $entry['age_min'], $entry['age_max'], $center_filters],
                                                  [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_INT, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);

                    $total = $app['db']->fetchAll("SELECT count(*) as COUNT FROM dashboard_participants
                                                  WHERE enrollment_date <= ?", [$date], [\PDO::PARAM_STR]);
                    array_push($counts, $this->getCount($count, "COUNT"));
                    array_push($hover_text, $this->calculatePercentText($this->getCount($count, "COUNT"), $this->getCount($total, "COUNT")));

                } else {
                    $count = $app['db']->fetchAll("SELECT count(*) as COUNT FROM dashboard_participants
                                                  WHERE enrollment_date <= ? AND $db_col = ? AND recruitment_center IN (?)", [$date, $entry['id'], $center_filters],
                                                  [\PDO::PARAM_STR, \PDO::PARAM_INT, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
                    $total = $app['db']->fetchAll("SELECT count(*) as COUNT FROM dashboard_participants
                                                  WHERE enrollment_date <= ?", [$date], [\PDO::PARAM_STR]);
                    array_push($counts, $this->getCount($count, "COUNT"));
                    array_push($hover_text, $this->calculatePercentText($this->getCount($count, "COUNT"), $this->getCount($total, "COUNT")));
                }
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
        // use date sanitizers to prevent sql injections
        $map_mode = $request->get('map_mode');
        $end_date = $this->sanitizeDate($request->get('end_date'));
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

        $start_date = $this->sanitizeDate($start_date);

        if ($map_mode == 'states') {
            $states = $app['db']->fetchAll("SELECT * FROM state_census_regions");

            $state_registrations = [];
            $state_names = [];

            // grab state names from states array
            foreach($states as $row) {
                array_push($state_names, $row["state"]);
            }
            foreach($state_names as $state) {
                $count = $app['db']->fetchAll("SELECT count(*) AS COUNT FROM dashboard_participants
                                                  WHERE enrollment_date >= ? AND enrollment_date <= ? 
                                                  AND state = ?", [$start_date, $end_date, $state], [\PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_STR]);
                array_push($state_registrations, $this->getCount($count, "COUNT"));
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
                                              WHERE enrollment_date >= ? AND enrollment_date <= ? 
                                              AND state IN (?)", [$start_date, $end_date, $region_states], [\PDO::PARAM_STR, \PDO::PARAM_STR, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY]);
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
                $count = $app['db']->fetchAll("SELECT count(*) as COUNT FROM dashboard_participants 
                                                  WHERE enrollment_date >= ? AND enrollment_date <= ? 
                                                  AND recruitment_center = ?", [$start_date, $end_date, $location["id"]], [\PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_INT]);
                if ($location["category"] == 'Misc') {
                    $label = "{$location["label"]}: <b>{$this->getCount($count, "COUNT")}</b>";
                } else {
                    $label = "{$location["label"]} ({$location['category']}): <b>{$this->getCount($count, "COUNT")}</b>";
                }

                $map_data[] = array(
                    'type' => 'scattergeo',
                    'locationmode' => 'USA-states',
                    'lat' => [$location['latitude']],
                    'lon' => [$location['longitude']],
                    'hoverinfo' => 'text',
                    'text' => [$label],
                    'marker' => array(
                        'size' => [$this->getCount($count, "COUNT")],
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
        $end_date = $this->sanitizeDate($request->get('end_date'));
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

        // sanitize start date once set
        $start_date = $this->sanitizeDate($start_date);

        $phases = [];
        $counts = [];
        $eligible = [];
        $completed_text = [];
        $eligible_text = [];
        // get participant counts by tier & lifecycle phase
        foreach($lifecyle_phases as $phase) {
            $completed_raw = $app['db']->fetchAll("SELECT count(*) as COUNT FROM dashboard_participants WHERE enrollment_date <= ? 
                                              AND enrollment_date >= ? AND lifecycle_phase >= ? AND recruitment_center in (?)",
                                            [$end_date, $start_date, $phase['id'], $center_filters], [\PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_INT, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
            $eligible_raw = $app['db']->fetchAll("SELECT count(*) as COUNT FROM dashboard_participants WHERE enrollment_date <= ? 
                                              AND enrollment_date >= ? AND lifecycle_phase >= ? AND recruitment_center in (?)",
                                            [$end_date, $start_date, $phase['id'] - 1, $center_filters], [\PDO::PARAM_STR, \PDO::PARAM_STR, \PDO::PARAM_INT, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]);
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
    // each entry is comprised of a single day
    // stores each entry as a combination of facet and date
    private function getMetricsObject(Application $app, $facet, $date) {
        $memcache = new \Memcache();
        $memcacheKey = 'metrics_api_' . $facet . '_' . $date;
        $metrics = $memcache->get($memcacheKey);
        if (!$metrics) {
            try {
                $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper']);
                $all_metrics = $metricsApi->metrics($date, $date);
                if ($facet == 'NONE') {
                    $metrics = $all_metrics[0]['entries'];
                } else {
                    foreach($all_metrics as $entry) {
                        $entry_facet = $entry['facets']['hpoId'];
                        if ($entry_facet == $facet) {
                            // set expiration to one day
                            $metrics = $entry['entries'];
                            // exit loop as we now have the day & facet we were looking for
                            break;
                        }
                    }
                }
                $memcache->set($memcacheKey, $metrics, 0, 86400);
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

    // stores display names for metrics attibute field names
    // can return all key-value pairs, or just the display name for a given field
    private function getMetricsDisplayNames($field = '') {
        $metrics_attributes = array(
            "Participant" => "Total Participants",
            "Participant.membershipTier" => "Membership Tier",
            "Participant.genderIdentity" => "Gender Identity",
            "Participant.ageRange" => "Age Range",
            "Participant.ethnicity" => "Ethnicity",
            "Participant.race" => "Race"
        );
        if (empty($field)){
            return $metrics_attributes;
        } else {
            return $metrics_attributes[$field];
        }
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
        // colorbrewer 12-element qualitative colors
        $colors = ['rgb(166,206,227)','rgb(31,120,180)','rgb(178,223,138)','rgb(51,160,44)','rgb(251,154,153)','rgb(227,26,28)',
            'rgb(253,191,111)','rgb(255,127,0)','rgb(202,178,214)','rgb(106,61,154)','rgb(255,255,153)','rgb(177,89,40)'];
        return $colors[$index];
    }

    // helper function to return count values from fetchAll (due to DBAL in query type issues)
    private function getCount($result, $key) {
        return (int) $result[0][$key];
    }

    // sanitize url date parameter to prevent SQL injection
    private function sanitizeDate($date_string) {
        return date('Y-m-d', strtotime($date_string));
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
