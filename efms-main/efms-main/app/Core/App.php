<?php

declare(strict_types=1);

namespace App\Core;

/**
 * App
 *
 * Bootstraps configuration, timezone, error handling, and hands the
 * captured Request to the Router. public/index.php is a two-line
 * file that just calls App::run().
 */
final class App
{
    public static function run(): void
    {
        self::bootstrap();

        $router = new Router();
        require __DIR__ . '/../../routes/web.php'; // registers routes onto $router

        $request = Request::capture();

        try {
            $response = $router->dispatch($request);
        } catch (ValidationException $e) {
            if ($request->isJson()) {
                $response = Response::json(['errors' => $e->errors()], 422);
            } else {
                Session::flash('errors', $e->errors());
                Session::flash('old', $request->all());
                $response = Response::redirect($_SERVER['HTTP_REFERER'] ?? '/');
            }
        } catch (\Throwable $e) {
            Logger::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $response = self::errorResponse($e);
        }

        $response->send();
    }

    private static function bootstrap(): void
    {
        Config::load(__DIR__ . '/../../config');
        date_default_timezone_set(Config::get('app.timezone', 'UTC'));

        error_reporting(E_ALL);
        ini_set('display_errors', Config::get('app.debug') ? '1' : '0');
    }

    private static function errorResponse(\Throwable $e): Response
    {
        $debug = Config::get('app.debug', false);
        $message = $debug ? $e->getMessage() : 'Something went wrong. Please try again later.';

        return Response::html(
            '<h1>500 — Server Error</h1><p>' . View::e($message) . '</p>' .
            ($debug ? '<pre>' . View::e($e->getTraceAsString()) . '</pre>' : ''),
            500
        );
    }
}
