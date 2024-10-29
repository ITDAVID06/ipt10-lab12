<?php

require "vendor/autoload.php";
require "init.php";

// Database connection object (from init.php (DatabaseConnection))
global $conn;

// try {

    // Create Router instance
    $router = new \Bramus\Router\Router();

    // Define routes
    $router->get('/', '\App\Controllers\HomeController@index');

    $router->get('/register', '\App\Controllers\ExamController@registrationForm');
    $router->post('/register', '\App\Controllers\ExamController@register');

    $router->get('/login', '\App\Controllers\ExamController@loginForm');
    $router->post('/login', '\App\Controllers\ExamController@login');

    $router->get('/examinees', '\App\Controllers\ExamController@displayExaminees');

    $router->get('/exam', '\App\Controllers\ExamController@exam');
    $router->post('/exam', '\App\Controllers\ExamController@exam');
    $router->get('/result', '\App\Controllers\ExamController@result');

    // Run it!
    $router->run();

// } catch (Exception $e) {

//     echo json_encode([
//         'error' => $e->getMessage()
//     ]);

// }
