<?php
require '../Include/Config.php';

// This file is generated by Composer
require_once dirname(__FILE__).'/../vendor/autoload.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use Slim\Container;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\VersionMiddleware;

// Instantiate the app
$settings = require __DIR__.'/../Include/slim/settings.php';

$container = new Container;

// Add middleware to the application
$app = new App($container);

$app->add(new VersionMiddleware());

// Set up
require __DIR__.'/../Include/slim/error-handler.php';



$app->get('/begin', 'beginSession');
$app->post("/begin", "beginSession");
$app->get('/end', 'endSession');
$app->get('/two-factor', 'processTwoFactorGet');
$app->post('/two-factor', 'processTwoFactorPost');

function processTwoFactorGet(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/');
    $curUser = AuthenticationManager::GetCurrentUser();

    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => $curUser
    ];
    
    return $renderer->render($response, 'two-factor.php', $pageArgs);
}


function processTwoFactorPost(Request $request, Response $response, array $args)
{
    $loginRequestBody = (object)$request->getParsedBody();
    AuthenticationManager::Authenticate(AuthenticationManager::GetAuthenticationProvider(), $loginRequestBody);
}

function endSession(Request $request, Response $response, array $args)
{
    AuthenticationManager::EndSession();
}


function beginSession(Request $request, Response $response, array $args)
{
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'localAuthNextStepURL' => AuthenticationManager::GetSessionBeginURL()
    ];

    if ($request->getMethod() == "POST") {
        $loginRequestBody = (object)$request->getParsedBody();
        $authenticationResult = AuthenticationManager::Authenticate(new LocalAuthentication(), $loginRequestBody);
        $pageArgs['sErrorText'] = $authenticationResult->message;
    }

    $renderer = new PhpRenderer('templates/');
    
    $pageArgs['prefilledUserName'] = "";
    # Defermine if approprirate to pre-fill the username field
    if (isset($_GET['username'])) {
        $pageArgs['prefilledUserName'] = $_GET['username'];
    } elseif (isset($_SESSION['username'])) {
        $pageArgs['prefilledUserName'] = $_SESSION['username'];
    }

    return $renderer->render($response, 'begin-session.php', $pageArgs);
}

// Run app
$app->run();