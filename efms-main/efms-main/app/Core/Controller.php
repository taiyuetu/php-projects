<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Controller
 *
 * Base class for every controller. Provides the small set of helpers
 * a controller actually needs (render a view, return JSON, redirect,
 * validate input, read the current user) so subclasses stay thin and
 * focused on orchestration, not plumbing.
 */
abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'layouts.app'): Response
    {
        if ($layout) {
            $data['layout'] = $layout;
        }
        $data['auth'] = Auth::user();

        return Response::html(View::render($template, $data));
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url): Response
    {
        return Response::redirect($url);
    }

    /**
     * Validates $request data against $rules using the Validator, and
     * throws a ValidationException (caught by index.php) on failure so
     * controllers can validate in one line:
     *
     *   $data = $this->validate($request, ['name' => 'required|max:255']);
     */
    protected function validate(Request $request, array $rules): array
    {
        $validator = new Validator($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors());
        }

        return $validator->validated();
    }

    protected function currentUserId(): ?int
    {
        return Auth::id();
    }
}
