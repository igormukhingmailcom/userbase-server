<?php

use UserBase\Server\Application;
use Symfony\Component\HttpFoundation\Request;

$application = new Application();

$application->before(function (Request $request) use ($application) {
    $token = $application['security']->getToken();
    if ($token) {
        if ($request->getRequestUri()!='/login') {

            if ($token->getUser() == 'anon.') {
                //exit('anon!');
                //return $app->redirect('/login');
            } else {
                $application['user'] = $token->getUser();
                $application['twig']->addGlobal('user', $token->getUser());
            }
        }


        $postfix = $application['userbase.postfix'];
        if ($postfix) {
            $application['twig']->addGlobal('postfix', $postfix);
        }
        $application['twig']->addGlobal('logourl', $application['userbase.logourl']);
        
        $filter = new Twig_SimpleFilter('mydate', function ($value) {
            if ($value>0) {
                return date('d/M/Y');
            } else {
                return '-';
            }
        });

        $filter = new Twig_SimpleFilter('star', function ($value) {
            $value = str_replace('*', '<i class="fa fa-star"></i>', $value);
            return $value;
        });

        $application['twig']->addFilter($filter);


    }
    //$application['twig']->addGlobal('site', $application['site']);
});

return $application;
