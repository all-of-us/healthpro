<?php
namespace Pmi\Application;

use App\EventListener\ResponseSecurityHeadersTrait;
use Pmi\Audit\Log;
use Pmi\Entities\Configuration;
use Pmi\Security\User;
use Pmi\Security\UserProvider;
use Pmi\Service\NoticeService;
use Pmi\Service\UserService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use App\Service\HelpService;

class HpoApplication extends AbstractApplication
{
    use ResponseSecurityHeadersTrait;

    protected $configuration = [];
    protected $participantSource = 'rdr';
    protected $siteNameMapper = [];
    protected $organizationNameMapper = [];
    protected $awardeeNameMapper = [];

    public function setup($config = [])
    {
        parent::setup($config);

        $this->registerDb();

        $this['pmi.drc.rdrhelper'] = new \Pmi\Drc\RdrHelper($this->getRdrOptions());
        if ($this->participantSource == 'mock') {
            $this['pmi.drc.participants'] = new \Pmi\Drc\MockParticipantSearch();
        } else {
            $this['pmi.drc.participants'] = new \Pmi\Drc\RdrParticipants($this['pmi.drc.rdrhelper']);
        }

        $this['pmi.drc.appsclient'] = (!$this->isProd() && ($this['isUnitTest'] || $this->getConfig('gaBypass'))) ?
             \Pmi\Drc\MockAppsClient::createFromApp($this) : \Pmi\Drc\AppsClient::createFromApp($this);
        return $this;
    }

    protected function registerSecurity()
    {
        $this['app.googlegroups_authenticator'] = function ($app) {
            return new \Pmi\Security\GoogleGroupsAuthenticator($app);
        };

        $app = $this;
        // include `/` in common routes because homeAction will redirect based on role
        $commonRegex = '^/(logout|login-return|keepalive|client-timeout|agree)?$';
        $anonRegex = '^/(timeout$|login$|mock-login$|cron\/|_ah\/)'; // cron and _ah controllers have their own access control
        $this->register(new \Silex\Provider\SecurityServiceProvider(), [
            'security.firewalls' => [
                'anonymous' => [
                    'pattern' => $anonRegex,
                    'anonymous' => true
                ],
                'main' => [
                    'pattern' => '^/.*$',
                    'guard' => [
                        'authenticators' => [
                            'app.googlegroups_authenticator'
                        ]
                    ],
                    'users' => function () use ($app) {
                        return new UserProvider($app);
                    }
                ]
            ],
            'security.access_rules' => [
                [['path' => $anonRegex], 'IS_AUTHENTICATED_ANONYMOUSLY'],
                [['path' => '^/_dev($|\/)$'], 'IS_AUTHENTICATED_FULLY'],
                [['path' => $commonRegex], 'IS_AUTHENTICATED_FULLY'],
                [['path' => '^/admin($|\/)'], 'ROLE_ADMIN'],
                [['path' => '^/workqueue($|\/)'], ['ROLE_USER', 'ROLE_AWARDEE']],
                [['path' => '^/problem($|\/)'], ['ROLE_DV_ADMIN']],
                [['path' => '^/site($|\/)'], ['ROLE_USER', 'ROLE_AWARDEE']],
                [['path' => '^/help($|\/)'], ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_AWARDEE', 'ROLE_DV_ADMIN']],
                [['path' => '^/biobank\/\w+\/(order|quanum-order)\/\w+$'], ['ROLE_AWARDEE', 'ROLE_BIOBANK', 'ROLE_SCRIPPS']],
                [['path' => '^/biobank($|\/)'], ['ROLE_BIOBANK', 'ROLE_SCRIPPS']],
                [['path' => '^/settings($|\/)'], ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_AWARDEE', 'ROLE_DV_ADMIN', 'ROLE_BIOBANK', 'ROLE_SCRIPPS', 'ROLE_AWARDEE_SCRIPPS']],
                [['path' => '^/.*$'], 'ROLE_USER'],
            ]
        ]);
    }

    protected function loadConfiguration($override = [])
    {
        // default two-factor setting
        $this->configuration['enforce2fa'] = $this->isProd();

        $appDir = realpath(__DIR__ . '/../../../');
        $configFile = $appDir . '/dev_config/config.yml';
        if ($this->isLocal() && file_exists($configFile)) {
            $yaml = new \Symfony\Component\Yaml\Parser();
            $configs = $yaml->parse(file_get_contents($configFile));
            if (is_array($configs) || count($configs) > 0) {
                foreach ($configs as $key => $val) {
                    $this->configuration[$key] = $val;
                }
            }
        }

        // circle ci db configurations
        $circleConfigFile = $appDir . '/ci/config.yml';
        if (getenv('CI') && $this['isUnitTest'] && file_exists($circleConfigFile)) {
            $yaml = new \Symfony\Component\Yaml\Parser();
            $configs = $yaml->parse(file_get_contents($circleConfigFile));
            if (is_array($configs) || count($configs) > 0) {
                foreach ($configs as $key => $val) {
                    $this->configuration[$key] = $val;
                }
            }
        }

        // unit tests don't have access to Datastore
        // local environment uses yaml file
        if (!$this['isUnitTest'] && !$this->isPhpDevServer() && !$this->isLocal()) {
            $configs = Configuration::fetchBy();
            foreach ($configs as $config) {
                $this->configuration[$config->key] = $config->value;
            }
        }

        foreach ($override as $key => $val) {
            $this->configuration[$key] = $val;
        }
    }

    protected function registerDb()
    {
        $socket = $this->getConfig('mysql_socket');
        $host = $this->getConfig('mysql_host');
        $schema = $this->getConfig('mysql_schema');
        $user = $this->getConfig('mysql_user');
        $password = $this->getConfig('mysql_password');
        if ($this->isLocal()) {
            // look for Docker environment variables override
            if (getenv('MYSQL_HOST')) {
                $host = getenv('MYSQL_HOST');
            }
            if (getenv('MYSQL_DATABASE')) {
                $schema = getenv('MYSQL_DATABASE');
            }
            if (getenv('MYSQL_USER')) {
                $user = getenv('MYSQL_USER');
            }
            if (getenv('MYSQL_PASSWORD') !== false) {
                $password = getenv('MYSQL_PASSWORD');
            }
        }
        if ($socket) {
            $options = [
                'driver' => 'pdo_mysql',
                'unix_socket' => $socket,
                'dbname' => $schema,
                'user' => $user,
                'password' => $password,
                'charset' => 'utf8mb4'
            ];
        } else {
            $options = [
                'driver' => 'pdo_mysql',
                'host' => $host,
                'dbname' => $schema,
                'user' => $user,
                'password' => $password,
                'charset' => 'utf8mb4'
            ];
        }
        $this->register(new \Silex\Provider\DoctrineServiceProvider(), [
            'db.options' => $options
        ]);

        $this['em'] = new \Pmi\EntityManager\EntityManager();
        $this['em']->setDbal($this['db']);
    }

    public function switchSite($email)
    {
        $user = $this->getUser();
        if ($user && $user->belongsToSite($email)) {
            $this['session']->set('site', $user->getSite($email));
            $this['session']->remove('awardee');
            $this->setNewRoles($user);
            $this->saveSiteMetaDataInSession();
            return true;
        } elseif ($user && $user->belongsToAwardee($email)) {
            $this['session']->set('awardee', $user->getAwardee($email));
            $this['session']->remove('site');
            $this->setNewRoles($user);
            // Clears previously set site meta data
            $this->saveSiteMetaDataInSession();
            return true;
        } else {
            return false;
        }
    }

    protected function setNewRoles($user)
    {
        $userRoles = UserService::getRoles($user->getAllRoles(), $this['session']->get('site'), $this['session']->get('awardee'));
        if ($user->getAllRoles() != $userRoles) {
            $token = new PostAuthenticationGuardToken($user, 'main', $userRoles);
            $this['security.token_storage']->setToken($token);
        }
    }

    /** Returns the user's currently selected HPO site. */
    public function getSite()
    {
        return $this['session']->get('site');
    }

    public function getAwardee()
    {
        return $this['session']->get('awardee');
    }

    public function getSiteId()
    {
        if ($site = $this->getSite()) {
            return $site->id;
        } else {
            return null;
        }
    }

    public function getSiteIdWithPrefix()
    {
        if ($site = $this->getSite()) {
            return \Pmi\Security\User::SITE_PREFIX . $site->id;
        } else {
            return null;
        }
    }

    public function getAwardeeId()
    {
        if ($awardee = $this->getAwardee()) {
            return $awardee->id;
        } else {
            return null;
        }
    }

    public function getSiteEntity()
    {
        $googleGroup = $this->getSiteId();
        if (!$googleGroup) {
            return null;
        }
        return $this['em']
            ->getRepository('sites')
            ->fetchOneBy(['deleted' => 0, 'google_group' => $googleGroup]);
    }

    public function getAwardeeEntity()
    {
        $googleGroup = $this->getAwardeeId();
        if (!$googleGroup) {
            return null;
        }
        return $this['em']
            ->getRepository('sites')
            ->fetchBy(['deleted' => 0, 'awardee' => $googleGroup]);
    }

    public function getSiteOrganization()
    {
        return $this['session']->get('siteOrganization');
    }

    public function getSiteOrganizationId()
    {
        return $this['session']->get('siteOrganizationId');
    }

    public function getSiteOrganizationDisplayName()
    {
        return $this['session']->get('siteOrganizationDisplayName');
    }

    public function getSiteAwardee()
    {
        return $this['session']->get('siteAwardee');
    }

    public function getSiteAwardeeId()
    {
        return $this['session']->get('siteAwardeeId');
    }

    public function getSiteAwardeeDisplayName()
    {
        return $this['session']->get('siteAwardeeDisplayName');
    }

    public function getCurrentSiteDisplayName()
    {
        return $this['session']->get('currentSiteDisplayName');
    }

    public function getAwardeeOrganization()
    {
        if ($this['isUnitTest']) {
            return null;
        }
        $sites = $this->getAwardeeEntity();
        if (!$sites) {
            return null;
        } else {
            $organizations = [];
            foreach ($sites as $site) {
                if (!empty($site['organization'])) {
                    $organizations[] = $site['organization'];
                }
            }
            if (empty($organizations)) {
                return null;
            } else {
                return $organizations;
            }
        }
    }

    public function getSitesFromOrganization($org)
    {
        return $this['em']->getRepository('sites')->fetchBy([
            'deleted' => 0,
            'status' => 1,
            'organization' => $org,
        ]);
    }

    public function isDVType() {
        $site = $this['em']->getRepository('sites')->fetchBy([
            'deleted' => 0,
            'google_group' => $this->getSiteId(),
            'type' => 'DV'
        ]);
        return !empty($site);
    }

    public function isDiversionPouchSite()
    {
        if (!isset($this->configuration['diversion_pouch_site'])) {
            return false;
        }
        $site = $this['em']->getRepository('sites')->fetchBy([
            'deleted' => 0,
            'google_group' => $this->getSiteId(),
            'site_type' => $this->configuration['diversion_pouch_site']
        ]);
        return !empty($site);
    }

    public function getOrderType()
    {
        if ($this->isDVType() && !$this->isDiversionPouchSite()) {
            return 'dv';
        }
        return 'hpo';
    }

    public function getSiteType()
    {
        return $this->isDVType() ? 'dv' : 'hpo';
    }

    protected function earlyBeforeCallback(Request $request, AbstractApplication $app)
    {
        if ($request->getBasePath() === '/web') {
            return $this->abort(404);
        }
    }

    protected function beforeCallback(Request $request, AbstractApplication $app)
    {
        $app['twig']->addGlobal('confluenceResources', HelpService::$confluenceResources);
        $app['twig']->addGlobal('feedback_url', HelpService::getFeedbackUrl());

        $app->log(Log::REQUEST);

        // log the user out if their session is expired
        if ($this->isLoginExpired() && $request->attributes->get('_route') !== 'logout') {
            return $this->redirectToRoute('logout', ['timeout' => true]);
        }

        if ($this['session']->get('isLogin')) {
            $app->log(Log::LOGIN_SUCCESS, $this->getUser()->getRoles());
            $this->addFlashSuccess('Welcome, ' . $this->getUser()->getEmail() . '!');
        }

        if ($this->isLoggedIn()) {
            $user = $this->getUser();
            $this['em']->setTimezone($this->getUserTimezone());
        }

        // HPO users must select their site first
        if (!$this->getSite() && !$this->getAwardee() && $this->isLoggedIn() && ($this['security.authorization_checker']->isGranted('ROLE_USER') || $this['security.authorization_checker']->isGranted('ROLE_AWARDEE')))
        {
            // auto-select since they only have one site
            if (count($user->getSites()) === 1 && empty($user->getAwardees()) && $this->isValidSite($user->getSites()[0]->email)) {
                $this->switchSite($user->getSites()[0]->email);
            } elseif (count($user->getAwardees()) === 1 && empty($user->getSites())) {
                $this->switchSite($user->getAwardees()[0]->email);
            } elseif ($request->attributes->get('_route') !== 'selectSite' &&
                    $request->attributes->get('_route') !== 'switchSite' &&
                    strpos($request->attributes->get('_route'), 'problem_') !== 0 &&
                    strpos($request->attributes->get('_route'), 'admin_') !== 0 &&
                    strpos($request->attributes->get('_route'), 'biobank_') !== 0 &&
                    !$this->isUpkeepRoute($request)) {
                return $this->forwardToRoute('selectSite', $request);
            }
        }

        // Display cross-awardee warning on all participant pages
        if ($request->attributes->has('participantId')) {
            $participantId = $request->attributes->get('participantId');
            // Check session value
            if (empty($this['session']->get('agreeCrossOrg_' . $participantId))) {
                $participant = $this['pmi.drc.participants']->getById($participantId);
                // Check cross-awardee
                if (!empty($participant) && $participant->hpoId !== $this->getSiteOrganization()) {
                    return $this->redirectToRoute('participant', ['id' => $participantId, 'return' => $request->getRequestUri()]);
                }
            }
        }

        $noticeService = new NoticeService($this['em']);
        $notices = $noticeService->getCurrentNotices($request->getPathInfo());
        foreach ($notices as $notice) {
            // Ignore full page notices for admin urls
            if ($notice['full_page'] && strpos($request->attributes->get('_route'), 'admin_') !== 0) {
                return new Response($this['twig']->render('full-page-notice.html.twig', [
                    'message' => $notice['message']
                ]));
            }
        }
        $app['twig']->addGlobal('global_notices', $notices);
    }

    protected function afterCallback(Request $request, Response $response)
    {
        $this->addSecurityHeaders($response);
        if ($this->isLoggedIn()) {
            // only the first route handled is considered a login
            $this['session']->set('isLogin', false);

            // unset after the first route handled following loginReturn
            if (!$this->isUpkeepRoute($request)) {
                $this['session']->set('isLoginReturn', false);
            }

            // Set user site display names
            if (!$this['session']->has('userSiteDisplayNames')) {
                if (!empty($this->getUser()->getSites())) {
                    $userSiteDisplayNames = [];
                    foreach ($this->getUser()->getSites() as $userSite) {
                        $userSiteDisplayNames[$userSite->id] = $this->getSiteDisplayName($userSite->id, false);
                    }
                    $this['session']->set('userSiteDisplayNames', $userSiteDisplayNames);
                }
            }
        }
    }

    protected function finishCallback(Request $request, Response $response)
    {
        // moved to afterCallBack to fix session start error
    }

    public function isValidSite($email)
    {
        $user = $this->getUser();
        if (!$user || !$user->belongsToSite($email)) {
            return false;
        }
        if ($this->isStable() || $this->isProd()) {
            $siteGroup = $user->getSite($email);
            $site = $this['em']->getRepository('sites')->fetchOneBy([
                'deleted' => 0,
                'google_group' => $siteGroup->id,
            ]);
            if (!$site) {
                return false;
            }
            if (empty($site['mayolink_account']) && $site['awardee_id'] !== 'TEST') {
                // Site is invalid if it doesn't have a MayoLINK account id, unless it is in the TEST awardee
                return false;
            }
        }

        return true;
    }

    public function getReportKitUrl()
    {
        return $this->getConfig('reportKitUrl');
    }

    public function getFormErrors($form)
    {
        $errors = [];
        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }
        foreach ($form->all() as $child) {
            $childErrors = $this->getFormErrors($child);
            if (count($childErrors) > 0) {
                $errors = array_merge($errors, $childErrors);
            }
        }
        return $errors;
    }

    public function getSiteDisplayName($siteSuffix, $defaultToSiteSuffix = true)
    {
        $siteName = $defaultToSiteSuffix ? $siteSuffix : null ;
        if (!empty($siteSuffix)) {
            if (array_key_exists($siteSuffix, $this->siteNameMapper)) {
                $siteName = $this->siteNameMapper[$siteSuffix];
            } else {
                $site = $this['em']->getRepository('sites')->fetchOneBy([
                    'deleted' => 0,
                    'google_group' => $siteSuffix
                ]);
                if (!empty($site)) {
                    $siteName = $this->siteNameMapper[$siteSuffix] = $site['name'];
                }
            }
        }
        return $siteName;
    }

    public function getOrganizationDisplayName($organizationId)
    {
        $organizationName = $organizationId;
        if (!empty($organizationId)) {
            if (array_key_exists($organizationId, $this->organizationNameMapper)) {
                $organizationName = $this->organizationNameMapper[$organizationId];
            } else {
                $organization = $this['em']->getRepository('organizations')->fetchOneBy([
                    'id' => $organizationId
                ]);
                if (!empty($organization)) {
                    $organizationName = $this->organizationNameMapper[$organizationId] = $organization['name'];
                }
            }
        }
        return $organizationName;
    }

    public function getAwardeeDisplayName($awardeeId)
    {
        $awardeeName = $awardeeId;
        if (!empty($awardeeId)) {
            if (array_key_exists($awardeeId, $this->awardeeNameMapper)) {
                $awardeeName = $this->awardeeNameMapper[$awardeeId];
            } else {
                $awardee = $this['em']->getRepository('awardees')->fetchOneBy([
                    'id' => $awardeeId
                ]);
                if (!empty($awardee)) {
                    $awardeeName = $this->awardeeNameMapper[$awardeeId] = $awardee['name'];
                }
            }
        }
        return $awardeeName;
    }

    public function getUserEmailById($userId)
    {
        $user = $this['em']->getRepository('users')->fetchOneBy([
            'id' => $userId
        ]);
        if (!empty($user)) {
            return $user['email'];
        }
        return null;
    }

    /**
     * Returns true for TEST site if disable_test_access configuration is set to true
     */
    public function isTestSite()
    {
        return !empty($this->getConfig('disable_test_access')) && $this->getSiteAwardeeId() === 'TEST';
    }

    public function saveSiteMetaDataInSession()
    {
        $site = $this->getSiteEntity();
        if (!empty($site)) {
            $this['session']->set('siteOrganization', $site['organization']);
            $this['session']->set('siteOrganizationId', $site['organization_id']);
            $this['session']->set('siteOrganizationDisplayName', $this->getOrganizationDisplayName($site['organization_id']));
            $this['session']->set('siteAwardee', $site['awardee']);
            $this['session']->set('siteAwardeeId', $site['awardee_id']);
            $this['session']->set('siteAwardeeDisplayName', $this->getAwardeeDisplayName($site['awardee_id']));
            $this['session']->set('currentSiteDisplayName', $this->getAwardeeDisplayName($site['name']));
            $this['session']->set('siteType', $this->getSiteType());
            $this['session']->set('orderType', $this->getOrderType());
        } else {
            $this['session']->remove('siteOrganization');
            $this['session']->remove('siteOrganizationId');
            $this['session']->remove('siteOrganizationDisplayName');
            $this['session']->remove('siteAwardee');
            $this['session']->remove('siteAwardeeId');
            $this['session']->remove('siteAwardeeDisplayName');
            $this['session']->remove('currentSiteDisplayName');
            $this['session']->remove('siteType');
            $this['session']->remove('orderType');
        }
    }

    public function canMockLogin()
    {
        return $this->isLocal() && $this->getConfig('local_mock_auth');
    }

    private function getRdrOptions()
    {
        $rdrOptions = [];
        if ($this->isLocal()) {
            putenv('DATASTORE_EMULATOR_HOST=' . self::DATASTORE_EMULATOR_HOST);
            $keyFile = realpath(__DIR__ . '/../../../') . '/dev_config/rdr_key.json';
            if (file_exists($keyFile)) {
                $rdrOptions['key_file'] = $keyFile;
            }
        }
        $rdrOptions['config'] = $this->getRdrConfigs();
        $rdrOptions['logger'] = $this['logger'];
        $rdrOptions['cache'] = $this['cache'];
        $rdrOptions['em'] = $this['em'];

        return $rdrOptions;
    }

    private function getRdrConfigs()
    {
        return [
            'rdr_endpoint' => $this->getConfig('rdr_endpoint'),
            'rdr_disable_cache' => $this->getConfig('rdr_endpoint'),
            'cache_time' => intval($this->getConfig('cache_time')),
            'disable_test_access' => $this->getConfig('disable_test_access'),
            'genomics_start_time' => $this->getConfig('genomics_start_time'),
            'rdr_auth_json' => $this->getConfig('rdr_auth_json'),
            'cohort_one_launch_time' => $this->getConfig('cohort_one_launch_time')
        ];
    }
}
