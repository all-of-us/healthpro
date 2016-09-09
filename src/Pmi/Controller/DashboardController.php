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
        ['load_data', '/load_data']
    ];

    public function homeAction(Application $app, Request $request)
    {
        return $app['twig']->render('dashboard/index.html.twig');
    }

    public function demoAction(Application $app, Request $request)
    {

        return $app['twig']->render('dashboard/demo.html.twig', []);
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

        // get date interval breakdown and end date
        $interval = $request->get('interval');
        $end_date = $request->get('end_date');
        $oldest_reg = $app['db']->fetchColumn("SELECT min(enrollment_date) from dashboard_participants");
        // assemble array of dates to key graph off of
        $dates = [$end_date];
        $i = 0;
        while (strtotime($dates[$i]) >= strtotime($oldest_reg)){
            $d = strtotime("-1 $interval", strtotime($dates[$i]));
            array_push($dates, date('Y-m-d', $d));
            $i++;
        }
        $data = [];

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
        return $app['twig']->render('dashboard/load_data.json.twig', [
            'data' => $data
        ]);
    }
}
