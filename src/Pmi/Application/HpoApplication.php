<?php
namespace Pmi\Application;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Pmi\Entities\Configuration;
use Pmi\Security\UserProvider;
use Pmi\Audit\Log;
use Pmi\Service\NoticeService;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;

class HpoApplication extends AbstractApplication
{
    protected $configuration = [];
    protected $participantSource = 'rdr';
    protected $siteNameMapper = [];
    protected $organizationNameMapper = [];
    protected $awardeeNameMapper = [];

    public function setup($config = [])
    {
        parent::setup($config);

        $rdrOptions = [];
        if ($this->isLocal()) {
            $keyFile = realpath(__DIR__ . '/../../../') . '/dev_config/rdr_key.json';
            if (file_exists($keyFile)) {
                $rdrOptions['key_file'] = $keyFile;
            }
        }
        if ($this->getConfig('rdr_endpoint')) {
            $rdrOptions['endpoint'] = $this->getConfig('rdr_endpoint');
        }
        if ($this->getConfig('rdr_auth_json')) {
            $rdrOptions['key_contents'] = $this->getConfig('rdr_auth_json');
        }
        if ($this->getConfig('rdr_disable_cache') || $this->isPhpDevServer()) {
            $rdrOptions['disable_cache'] = true;
        }
        if ($this->getConfig('cache_time')) {
            $rdrOptions['cache_time'] = $this->getConfig('cache_time');
        }
        if ($this->getConfig('disable_test_access')) {
            $rdrOptions['disable_test_access'] = $this->getConfig('disable_test_access');
        }

        $this['pmi.drc.rdrhelper'] = new \Pmi\Drc\RdrHelper($rdrOptions);
        if ($this->participantSource == 'mock') {
            $this['pmi.drc.participants'] = new \Pmi\Drc\MockParticipantSearch();
        } else {
            $this['pmi.drc.participants'] = new \Pmi\Drc\RdrParticipants($this['pmi.drc.rdrhelper']);
        }

        $this['pmi.drc.appsclient'] = (!$this->isProd() && ($this['isUnitTest'] || $this->getConfig('gaBypass'))) ?
             \Pmi\Drc\MockAppsClient::createFromApp($this) : \Pmi\Drc\AppsClient::createFromApp($this);

        $this->registerDb();
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
        $anonRegex = '^/(timeout$|login$|cron\/)'; // cron uses GAE auth, so no need for Silex auth
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
                [['path' => '^/dashboard($|\/)'], 'ROLE_DASHBOARD'],
                [['path' => '^/admin($|\/)'], 'ROLE_ADMIN'],
                [['path' => '^/workqueue($|\/)'], ['ROLE_USER', 'ROLE_AWARDEE']],
                [['path' => '^/problem($|\/)'], ['ROLE_DV_ADMIN']],
                [['path' => '^/site($|\/)'], ['ROLE_USER', 'ROLE_AWARDEE']],
                [['path' => '^/help($|\/)'], ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_AWARDEE', 'ROLE_DV_ADMIN']],
                [['path' => '^/settings($|\/)'], ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_AWARDEE', 'ROLE_DV_ADMIN']],
                [['path' => '^/biobank($|\/)'], ['ROLE_BIOBANK', 'ROLE_SCRIPPS']],
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

    public function setHeaders(Response $response)
    {
        // prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // whitelist content that the client is allowed to request
        $whitelist =  "default-src 'self'"
            . " 'unsafe-eval'" // required for setTimeout and setInterval
            . " 'unsafe-inline'" // for the places we are using inline JS
            . " www.google-analytics.com www.googletagmanager.com" // Google Analytics
            . " storage.googleapis.com" // for SOP PDFs stored in a Google Storage bucket
            . " www.youtube.com" // for training videos hosted on YouTube
            . " cdn.plot.ly;" // allow plot.ly remote requests
            . " img-src www.google-analytics.com 'self' data:"; // allow Google Analytcs, self, and data: urls for img src

        $response->headers->set('Content-Security-Policy', $whitelist);

        // prevent browsers from sending unencrypted requests
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        
        // "low" security finding: prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // "low" security finding: enable XSS Protection
        // http://blog.innerht.ml/the-misunderstood-x-xss-protection/
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Default cache control header in ResponseHeaderBag::computeCacheControlValue returns "no-cache, private"
        // Recommendation from security team is to add no-store as well.
        $response->headers->addCacheControlDirective('no-cache, no-store');
    }
    
    public function switchSite($email)
    {
        $user = $this->getUser();
        if ($user && $user->belongsToSite($email)) {
            $this['session']->set('site', $user->getSite($email));
            $this['session']->remove('awardee');
            $this->setNewRoles($user);
            return true;
        } elseif ($user && $user->belongsToAwardee($email)) {
            $this['session']->set('awardee', $user->getAwardee($email));
            $this['session']->remove('site');
            $this->setNewRoles($user);
            return true;
        } else {
            return false;
        }
    }

    public function setNewRoles($user)
    {
        $roles = $user->getRoles();
        if ($this['session']->has('site')) {
            if(($key = array_search('ROLE_AWARDEE', $roles)) !== false) {
                unset($roles[$key]);
            }
        }
        if ($this['session']->has('awardee')) {
            if(($key = array_search('ROLE_USER', $roles)) !== false) {
                unset($roles[$key]);
            }
        }
        if ($roles != $user->getRoles()) {
            $token = new PostAuthenticationGuardToken($this['security.token_storage']->getToken()->getUser(), 'main', $roles);
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
            ->fetchOneBy(['google_group' => $googleGroup]);
    }

    public function getAwardeeEntity()
    {
        $googleGroup = $this->getAwardeeId();
        if (!$googleGroup) {
            return null;
        }
        return $this['em']
            ->getRepository('sites')
            ->fetchBy(['awardee' => $googleGroup]);
    }

    public function getSiteOrganization()
    {
        if ($this['isUnitTest']) {
            return null;
        }
        $site = $this->getSiteEntity();
        if (!$site || empty($site['organization'])) {
            return null;
        } else {
            return $site['organization'];
        }
    }

    public function getSiteAwardee()
    {
        if ($this['isUnitTest']) {
            return null;
        }
        $site = $this->getSiteEntity();
        if (!$site || empty($site['awardee_id'])) {
            return null;
        }
        return $site['awardee_id'];
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
            'status' => 1,
            'organization' => $org,
        ]);
    }

    public function isDVType() {
        $site = $this['em']->getRepository('sites')->fetchBy([
            'google_group' => $this->getSiteId(),
            'type' => 'DV'
        ]);
        return !empty($site);
    }

    protected function earlyBeforeCallback(Request $request, AbstractApplication $app)
    {
        if ($request->getBasePath() === '/web') {
            return $this->abort(404);
        }
    }
    
    protected function beforeCallback(Request $request, AbstractApplication $app)
    {
        $app->log(Log::REQUEST);
        
        // log the user out if their session is expired
        if ($this->isLoginExpired() && $request->attributes->get('_route') !== 'logout') {
            return $this->redirectToRoute('logout', ['timeout' => true]);
        }

        if ($this['session']->get('isLogin')) {
            $app->log(Log::LOGIN_SUCCESS, $this->getUser()->getRoles());
            $this->addFlashSuccess('Welcome, ' . $this->getUser()->getEmail() . '!');
        }

        // users with multiple roles must select their initial destination
        $hasMultiple = ($this->hasRole('ROLE_DASHBOARD') && ($this->hasRole('ROLE_USER') || $this->hasRole('ROLE_ADMIN') || $this->hasRole('ROLE_AWARDEE') || $this->hasRole('ROLE_DV_ADMIN')));
        if ($this['session']->get('isLoginReturn') && $hasMultiple && !$this->isUpkeepRoute($request)) {
            return $this->forwardToRoute('dashSplash', $request);
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
                    strpos($request->attributes->get('_route'), 'dashboard_') !== 0 &&
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
        $this->setHeaders($response);
    }
    
    protected function finishCallback(Request $request, Response $response)
    {
        if ($this->isLoggedIn()) {
            // only the first route handled is considered a login
            $this['session']->set('isLogin', false);
            
            // unset after the first route handled following loginReturn
            if (!$this->isUpkeepRoute($request)) {
                $this['session']->set('isLoginReturn', false);
            }
        }
    }

    public function isValidSite($email)
    {
        $user = $this->getUser();
        if (!$user || !$user->belongsToSite($email)) {
            return false;
        }
        if ($this->isProd()) {
            $siteGroup = $user->getSite($email);
            $site = $this['em']->getRepository('sites')->fetchOneBy([
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
        return !empty($this->getConfig('disable_test_access')) && $this->getSiteAwardee() === 'TEST';
    }
}
