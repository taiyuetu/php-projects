<?php
/**
 * Base Controller
 * All controllers extend this class to get view rendering,
 * model loading, and auth/role guard helpers.
 */
class Controller
{
    /**
     * Load a model instance by name.
     */
    protected function model(string $modelName)
    {
        return new $modelName();
    }

    /**
     * Render a view file inside the main app layout.
     * $view is given as "folder/file" (no .php), relative to app/views/
     */
    protected function render(string $view, array $data = [], string $layout = 'layouts/app')
    {
        extract($data);
        $viewFile = __DIR__ . '/../app/views/' . $view . '.php';

        if (!file_exists($viewFile)) {
            http_response_code(500);
            die('Application view is missing.');
        }

        if ($layout) {
            $layoutFile = __DIR__ . '/../app/views/' . $layout . '.php';
            // Render the inner view into $content, then wrap with layout
            ob_start();
            require $viewFile;
            $content = ob_get_clean();
            require $layoutFile;
        } else {
            require $viewFile;
        }
    }

    /**
     * Render a view with NO layout (e.g. login page).
     */
    protected function renderOnly(string $view, array $data = [])
    {
        $this->render($view, $data, '');
    }

    protected function redirect(string $path = '')
    {
        $base = rtrim(BASE_URL, '/');
        header('Location: ' . ($base === '/hrms/public' ? $base : $base) . '/' . ltrim($path, '/'));
        exit;
    }

    protected function json($data, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /** Flash message helpers (one-time session messages) */
    protected function setFlash(string $key, string $message)
    {
        $_SESSION['flash'][$key] = $message;
    }

    /** ---- Auth / Role guards ---- */

    protected function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    protected function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('auth/login');
        }
    }

    protected function requireRole($roles)
    {
        $this->requireLogin();
        $roles = is_array($roles) ? $roles : [$roles];
        if (!in_array($_SESSION['user_role'], $roles, true)) {
            http_response_code(403);
            die('403 - You do not have permission to access this page.');
        }
    }

    protected function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function csrfToken(): string
    {
        return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    }

    protected function verifyCsrf(): void
    {
        if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) ($_POST['_token'] ?? ''))) {
            http_response_code(419);
            exit('Page expired. Please try again.');
        }
    }

    /**
     * Slice an array of records and return pagination metadata and paginated items.
     */
    protected function paginate(array $items, int $perPage = 10, string $pageParam = 'page'): array
    {
        $totalItems = count($items);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $currentPage = max(1, (int) $this->input($pageParam, 1));
        if ($currentPage > $totalPages && $totalPages > 0) {
            $currentPage = $totalPages;
        }

        $offset = ($currentPage - 1) * $perPage;
        $slicedItems = array_slice($items, $offset, $perPage);
        $from = $totalItems === 0 ? 0 : $offset + 1;
        $to = min($offset + $perPage, $totalItems);

        return [
            'items'        => $slicedItems,
            'current_page' => $currentPage,
            'per_page'     => $perPage,
            'total_items'  => $totalItems,
            'total_pages'  => $totalPages,
            'has_prev'     => $currentPage > 1,
            'has_next'     => $currentPage < $totalPages,
            'prev_page'    => $currentPage - 1,
            'next_page'    => $currentPage + 1,
            'from'         => $from,
            'to'           => $to,
            'page_param'   => $pageParam,
        ];
    }
}
