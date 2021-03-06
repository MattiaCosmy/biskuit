<?php

use Biskuit\Event\PrefixEventDispatcher;
use Biskuit\Twig\TwigEngine;
use Biskuit\View\Asset\AssetFactory;
use Biskuit\View\Asset\AssetManager;
use Biskuit\View\Helper\DataHelper;
use Biskuit\View\Helper\DeferredHelper;
use Biskuit\View\Helper\GravatarHelper;
use Biskuit\View\Helper\MapHelper;
use Biskuit\View\Helper\MarkdownHelper;
use Biskuit\View\Helper\MetaHelper;
use Biskuit\View\Helper\ScriptHelper;
use Biskuit\View\Helper\SectionHelper;
use Biskuit\View\Helper\StyleHelper;
use Biskuit\View\Helper\TokenHelper;
use Biskuit\View\Helper\UrlHelper;
use Biskuit\View\Loader\FilesystemLoader;
use Biskuit\View\PhpEngine;
use Biskuit\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Templating\TemplateNameParser;

return [

    'name' => 'view',

    'include' => 'modules/*/index.php',

    'require' => [

        'view/twig'

    ],

    'main' => function ($app) {

        $app['view'] = function ($app) {
            return new View(new PrefixEventDispatcher('view.', $app['events']));
        };

        $app['assets'] = function () {
            return new AssetFactory();
        };

        $app['styles'] = function ($app) {
            return new AssetManager($app['assets']);
        };

        $app['scripts'] = function ($app) {
            return new AssetManager($app['assets']);
        };

        $app['module']->addLoader(function ($module) use ($app) {

            if (isset($module['views'])) {
                $app->extend('view', function ($view) use ($module) {
                    foreach ((array) $module['views'] as $name => $path) {
                        $view->map($name, $path);
                    }
                    return $view;
                });
            }

            return $module;
        });

    },

    'events' => [

        'controller' => [function ($event) use ($app) {

            $view = $app['view'];
            $layout = true;
            $result = $event->getControllerResult();

            if (is_array($result) && isset($result['$view'])) {

                foreach ($result as $key => $value) {
                    if ($key === '$view') {

                        if (isset($value['name'])) {
                            $name = $value['name'];
                            unset($value['name']);
                        }

                        if (isset($value['layout'])) {
                            $layout = $value['layout'];
                            unset($value['layout']);
                        }

                        $app->on('view.meta', function ($event, $meta) use ($value) {
                            $meta($value);
                        });

                    } elseif ($key[0] === '$') {

                        $view->data($key, $value);

                    }
                }

                if (isset($name)) {
                    $response = $result = $view->render($name, $result);
                }
            }

            if (!is_string($result)) {
                return;
            }

            if (is_string($layout)) {
                $view->map('layout', $layout);
            }

            if ($layout) {

                $view->section('content', (string) $result);

                if (null !== $result = $view->render('layout')) {
                    $response = $result;
                }
            }

            if (isset($response)) {
                $event->setResponse(new Response($response));
            }

        }, 50],

        'view.init' => [function ($event, $view) use ($app) {

            $view->addEngine(new PhpEngine(null, isset($app['locator']) ? new FilesystemLoader($app['locator']) : null));

            if (isset($app['twig'])) {
                $view->addEngine(new TwigEngine($app['twig'], new TemplateNameParser()));
            }

            $view->addGlobal('app', $app);
            $view->addGlobal('view', $view);

            $view->addHelpers([
                new DataHelper(),
                new DeferredHelper($app['events']),
                new GravatarHelper(),
                new MapHelper(),
                new MetaHelper(),
                new ScriptHelper($app['scripts']),
                new SectionHelper(),
                new StyleHelper($app['styles']),
                new UrlHelper($app['url'])
            ]);

            if (isset($app['csrf'])) {
                $view->addHelper(new TokenHelper($app['csrf']));
            }

            if (isset($app['markdown'])) {
                $view->addHelper(new MarkdownHelper($app['markdown']));
            }

        }, 50]

    ],

    'autoload' => [

        'Biskuit\\View\\' => 'src'

    ]

];
