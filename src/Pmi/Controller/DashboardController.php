<?php
namespace Pmi\Controller;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Pmi\Drc\RdrMetrics;

class DashboardController extends AbstractController
{
    protected static $name = 'dashboard';
    
    protected static $routes = [
        ['home', '/'],
        ['demo', '/demo'],
        ['metrics_load', '/metrics_load'],
        ['demo_load_data', '/demo_load_data'],
        ['demo_load_map_data', '/demo_load_map_data'],
        ['demo_load_lifecycle_data', '/demo_load_lifecycle_data'],
        ['demo_total_progress', '/demo_total_progress']

    ];

    public function homeAction(Application $app, Request $request)
    {
        return $app['twig']->render('dashboard/index.html.twig');
    }

    public function metrics_loadAction(Application $app, Request $request)
    {
        $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper']);
        // load attribute to query
        $metrics_attribute = $request->get('metrics_attribute');
        $result = $metricsApi->metrics($metrics_attribute)->bucket;
        return $app->json($result);
    }

    // DEMO ACTIONS, NOT FOR DEPLOYMENT

    public function demoAction(Application $app, Request $request)
    {
        $total = $app['db']->fetchColumn("SELECT count(*) from dashboard_participants");
        return $app['twig']->render('dashboard/demo.html.twig', [
            'total_participants' => $total
        ]);
    }

    public function demo_load_dataAction(Application $app, Request $request) {
        // determine search attribute
        $search_attr = $request->get('attribute');

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
        foreach($search_vals as $entry){
            if ($search_attr == 'age_groups') {
                $vars = [$entry['age_min'], $entry['age_max']];
                $and_clause = "AND $db_col >= ? AND $db_col <= ?";
            } else {
                $vars = [$entry['id']];
                $and_clause = "AND $db_col = ?";
            }
            $counts = [];
            foreach($dates as $date) {
                $count = $app['db']->fetchColumn("SELECT count(*) FROM dashboard_participants
                                                  WHERE enrollment_date <= '$date' $and_clause", $vars);
                array_push($counts, $count);
            };
            $data[] = array(
                "x" => $dates,
                "y" => $counts,
                "type" => 'bar',
                "name" => $entry['label']
            );
        };

        // render JSON data for Plotly
        return $app->json($data);
    }

    public function demo_load_map_dataAction(Application $app, Request $request) {
        // request parameters
        $map_mode = $request->get('map_mode');
        $end_date = $request->get('end_date');
        $start_date = $request->get('start_date');

        // if no start date is supplied, check oldest registration in database
        if (empty($start_date)) {
            $start_date = $app['db']->fetchColumn("SELECT min(enrollment_date) FROM dashboard_participants");
        }

        // colorbrewer 12-element qualitative colors
        $colors = ['rgb(166,206,227)','rgb(31,120,180)','rgb(178,223,138)','rgb(51,160,44)','rgb(251,154,153)','rgb(227,26,28)',
            'rgb(253,191,111)','rgb(255,127,0)','rgb(202,178,214)','rgb(106,61,154)','rgb(255,255,153)','rgb(177,89,40)'];

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
                'text' => $state_names
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
                'text' => $region_text
            );

        } elseif ($map_mode == 'recruitment_centers') {
            $i = 0;
            $recruitment_centers = $app['db']->fetchAll("SELECT * FROM recruitment_centers");
            foreach($recruitment_centers as $location) {
                $count = $app['db']->fetchColumn("SELECT count(*) FROM dashboard_participants 
                                                  WHERE enrollment_date >= ? AND enrollment_date <= ? 
                                                  AND recruitment_center = ?", [$start_date, $end_date, $location["id"]]);

                $map_data[] = array(
                    'type' => 'scattergeo',
                    'locationmode' => 'USA-states',
                    'lat' => [$location['latitude']],
                    'lon' => [$location['longitude']],
                    'hoverinfo' => 'text',
                    'text' => ["{$location["label"]}: <b>{$count}</b>"],
                    'marker' => array(
                        'size' => [$count],
                        'color' => $colors[$i],
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

    public function demo_load_lifecycle_dataAction(Application $app, Request $request) {
        // request parameters
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');

        // load lifecycle phases and participant tiers for querying
        $lifecyle_phases = $app['db']->fetchAll("SELECT * FROM lifecycle_phases WHERE id != 7");

        $phases = [];
        $counts = [];
        $eligible = [];
        $completed_text = [];
        $eligible_text = [];
        // get participant counts by tier & lifecycle phase
        foreach($lifecyle_phases as $phase) {
            $completed_count = $app['db']->fetchColumn("SELECT count(*) from dashboard_participants WHERE enrollment_date <= ? 
                                              AND enrollment_date >= ? AND lifecycle_phase >= ?",
                                            [$end_date, $start_date, $phase['id']]);
            $eligible_count = $app['db']->fetchColumn("SELECT count(*) from dashboard_participants WHERE enrollment_date <= ? 
                                              AND enrollment_date >= ? AND lifecycle_phase >= ?",
                                            [$end_date, $start_date, $phase['id'] - 1]);
            $not_completed = $eligible_count - $completed_count;
            array_push($phases, $phase['label']);
            array_push($counts, $completed_count);
            array_push($eligible, $not_completed);
            $pct_completed = $completed_count / $eligible_count;
            $pct_eligible = $not_completed / $eligible_count;
            array_push($completed_text, "{$completed_count} (".number_format( $pct_completed * 100, 2 ) . '%'.")");
            array_push($eligible_text, "{$not_completed} (".number_format( $pct_eligible * 100, 2 ) . '%'.")");
        };

        $data = [array(
            "x" => $phases,
            "y" => $counts,
            "text" => $completed_text,
            "type" => 'bar',
            "hoverinfo" => 'text+name',
            "name" => 'Completed'
        ), array(
            "x" => $phases,
            "y" => $eligible,
            "text" => $eligible_text,
            "type" => 'bar',
            "hoverinfo" => 'text+name',
            "name" => 'Eligible, Not Completed'
        )];

        // render JSON data for Plotly
        return $app->json($data);
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
}
