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
        // Base Routes
        ['home', '/'],
        ['totalProgress', '/total-progress'],
        ['realTime', '/real-time'],
        ['participantsByRegion', '/participants-by-region'],
        ['participantsByLifecycle', '/participants-by-lifecycle'],
        ['ehr', '/ehr'],
        // Data Retrieval
        ['metricsV2Load', '/metrics_load'],
        ['metricsLoadRegion', '/metrics_load_region'],
        ['metricsLoadLifecycle', '/metrics_load_lifecycle'],
        ['metricsLoadEHR', '/metrics_load_ehr']
    ];

    /**
     * @var array
     */
    protected static $color_profiles = [
        'Blackbody', 'Bluered', 'Blues', 'Custom', 'Earth', 'Electric', 'Greens',
        'Hot', 'Jet', 'Picnic', 'Portland', 'Rainbow', 'RdBu', 'Reds', 'Viridis',
        'YlGnBu', 'YlOrRd'
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
        // metrics attributes are hard-coded as we don't have human-readable names in the API yet
        $metrics_attributes = $this->getMetricsDisplayNames();

        $recruitment_centers = $this->getCentersList($app);

        return $app->redirect('total-progress');
    }

    /**
     * Total Progress Action
     *
     * @param Application $app
     *
     * @return Response
     */
    public function totalProgressAction(Application $app)
    {
        // metrics attributes are hard-coded as we don't have human-readable names in the API yet
        $metrics_attributes = $this->getMetricsDisplayNames();

        $recruitment_centers = $this->getCentersList($app);

        return $app['twig']->render(
            'dashboard/total-progress.html.twig',
            [
                'color_profiles' => self::$color_profiles,
                'metrics_attributes' => $metrics_attributes,
                'recruitment_centers' => $recruitment_centers
            ]
        );
    }

    /**
     * Real-time Action
     *
     * @param Application $app
     *
     * @return Response
     */
    public function realTimeAction(Application $app)
    {
        // metrics attributes are hard-coded as we don't have human-readable names in the API yet
        $metrics_attributes = $this->getMetricsDisplayNames();

        $recruitment_centers = $this->getCentersList($app);

        return $app['twig']->render(
            'dashboard/real-time.html.twig',
            [
                'color_profiles' => self::$color_profiles,
                'metrics_attributes' => $metrics_attributes,
                'recruitment_centers' => $recruitment_centers
            ]
        );
    }

    /**
     * Participants by Region Action
     *
     * @param Application $app
     *
     * @return Response
     */
    public function participantsByRegionAction(Application $app)
    {
        // metrics attributes are hard-coded as we don't have human-readable names in the API yet
        $metrics_attributes = $this->getMetricsDisplayNames();

        $recruitment_centers = $this->getCentersList($app);

        return $app['twig']->render(
            'dashboard/participants-by-region.html.twig',
            [
                'color_profiles' => self::$color_profiles,
                'metrics_attributes' => $metrics_attributes,
                'recruitment_centers' => $recruitment_centers
            ]
        );
    }

    /**
     * Particpants by Lifecycle Action
     *
     * @param Application $app
     *
     * @return Response
     */
    public function participantsByLifecycleAction(Application $app)
    {
        // metrics attributes are hard-coded as we don't have human-readable names in the API yet
        $metrics_attributes = $this->getMetricsDisplayNames();

        $recruitment_centers = $this->getCentersList($app);

        return $app['twig']->render(
            'dashboard/participants-by-lifecycle.html.twig',
            [
                'color_profiles' => self::$color_profiles,
                'metrics_attributes' => $metrics_attributes,
                'recruitment_centers' => $recruitment_centers
            ]
        );
    }

    /**
     * EHR Metrics
     *
     * @param Application $app
     *
     * @return Response
     */
    public function ehrAction(Application $app)
    {
        $recruitment_centers = $this->getCentersList($app);
        return $app['twig']->render(
            'dashboard/ehr.html.twig',
            [
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

        if ($centers == 'ALL') {
            $centers = [];
        }

        // set up & sanitize variables
        $start_date = $this->sanitizeDate($start_date);
        $end_date = $this->sanitizeDate($end_date);

        $day_counts = $this->getMetricsObject(
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
            return $app->json([
                'error' => 'No data matched your criteria.'
            ], 400);
        }

        // Roll up the extra HPO dimension by date
        $day_counts = $this->combineHPOsByDate($day_counts);

        switch ($stratification) {
            case 'ENROLLMENT_STATUS':
                if ($history) {
                    $display_values = [
                        'registered' => 'Registered',
                        'consented' => 'Consented',
                        'core' => 'Core Participant'
                    ];
                } else {
                    $display_values = [
                        'INTERESTED' => 'Registered',
                        'MEMBER' => 'Consented',
                        'FULL_PARTICIPANT' => 'Core Participant'
                    ];
                }
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
                    'Prefer not to say' => 'Prefer not to say'
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
                $display_values = [
                    'American_Indian_Alaska_Native' => 'American Indian or Alaska Native',
                    'Asian' => 'Asian',
                    'Black_African_American' => 'Black, African American, or African',
                    'Middle_Eastern_North_African' => 'Middle Eastern or North African',
                    'Native_Hawaiian_other_Pacific_Islander' => 'Native Hawaiian or other Pacific Islander',
                    'White' => 'White',
                    'Hispanic_Latino_Spanish' => 'Hispanic, Latino, or Spanish',
                    'None_Of_These_Fully_Describe_Me' => 'None of these fully describe me',
                    'Prefer_Not_To_Answer' => 'Prefer not to answer',
                    'Multi_Ancestry' => 'Multi-Ancestry',
                    'No_Ancestry_Checked' => 'No ancestry checked'
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

        // Reverse the arrays, as the last item added appears as first value in chart
        $trace_names = array_keys($display_values);

        // if we got this far, we have data!
        // assemble data object in Plotly format
        foreach ($trace_names as $trace_name) {
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

        // Reverse the array, as Plotly.js will put the first item at the bottom
        $data = array_reverse($data);

        // Apply color based on order of appearance
        for ($i = 0; $i < count($data); $i++) {
            $data[$i]['marker'] = [
                "color" => $this->getColorBrewerVal($i)
            ];
        }

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
        $stratification = $request->get('stratification');
        $end_date = $request->get('end_date');
        $centers = $request->get('centers');
        $enrollment_statuses = $request->get('enrollment_statuses');
        $history = $request->get('history', true); // Data available only through history flag
        $color_profile = $request->get('color_profile');

        // Use 'ALL' keyword to send empty filter for awardee
        if ($centers == ['ALL']) {
            $centers = [];
        }

        // load custom green color profile for default
        if ($color_profile == 'Custom') {
            $color_profile = [
                [0, 'rgb(247,252,245)'], [0.125, 'rgb(229,245,224)'], [0.25, 'rgb(199,233,192)'],
                [0.375, 'rgb(161,217,155)'], [0.5, 'rgb(116,196,118)'], [0.625, 'rgb(65,171,93)'],
                [0.75, 'rgb(35,139,69)'], [0.875, 'rgb(0,109,44)'], [1, 'rgb(0,68,27)']
            ];
        };

        // retrieve metrics from cache, or request new if expired
        $metrics = $this->getMetricsObject(
            $app,
            'DAY', // Not relevant to this call
            date('Y-m-d', strtotime($end_date . '-1 day')), // Previous day for start_date
            $end_date,
            $stratification,
            $centers,
            $enrollment_statuses,
            [
                'history' => $history
            ]
        );

        if (!$metrics) {
            return $app->json([
                'error' => 'No data matched your criteria.'
            ], 400);
        }

        // keep track of highest value so that we can normalize the color output accordingly
        // max_val will top out at 100
        $max_val = 0;

        // make sure metrics data exists first, if metrics cache or API fail return value will be false
        if (!empty($metrics)) {
            if ($stratification == 'GEO_STATE') {
                // Roll up the extra HPO dimension by date
                $metrics = $this->combineHPOsByDate($metrics, $centers);

                // Grab relevant data
                $state_metrics = $metrics[0]['metrics'];
                ksort($state_metrics);

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

                // Find maximum value of array
                foreach ($state_metrics as $row) {
                    if ($row > $max_val) {
                        $max_val = $row;
                    }
                }

                // now iterate through states
                foreach ($all_states as $state) {
                    array_push($total_counts, $state_metrics[$state['state']]);
                    array_push($state_registrations, $state_metrics[$state['state']]);
                    array_push($state_names, $state['state']);
                    array_push(
                        $hover_text,
                        "<b>" . $state_metrics[$state['state']] . "</b><br />" . $state['state']
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
                    "zmax" => $max_val,
                    "hoverinfo" => 'text',
                    "colorbar" => [
                        "title" => 'Participants',
                        "titleside" => 'right'
                    ]
                ];
            } elseif ($stratification == 'GEO_CENSUS') {
                // Roll up the extra HPO dimension by date
                $metrics = $this->combineHPOsByDate($metrics, $centers);

                // Grab relevant data
                $census_metrics = $metrics[0]['metrics'];
                ksort($census_metrics);

                $state_registrations = [];
                $total_counts = [];
                $hover_text = [];
                $state_names = [];

                // Pull list of states and census region from database
                try {
                    $all_states = $app['db']->fetchAll(
                        "SELECT scr.state, scr.census_region_id, cr.label
                            FROM state_census_regions scr
                            LEFT JOIN census_regions cr ON scr.census_region_id = cr.id"
                    );
                } catch (\Exception $e) {
                    return $app->abort(500, 'Unable to load states.');
                }

                // Find maximum value of array
                foreach ($census_metrics as $row) {
                    if ($row > $max_val) {
                        $max_val = $row;
                    }
                }

                // now iterate through states
                foreach ($all_states as $state) {
                    array_push($total_counts, $census_metrics[strtoupper($state['label'])]);
                    array_push($state_registrations, $census_metrics[strtoupper($state['label'])]);
                    array_push($state_names, $state['state']);
                    array_push(
                        $hover_text,
                        "<b>" . $census_metrics[strtoupper($state['label'])] . "</b><br />" . $state['label']
                    );
                }

                // Title case region labels
                $regions = [];
                foreach (array_keys($metrics[0]['metrics']) as $row) {
                    array_push($regions, ucwords(strtolower($row)));
                }

                $map_data[] = [
                    'type' => 'choropleth',
                    'locationmode' => 'USA-states',
                    'locations' => $state_names,
                    'regions' => $regions,
                    'region_counts' => array_values($metrics[0]['metrics']),
                    'z' => $state_registrations,
                    'counts' => $total_counts,
                    'text' => $hover_text,
                    "colorscale" => $color_profile,
                    "zmin" => 0,
                    // set floor on max accordingly
                    "zmax" => $max_val,
                    "hoverinfo" => 'text',
                    "colorbar" => [
                        "title" => 'Participants',
                        "titleside" => 'right'
                    ]
                ];
            } elseif ($stratification == 'GEO_AWARDEE') {
                $map_data = [];
                $categorized_centers = $this->getCentersList($app);
                $recruitment_centers = [];
                foreach ($categorized_centers as $categories) {
                    foreach ($categories as $loc) {
                        array_push($recruitment_centers, $loc);
                    }
                }

                // Find the max_val before running through creating the map
                foreach ($metrics as $metric) {
                    if ($max_val < $metric['count']) {
                        $max_val = $metric['count'];
                    }
                }

                // Guard against filtering to a zero-count HPO
                if ($max_val === 0) {
                    $max_val = 0.001;
                }

                $i = 0;
                foreach ($recruitment_centers as $location) {
                    // check if center is requested first before adding to map_data
                    if (empty($centers) || in_array($location['code'], $centers)) {
                        $count = 0;
                        // find appropriate entry
                        foreach ($metrics as $metric) {
                            if (!empty($metric['hpo']) && $metric['hpo'] == $location['code']) {
                                $count = $metric['count'];
                            }
                        }

                        $label = "{$location["label"]} ({$location['category']})<br />" . $count;

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
                                'size' => (($count/$max_val) * 20) + 5,
                                'color' => $this->getColorBrewerVal($i),
                                'line' => [
                                    'color' => 'black',
                                    'width' => 0.5
                                ]
                            ]
                        ];
                        $i++;
                    }
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
        $stratification = $request->get('stratification');
        $end_date = $request->get('end_date');
        $centers = $request->get('centers');
        $enrollment_statuses = $request->get('enrollment_statuses');
        $history = $request->get('history', true); // Data available only through history flag

        // Use 'ALL' keyword to send empty filter for awardee
        if ($centers == ['ALL']) {
            $centers = [];
        }

        // retrieve metrics from cache, or request new if expired
        $metrics = $this->getMetricsObject(
            $app,
            'DAY', // Not relevant to this call
            date('Y-m-d', strtotime($end_date . '-1 day')), // Previous day for start_date
            $end_date,
            $stratification,
            $centers,
            $enrollment_statuses,
            [
                'history' => $history
            ]
        );
        $metrics = $this->combineHPOsByDate($metrics, $centers);

        if (!$metrics) {
            return $app->json([
                'error' => 'No data matched your criteria.'
            ], 400);
        }

        $display_values = [
            'Registered' => 'Registered',
            'Consent_Enrollment' => 'Primary Consent',
            'Consent_Complete' => 'Primary+EHR/EHR-lite Consent',
            'PPI_Module_The_Basics' => 'PPI Module: The Basics',
            'PPI_Module_Overall_Health' => 'PPI Module: Overall Health',
            'PPI_Module_Lifestyle' => 'PPI Module: Lifestyle',
            'Baseline_PPI_Modules_Complete' => 'Baseline PPI Modules Complete',
            'Physical_Measurements' => 'Physical Measurements',
            'Samples_Received' => 'Samples Received',
            'Full_Participant' => 'Core Participant'
        ];

        $completed = [];
        $completed_text = [];
        $not_completed = [];
        $not_completed_text = [];
        foreach ($display_values as $display_key => $display_val) {
            array_push($completed, $metrics[0]['metrics']['completed'][$display_key]);
            array_push($completed_text, sprintf(
                '%s: %d',
                $display_val,
                $metrics[0]['metrics']['completed'][$display_key]
            ));

            array_push($not_completed, $metrics[0]['metrics']['not_completed'][$display_key]);
            array_push($not_completed_text, sprintf(
                '%s: %d',
                $display_val,
                $metrics[0]['metrics']['not_completed'][$display_key]
            ));
        }

        $pipeline_data = [
            [
                "x" => array_values($display_values),
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
                "x" => array_values($display_values),
                "y" => $not_completed,
                "text" => $not_completed_text,
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

    /**
     * Metrics Load EHR
     *
     * @param Application $app
     * @param Request     $request
     *
     * @return Response
     */
    public function metricsLoadEHRAction(Application $app, Request $request)
    {
        if (!$app['csrf.token_manager']->isTokenValid(new CsrfToken('dashboard', $request->get('csrf_token')))) {
            return $app->abort(403);
        }

        // get request attributes
        $mode = $request->get('mode');
        $start_date = $request->get('start_date', date('Y-m-d'));
        $end_date = $request->get('end_date', date('Y-m-d'));
        $interval = $request->get('interval', 'quarter');
        $centers = $request->get('centers', []);
        $params = [];

        $metrics = $this->getMetricsEHRObject($app, $mode, $start_date, $end_date, $interval, $centers, $params);

        switch ($mode) {
            case 'Sites':
                $display_values = [
                    'total_ehr_data_received' => 'Total EHR Data Received',
                    'total_participants' => 'Participants',
                    'hpo_display_name' => 'Awardee Name',
                    'hpo_name' => 'Awardee',
                    'total_ehr_consented' => 'EHR Consent',
                    'total_primary_consented' => 'Primary Consent',
                    'last_ehr_submission_date' => 'Last Submission',
                    'hpo_id' => 'Identifier',
                    'total_core_participants' => 'Total Core Participants'
                ];
                break;
            case 'ParticipantsOverTime':
                $display_values = [
                    'SITES_ACTIVE' => 'Active Sites',
                    'EHR_RECEIVED' => 'EHR Received',
                    'EHR_CONSENTED' => 'EHR Consent'
                ];
                break;
            case 'SitesActiveOverTime':
                $display_values = [
                    'SITES_ACTIVE' => 'Active Sites'
                ];
                break;
            default:
                break;
        }

        $ehr_data = $metrics;
        // $ehr_data = [
        //     [
        //         "x" => array_values($display_values),
        //         "y" => $completed,
        //         "text" => $completed_text,
        //         "type" => 'bar',
        //         "hoverinfo" => 'text+name',
        //         "name" => 'Completed',
        //         "marker" => [
        //             "color" => $this->getColorBrewerVal(1)
        //         ]
        //     ],
        //     [
        //         "x" => array_values($display_values),
        //         "y" => $not_completed,
        //         "text" => $not_completed_text,
        //         "type" => 'bar',
        //         "hoverinfo" => 'text+name',
        //         "name" => 'Eligible, not completed',
        //         "marker" => [
        //             "color" => $this->getColorBrewerVal(0)
        //         ]
        //     ]
        // ];

        return $app->json($ehr_data);
    }

    /* Private Methods */

    /**
     * Get Metrics 2 Object
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
     * @param array       $params
     *
     * @return array
     */
    private function getMetricsObject(
        Application $app,
        $interval,
        $start_date,
        $end_date,
        $stratification,
        $centers,
        $enrollment_statuses,
        $params = []
    ) {
        try {
            $metrics = [];
            $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper'], new \Memcache());

            $metrics = $metricsApi->metrics(
                $start_date,
                $end_date,
                $stratification,
                $centers,
                $enrollment_statuses,
                $params
            );

            // Return false if no metrics returned
            if (count($metrics) == 0) {
                return false;
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            error_log($e->getMessage());
            return false;
        }
        return $metrics;
    }

    /**
     * Get Metrics EHR Object
     *
     * Retrieves data from the Metrics EHR endpoint.
     *
     * @param Application $app
     * @param string      $mode
     * @param string      $start_date
     * @param string      $end_date
     * @param string      $interval
     * @param string      $centers
     * @param array       $params
     *
     * @return array
     */
    private function getMetricsEHRObject(
        Application $app,
        $mode,
        $start_date,
        $end_date,
        $interval,
        $centers,
        $params = []
    ) {
        try {
            $metrics = [];
            $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper'], new \Memcache());

            $metrics = $metricsApi->ehrMetrics(
                $mode,
                $start_date,
                $end_date,
                $interval,
                $centers,
                $params
            );

            // Return false if no metrics returned
            if (count($metrics) == 0) {
                return false;
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            error_log($e->getMessage());
            return false;
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
     * Metrics data comes back as an ordered array of dates and HPOs. This
     * method rolls them up by date.
     *
     * @param array $day_count Source data from API response
     * @param array $centers Centers to include, default to all
     * @return array
     */
    private function combineHPOsByDate($day_count = [], $centers = [])
    {
        if (empty($day_count)) {
            return [];
        }

        if (!is_array($centers)) {
            $centers = explode(',', $centers);
        }

        $output = [];
        foreach ($day_count as $row) {
            // Skip over center if not in list provided
            if (!empty($centers) && !in_array($row['hpo'], $centers)) {
                continue;
            }

            // Create the bucket to store the metric if it doesn't exist
            if (!isset($output[$row['date']])) {
                $output[$row['date']] = [
                    'date' => $row['date'],
                    'metrics' => []
                ];
            }
            // Loop through the metrics and sum them by date
            foreach ($row['metrics'] as $k => $v) {
                // Nested array of values, e.g. LIFECYCLE
                if (is_array($v)) {
                    foreach ($v as $k2 => $v2) {
                        if (!isset($output[$row['date']]['metrics'][$k][$k2])) {
                            $output[$row['date']]['metrics'][$k][$k2] = $v2;
                        } else {
                            $output[$row['date']]['metrics'][$k][$k2] += $v2;
                        }
                    }
                } else {
                    if (!isset($output[$row['date']]['metrics'][$k])) {
                        $output[$row['date']]['metrics'][$k] = $v;
                    } else {
                        $output[$row['date']]['metrics'][$k] += $v;
                    }
                }
            }
        }
        return array_values($output);
    }
}
