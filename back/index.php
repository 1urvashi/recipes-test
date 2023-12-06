<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RecipeApi\Authenticator;
use RecipeApi\RecipeRepository;
use Slim\Factory\AppFactory;

require 'vendor/autoload.php';

$app = AppFactory::create();

$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', '*');
});

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write(file_get_contents('index.html'));
    return $response;
});

$recipesResolver = function(Request $request, Response $response, $args) {
    $repo = new RecipeRepository();
    $params = $request->getParsedBody();

    $response->getBody()->write(json_encode($repo->getFullRecipes(
        (int)($params['offset'] ?? $_REQUEST['offset'] ?? 0),
        (int)($params['limit'] ?? $_REQUEST['limit'] ?? 20)
    )));

    return $response;
};

$app->get('/recipes', $recipesResolver);
$app->post('/recipes', $recipesResolver);

$app->post('/recipes/raw', function (Request $request, Response $response, $args) {
    Authenticator::authenticateRequest($request);
    $params = $request->getParsedBody();
    $repo = new RecipeRepository();
    $response->getBody()->write(json_encode($repo->getRecipes(
        (int)($params['offset'] ?? $_REQUEST['offset'] ?? 0),
        (int)($params['limit'] ?? $_REQUEST['limit'] ?? 20)
    )));

    return $response;
});

$app->post('/instructions/raw', function (Request $request, Response $response, $args) {
    Authenticator::authenticateRequest($request);
    $repo = new RecipeRepository();
    $ids = json_decode($request->getBody());

    $response->getBody()->write(json_encode($repo->getInstructions($ids)));

    return $response;
});

$app->post('/ingredients/raw', function (Request $request, Response $response, $args) {
    Authenticator::authenticateRequest($request);
    $repo = new RecipeRepository();
    $ids = json_decode($request->getBody());

    $response->getBody()->write(json_encode($repo->getIngredients($ids)));

    return $response;
});

$curlPostRequest = function ($url, $postData, $authorized = true) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

    if ($authorized) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . md5($url)]);
    }

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response);
};

$retrieveDataFromRawEndpoints = function (Request $request) use ($curlPostRequest) {
    Authenticator::authenticateRequest($request);

    $params = $request->getParsedBody();
    $offset = (int)($params['offset'] ?? $_REQUEST['offset'] ?? 0);
    $limit = (int)($params['limit'] ?? $_REQUEST['limit'] ?? 20);

    $recipesResponse = $curlPostRequest('http://localhost:3001/recipes/raw', [
        'offset' => $offset,
        'limit' => $limit,
    ]);

    $ids = array_map(function ($item) {
        return $item->id;
    }, $recipesResponse);

    $instructionsResponse = $curlPostRequest('http://localhost:3001/instructions/raw', ['ids' => $ids]);
    $ingredientsResponse = $curlPostRequest('http://localhost:3001/ingredients/raw', ['ids' => $ids]);

    return [
        'recipes' => $recipesResponse,
        'instructions' => $instructionsResponse,
        'ingredients' => $ingredientsResponse,
    ];
};

$app->get('/benchmark', function (Request $request, Response $response, $args) use ($curlPostRequest) {
    try {
        Authenticator::authenticateRequest($request);

        $recipesResponse = $curlPostRequest('http://localhost:3001/recipes/raw', []);
        $instructionsResponse = $curlPostRequest('http://localhost:3001/instructions/raw', []);
        $ingredientsResponse = $curlPostRequest('http://localhost:3001/ingredients/raw', []);

        $responseData = [
            'recipes' => $recipesResponse,
            'instructions' => $instructionsResponse,
            'ingredients' => $ingredientsResponse,
        ];

        $response->getBody()->write(json_encode($responseData));
        return $response;
    } catch (Exception $e) {        
        echo json_encode(['error' => 'Internal Server Error']);
    }
});

$app->run();
