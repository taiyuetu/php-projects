<?php
/**
 * Router
 * Parses the URL of the form:
 *   /public/index.php?url=controller/action/param1/param2
 * (rewritten by .htaccess to /controller/action/param1/param2)
 * and dispatches to the matching Controller::action(params...).
 */
class Router
{
    public function dispatch(string $url = '')
    {
        $url = trim($url, '/');
        $url = filter_var($url, FILTER_SANITIZE_URL);
        $segments = $url === '' ? [] : explode('/', $url);

        $controllerName = !empty($segments[0]) ? ucfirst(strtolower($segments[0])) : DEFAULT_CONTROLLER;
        $controllerClass = $controllerName . 'Controller';

        $action = $segments[1] ?? DEFAULT_ACTION;
        $params = array_slice($segments, 2);

        $controllerFile = __DIR__ . '/../app/controllers/' . $controllerClass . '.php';

        if (!file_exists($controllerFile)) {
            $this->notFound();
        }

        require_once $controllerFile;

        if (!class_exists($controllerClass)) {
            $this->notFound();
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $action)) {
            $this->notFound();
        }

        call_user_func_array([$controller, $action], $params);
    }

    private function notFound()
    {
        http_response_code(404);
        $viewFile = __DIR__ . '/../app/views/errors/404.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo '404 - Page Not Found';
        }
        exit;
    }
}
