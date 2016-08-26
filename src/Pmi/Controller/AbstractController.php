<?php
namespace Pmi\Controller;

use Silex\Application;
use Silex\Api\ControllerProviderInterface;

class AbstractController implements ControllerProviderInterface
{
    protected static $routes = [];
    protected static $name;

    public function connect(Application $app)
    {
        $prefixes = [];
        if ($app->getName()) {
            $prefixes[] = $app->getName();
        }
        if (static::$name) {
            $prefixes[] = static::$name;
        }
        $prefix =  join('_', $prefixes);

        $controllers = $app['controllers_factory'];
        foreach (static::$routes as $route) {
            list($action, $pattern) = $route;
            if (isset($route[2])) {
                $options = $route[2];
            } else {
                $options = false;
            }
            $fullClassMethod = get_called_class() . "::{$action}Action";
            if (!isset($options['method']) || $options['method'] == 'GET') {
                $controller = $controllers->get($pattern, $fullClassMethod);
            } elseif ($options['method'] == 'POST') {
                $controller = $controllers->post($pattern, $fullClassMethod);
            } else {
                $controller = $controllers->match($pattern, $fullClassMethod);
                $controller->method($options['method']);
            }
            if ($prefix) {
                $controller->bind($prefix . '_' . $action);
            } else {
                $controller->bind($action);
            }
            if (is_array($options)) {
                foreach ($options as $optionName => $optionValue) {
                    switch ($optionName) {
                        case 'defaults':
                            foreach ($optionValue as $variable => $default) {
                                $controller->value($variable, $default);
                            }
                            break;
                        case 'patterns':
                            foreach ($optionValue as $variable => $pattern) {
                                $controller->assert($variable, $pattern);
                            }
                            break;
                        case 'before':
                            foreach ($optionValue as $callback) {
                                $controller->before([$app, "{$callback}BeforeCallback"]);
                            }
                            break;
                    }
                }
            }
        }
        return $controllers;
    }
}
