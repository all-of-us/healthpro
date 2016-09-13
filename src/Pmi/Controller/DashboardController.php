<?php
namespace Pmi\Controller;

use GuzzleHttp\Psr7\Response;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class DashboardController extends AbstractController
{
    protected static $name = 'dashboard';
    
    protected static $routes = [
        ['home', '/'],
        ['demo', '/demo'],
        ['load_data', '/load_data'],
        ['load_map_data', '/load_map_data']
    ];

    public function homeAction(Application $app, Request $request)
    {
        return $app['twig']->render('dashboard/index.html.twig');
    }

    public function demoAction(Application $app, Request $request)
    {

        return $app['twig']->render('dashboard/demo.html.twig', ['app' => $app]);
    }

    public function load_dataAction(Application $app, Request $request) {
        // set up variables for segmenting bar graphs
        $genders = ['Female', 'Female to Male Transgender', 'Male', 'Male to Female Transgender', 'Intersex',
            'Other (please specify)', 'Prefer not to Answer'];
        $enrollment_statuses = array(
            2 => 'Consented',
            3 => 'Engaged'
        );
        $races = ['American Indian or Alaskan Native', 'Asian', 'Black or African American',
            'Native Hawaiian or Pacific Islander', 'White', 'Other', 'Prefer not to answer'];
        $ethnicities = ['Hispanic, Latino or Spanish Origin', 'Not of Hispanic, Latino or Spanish Origin','Prefer not to answer'];
        $age_groups = ['18-25','26-35','36-45','46-55','56-65','66-75','75-86','85+'];

        // determine search attribute
        $search_attr = $request->get('attribute');
        switch ($search_attr) {
            case 'enrollment_statuses':
                $search_vals = $enrollment_statuses;
                break;
            case 'race':
                $search_vals = $races;
                break;
            case 'ethnicity':
                $search_vals = $ethnicities;
                break;
            case 'gender':
                $search_vals = $genders;
                break;
            case 'age':
                $search_vals = $age_groups;
                break;
            default:
                $search_vals = $enrollment_statuses;
                break;
        }

        // get date interval breakdown and end date from request parameters
        $interval = $request->get('interval');
        $end_date = $request->get('end_date');
        $oldest_reg = $app['db']->fetchColumn("SELECT min(enrollment_date) from dashboard_participants");

        // assemble array of dates to key graph off of using helper function
        $dates = $this->getDashboardDates($oldest_reg, $end_date, $interval);

        // iterate through search key/value pairs to load results from DB
        foreach($search_vals as $key => $value){
            if ($search_attr == 'age') {
                $counts = [];
                $age_values = explode('-', $value);
                // parse out high/low ages from ranges
                if (count($age_values) > 1) {
                    $age_low = (int)$age_values[0];
                    $age_high = (int)$age_values[1];
                } else {
                // we know value is 85+, so grab first value and set arbitrary high age
                    $age_low = (int)chop($age_values[0],"+");
                    $age_high = 9999;
                }
                foreach($dates as $date) {
                    $count = $app['db']->fetchColumn("SELECT count(age) FROM dashboard_participants 
                                                  WHERE enrollment_date <= ? AND age >= ? and age <= ?", [$date, $age_low, $age_high]);
                    array_push($counts, $count);
                };
                $data[] = array(
                    "x" => $dates,
                    "y" => $counts,
                    "type" => 'bar',
                    "name" => $value
                );
            } else {
                $counts = [];
                if ($search_attr == 'enrollment_status') {
                    $lookup_val = $key;
                } else {
                    $lookup_val = $value;
                }
                foreach($dates as $date) {
                    $count = $app['db']->fetchColumn("SELECT count($search_attr) FROM dashboard_participants 
                                                  WHERE enrollment_date <= ? AND $search_attr = ?", [$date, $lookup_val]);
                    array_push($counts, $count);
                };
                $data[] = array(
                    "x" => $dates,
                    "y" => $counts,
                    "type" => 'bar',
                    "name" => $value
                );
            }
        };

        // render JSON data for Plotly
        return $app->json($data);
    }

    public function load_map_dataAction(Application $app, Request $request) {
        // array of US states and census regions
        $states = ['AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'DC', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY',
            'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH',
            'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'];

        $census_regions = array(
            'South' => ["AL", "AR", "DC", "DE", "FL", "GA", "KY", "LA", "MD", "MS", "NC", "OK", "SC", "TN", "TX", "VA", "WV"],
            'West' => ["AK", "AZ", "CA", "CO", "HI", "ID", "MT", "NM", "NV", "OR", "UT", "WA", "WY"],
            'Midwest' => ["IA", "IN", "IL", "KS", "MI", "MN", "MO", "ND", "NE", "OH", "SD", "WI"],
            'Northeast' => ["CT", "MA", "ME", "NH", "NJ", "NY", "PA", "RI", "VT"]
        );

        // arrays of latitude/longitude coords for approx. locations of centers of census regions
        $census_lats = array('South' => '33.65', 'West' => '40.78', 'Midwest' => '41.90', 'Northeast' => '42.75');
        $census_longs = array('South' => '-84.42', 'West' => '-111.97', 'Midwest' => '-87.65', 'Northeast' => '-73.80');

        // request parameters
        $end_date = $request->get('end_date');
        $map_mode = $request->get('map_mode');

        if ($map_mode == 'states') {
            // load state-level registration numbers as of end date
            $state_registrations = [];

            foreach($states as $state) {
                $count = $app['db']->fetchColumn("select count(*) FROM dashboard_participants
                                              WHERE enrollment_date <= ? and state = ?", [$end_date, $state]);
                array_push($state_registrations, $count);
            }

            $map_data[] = array(
                'type' => 'choropleth',
                'locationmode' => 'USA-states',
                'locations' => $states,
                'z' => $state_registrations,
                'text' => $states
            );
        } else {
            foreach($census_regions as $region => $region_states) {
                $total = 0;
                $curr_states = "{$region}: ";
                foreach($region_states as $state) {
                    $count = $app['db']->fetchColumn("select count(*) FROM dashboard_participants
                                              WHERE enrollment_date <= ? and state = ?", [$end_date, $state]);
                    $total += $count;
                    $curr_states .= $state.',';
                }

                $map_data[] = array(
                    'type' => 'scattergeo',
                    'locationmode' => 'USA-states',
                    'lat' => [$census_lats[$region]],
                    'lon' => [$census_longs[$region]],
                    'mode' => 'markers+text',
                    'hoverinfo' => 'none',
                    'text' => ["{$region}: {$total}"],
                    'marker' => array(
                        'size' => $total,
                        'line' => array(
                            'color' => 'black',
                            'width' => 1
                        )
                    )
                );
            }
        }

        // render JSON for Plotly
        return $app->json($map_data);
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
