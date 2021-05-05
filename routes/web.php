<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});


//Chat
$router->group(["namespace"=>"chat","prefix" => "chats"],function () use ($router){
    $router->post('/send-a-message', 'chatController@sendMessage');
    $router->get('/', 'chatController@getUserChats');
    $router->get('/{id}/messages', 'chatController@getChatMessages');
    $router->put('/{id}/markAsRead', 'chatController@markAsRead');
    $router->delete('messages/{id}/delete', 'chatController@deleteAMessage');
    $router->get('get_chat_id/{id}', 'chatController@getChatID');
});
